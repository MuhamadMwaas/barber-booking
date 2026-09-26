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
     * never a partial payment. A higher one is the full price plus a tip for the
     * provider(s); the tip is stored in tip_amount, outside the taxed totals.
     * One payment covers the invoice owner plus every linked appointment, and
     * all covered appointments are completed together.
     *
     * @param  int|string  $paymentMethod  Active PaymentMethod id, or `cash`/`card`.
     * @param  float|null  $finalAmount  What the customer paid. Null means the full item total.
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

            // Lock the WHOLE group (same rows, same order as before, so the
            // lock order shared with AppointmentDeletionService and
            // AppointmentCancellationService is unchanged), then settle only
            // the blocks that still stand. A cancelled block of a split
            // booking is not paid for and must not block the rest (BOOKING-GAP-01).
            $lockedGroup = $invoiceOwner->linkedGroup()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->assertAppointmentsCanBePaid($lockedGroup, $appointment->id);

            $coveredAppointments = $lockedGroup
                ->filter(fn (Appointment $member) => $member->isActiveInGroup())
                ->values();
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

            // What the customer handed over splits into the invoice price
            // (full or discounted) and, when it exceeds the items total, a tip.
            [$chargedAmount, $tipAmount] = $this->splitFinalAmount($invoice, $finalAmount);
            $invoice = $invoiceService->applyFinalAmount($invoice, $chargedAmount);

            return $this->finalizeLockedInvoice(
                invoice: $invoice,
                tipAmount: $tipAmount,
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
        string $tipAmount,
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
            // Beside total_amount, never inside it: a tip is not revenue and
            // carries no VAT, so subtotal + tax_amount == total_amount still holds.
            'tip_amount' => $tipAmount,
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
                    'tip_amount' => $tipAmount,
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
            'tip_amount' => $tipAmount,
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
            'tip_amount' => $tipAmount,
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
            throw new InvalidArgumentException(__('payment.errors.method_not_found'));
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
                __('payment.errors.method_not_on_site')
            ),
        };
    }

    private function appointmentPaymentMethodFor(PaymentMethod $method): string
    {
        return $method->type === PaymentMethod::TYPE_CASH ? 'cash' : 'card';
    }

    /**
     * The payment is refused when the appointment the cashier opened is itself
     * cancelled / no-show, or when nothing in the group still stands.
     *
     * It used to refuse when ANY member was cancelled, so cancelling one block
     * of a group made the remaining blocks impossible to pay for.
     *
     * @param  Collection<int, Appointment>  $group  the whole locked group
     */
    private function assertAppointmentsCanBePaid($group, int $requestedAppointmentId): void
    {
        if ($group->isEmpty()) {
            throw new InvalidArgumentException(__('payment.errors.no_appointments'));
        }

        $requested = $group->firstWhere('id', $requestedAppointmentId);

        if (($requested && ! $requested->isActiveInGroup())
            || $group->every(fn (Appointment $member) => ! $member->isActiveInGroup())) {
            throw new InvalidArgumentException(__('payment.errors.cancelled_or_no_show'));
        }
    }

    /**
     * Split the amount the customer paid into [invoice price, tip].
     *
     *   paid <  itemsGross  => discount: charge `paid`, no tip
     *   paid == itemsGross  => full price, no tip
     *   paid >  itemsGross  => tip: charge the full items total, tip = the excess
     *
     * The charged part goes through applyFinalAmount() (discount + VAT); the tip
     * never does, because a tip is not revenue and carries no VAT.
     *
     * @return array{0: float|null, 1: string} Null charge means the full items total.
     */
    private function splitFinalAmount(Invoice $invoice, ?float $finalAmount): array
    {
        $itemsGross = (string) $invoice->items()->sum('total_amount');
        $paid = $finalAmount === null
            ? $itemsGross
            : number_format($finalAmount, 2, '.', '');

        if (bccomp($itemsGross, '0.00', 2) <= 0) {
            throw new InvalidArgumentException(__('payment.errors.invoice_not_positive'));
        }

        if (bccomp($paid, '0.00', 2) <= 0) {
            throw new InvalidArgumentException(__('payment.errors.amount_not_positive'));
        }

        if (bccomp($paid, $itemsGross, 2) === 1) {
            return [null, bcsub($paid, $itemsGross, 2)];
        }

        return [$finalAmount, '0.00'];
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
            throw new InvalidArgumentException(__('payment.errors.duration_not_positive'));
        }

        /** @var Appointment|null $appointment */
        $appointment = $appointments->firstWhere('id', $appointmentId);
        if (! $appointment) {
            throw new InvalidArgumentException(__('payment.errors.appointment_not_in_invoice'));
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
