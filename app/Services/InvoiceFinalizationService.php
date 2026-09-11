<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\InvoiceAlreadyFinalizedException;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The one application service that owns an on-site payment.
 *
 * StaffDashboard is the reference workflow. Filament tables are only adapters:
 * they collect an appointment, a final (possibly discounted) price, and either
 * cash/card or an active PaymentMethod id, then call this service.
 */
class InvoiceFinalizationService
{
    /**
     * Collect one full on-site payment and finalize the unified invoice.
     *
     * A lower final amount is a deliberate special-customer price (discount),
     * never a partial payment. One payment covers the invoice owner plus every
     * linked appointment, and all covered appointments are completed together.
     *
     * @param  int|string  $paymentMethod  Active PaymentMethod id, or `cash`/`card`.
     * @param  float|null  $finalAmount  Null means the full item total.
     */
    public function finalizeAppointmentPayment(
        Appointment $appointment,
        int|string $paymentMethod,
        ?float $finalAmount = null,
        ?string $notes = null,
        string $source = 'staff_dashboard',
        ?int $adjustedDuration = null
    ): Invoice {
        return DB::transaction(function () use ($appointment, $paymentMethod, $finalAmount, $notes, $source, $adjustedDuration) {
            $invoiceOwnerId = $appointment->parent_appointment_id ?? $appointment->id;

            // The invoice always belongs to the parent (or the standalone
            // appointment). Lock it first so every payment entry point queues on
            // the same stable row even when no invoice exists yet.
            $invoiceOwner = Appointment::query()
                ->whereKey($invoiceOwnerId)
                ->lockForUpdate()
                ->firstOrFail();

            $coveredAppointments = $invoiceOwner->linkedGroup()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->assertAppointmentsCanBePaid($coveredAppointments);
            $this->applyAdjustedDuration(
                $coveredAppointments,
                $appointment->id,
                $adjustedDuration
            );

            $method = $this->resolvePaymentMethod($paymentMethod);
            $paymentStatus = $this->paymentStatusFor($method);
            $methodCode = $this->appointmentPaymentMethodFor($method);

            // Check the state while holding the row lock. A retry/double-click
            // sees the committed PAID state and is reported as already finalized,
            // rather than inserting a second Payment.
            $existingInvoice = $invoiceOwner->invoice()
                ->lockForUpdate()
                ->first();

            if ($existingInvoice && $existingInvoice->status !== InvoiceStatus::DRAFT) {
                throw new InvoiceAlreadyFinalizedException($existingInvoice);
            }

            $invoiceService = app(InvoiceService::class);

            // Rebuild from every service in the parent/children group. This is
            // the same behaviour the StaffDashboard established as canonical.
            $invoice = $invoiceService->rebuildAggregatedInvoice($invoiceOwner);

            $this->assertValidFinalAmount($invoice, $finalAmount);
            $invoice = $invoiceService->applyFinalAmount($invoice, $finalAmount);

            return $this->finalizeLockedInvoice(
                invoice: $invoice,
                coveredAppointments: $coveredAppointments,
                paymentMethod: $method,
                paymentStatus: $paymentStatus,
                appointmentPaymentMethod: $methodCode,
                notes: $notes,
                source: $source,
            );
        });
    }

    /**
     * @param  Collection<int, Appointment>  $coveredAppointments
     */
    private function finalizeLockedInvoice(
        Invoice $invoice,
        $coveredAppointments,
        PaymentMethod $paymentMethod,
        PaymentStatus $paymentStatus,
        string $appointmentPaymentMethod,
        ?string $notes,
        string $source
    ): Invoice {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new InvoiceAlreadyFinalizedException($invoice);
        }

        $amountPaid = (string) $invoice->total_amount;
        $invoiceNumber = Invoice::generateInvoiceNumber();
        $tseData = $this->disabledTseMetadata();

        $invoice->update([
            'invoice_number' => $invoiceNumber,
            'status' => InvoiceStatus::PAID,
            'notes' => $notes,
            'invoice_data' => array_merge(
                $invoice->invoice_data ?? [],
                [
                    'tse_data' => $tseData,
                    'finalized_at' => now()->toISOString(),
                    'finalized_by' => Auth::user()?->full_name ?? 'System',
                    'payment_type' => (string) $paymentStatus->value,
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method_code' => $appointmentPaymentMethod,
                    'amount_paid' => $amountPaid,
                    'finalization_method' => $source,
                ]
            ),
        ]);

        foreach ($coveredAppointments as $coveredAppointment) {
            $coveredAppointment->update([
                'status' => AppointmentStatus::COMPLETED,
                'payment_status' => $paymentStatus,
                // Stable machine value. Human labels come from PaymentMethod or
                // translations and must never be persisted in this field.
                'payment_method' => $appointmentPaymentMethod,
            ]);
        }

        $payment = Payment::create([
            'payment_method_id' => $paymentMethod->id,
            'payment_number' => Payment::generatePaymentNumber(),
            'amount' => $amountPaid,
            // The Payment is proof of this exact invoice, so copy its reconciled
            // post-discount money split rather than calculating VAT a second time.
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'status' => $paymentStatus,
            'type' => Payment::TYPE_FULL,
            'paymentable_id' => $invoice->id,
            'paymentable_type' => Invoice::class,
            'payment_metadata' => [
                'invoice_number' => $invoiceNumber,
                'appointment_number' => $invoice->appointment->number,
                'covered_appointment_ids' => $coveredAppointments->pluck('id')->all(),
                'payment_method_code' => $appointmentPaymentMethod,
                'payment_date' => now()->toISOString(),
                'collected_by' => Auth::user()?->full_name ?? 'System',
                'source' => $source,
                'tse_enabled' => false,
            ],
        ]);

        Log::info('Unified on-site payment finalized', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'payment_id' => $payment->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_method_code' => $appointmentPaymentMethod,
            'amount_paid' => $amountPaid,
            'covered_appointment_ids' => $coveredAppointments->pluck('id')->all(),
            'source' => $source,
            'tse_enabled' => false,
        ]);

        return $invoice->fresh(['appointment', 'customer', 'items', 'payments']);
    }

    private function resolvePaymentMethod(int|string $identifier): PaymentMethod
    {
        if (is_int($identifier)) {
            $method = PaymentMethod::query()
                ->active()
                ->lockForUpdate()
                ->find($identifier);
        } else {
            $key = strtolower(trim($identifier));

            $method = match ($key) {
                'cash' => PaymentMethod::query()
                    ->active()
                    ->where('type', PaymentMethod::TYPE_CASH)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first(),
                'card' => PaymentMethod::query()
                    ->active()
                    ->whereIn('type', [
                        PaymentMethod::TYPE_DEBIT_CARD,
                        PaymentMethod::TYPE_CREDIT_CARD,
                    ])
                    // The current UI says only "Card". Prefer debit when both
                    // seeded variants exist; a concrete id from Filament wins.
                    ->orderByDesc('type')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first(),
                default => null,
            };
        }

        if (! $method) {
            throw new InvalidArgumentException('طريقة الدفع غير موجودة أو غير مفعلة.');
        }

        // Online gateways and bank transfers are deliberately outside the
        // current product policy: payment is cash/card in the salon only.
        $this->paymentStatusFor($method);

        return $method;
    }

    private function paymentStatusFor(PaymentMethod $method): PaymentStatus
    {
        return match ($method->type) {
            PaymentMethod::TYPE_CASH => PaymentStatus::PAID_ONSTIE_CASH,
            PaymentMethod::TYPE_CREDIT_CARD,
            PaymentMethod::TYPE_DEBIT_CARD => PaymentStatus::PAID_ONSTIE_CARD,
            default => throw new InvalidArgumentException(
                'طريقة الدفع الحالية يجب أن تكون نقداً أو بطاقة داخل الصالون.'
            ),
        };
    }

    private function appointmentPaymentMethodFor(PaymentMethod $method): string
    {
        return $method->type === PaymentMethod::TYPE_CASH ? 'cash' : 'card';
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     */
    private function assertAppointmentsCanBePaid($appointments): void
    {
        if ($appointments->isEmpty()) {
            throw new InvalidArgumentException('لا توجد مواعيد مرتبطة بهذه الفاتورة.');
        }

        $invalid = $appointments->first(fn (Appointment $appointment) => in_array(
            $appointment->status,
            [
                AppointmentStatus::USER_CANCELLED,
                AppointmentStatus::ADMIN_CANCELLED,
                AppointmentStatus::NO_SHOW,
            ],
            true
        ));

        if ($invalid) {
            throw new InvalidArgumentException('لا يمكن تحصيل فاتورة تحتوي على موعد ملغي أو لم يحضر صاحبه.');
        }
    }

    private function assertValidFinalAmount(Invoice $invoice, ?float $finalAmount): void
    {
        $itemsGross = (string) $invoice->items()->sum('total_amount');
        $requested = $finalAmount === null
            ? $itemsGross
            : number_format($finalAmount, 2, '.', '');

        if (bccomp($itemsGross, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('لا يمكن تحصيل فاتورة بلا مبلغ موجب.');
        }

        if (bccomp($requested, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('يجب أن يكون مبلغ الدفع أكبر من صفر.');
        }

        if (bccomp($requested, $itemsGross, 2) === 1) {
            throw new InvalidArgumentException('مبلغ الدفع لا يمكن أن يتجاوز مجموع خدمات الفاتورة.');
        }
    }

    /**
     * Preserve the provider appointment screen's optional duration correction,
     * but execute it inside the same transaction as the payment.
     *
     * @param  Collection<int, Appointment>  $appointments
     */
    private function applyAdjustedDuration($appointments, int $appointmentId, ?int $minutes): void
    {
        if ($minutes === null) {
            return;
        }

        if ($minutes <= 0) {
            throw new InvalidArgumentException('مدة الموعد المعدلة يجب أن تكون أكبر من صفر.');
        }

        /** @var Appointment|null $appointment */
        $appointment = $appointments->firstWhere('id', $appointmentId);
        if (! $appointment) {
            throw new InvalidArgumentException('الموعد المحدد ليس ضمن الفاتورة الموحدة.');
        }

        if ((int) $appointment->duration_minutes === $minutes) {
            return;
        }

        $appointment->update([
            'duration_minutes' => $minutes,
            'end_time' => $appointment->start_time->copy()->addMinutes($minutes),
        ]);
    }

    /**
     * TSE is deliberately disabled for the current product phase.
     *
     * No network client is called from payment. Keeping explicit metadata makes
     * that operational decision visible on every invoice and Payment audit row.
     */
    private function disabledTseMetadata(): array
    {
        return [
            'tse_enabled' => false,
            'note' => 'TSE is disabled by current business configuration',
            'timestamp' => now()->toISOString(),
        ];
    }
}
