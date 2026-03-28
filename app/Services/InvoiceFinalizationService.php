<?php
namespace App\Services;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class InvoiceFinalizationService
{
    /**
     * تحويل فاتورة Draft إلى Paid مع TSE
     */
    public function finalizeDraftInvoice(
        Invoice $invoice,
        string $paymentType,
        float $amountPaid,
        ?string $notes = null,
        bool $applyTse = true
    ): Invoice {

        // التحقق من الحالة
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException(
                'يمكن فقط تحويل الفواتير Draft. الحالة الحالية: ' . $invoice->status->getLabel()
            );
        }

        if (!$invoice->appointment) {
            throw new \InvalidArgumentException('الفاتورة غير مرتبطة بحجز');
        }

        DB::beginTransaction();

        try {
            $tseData = $applyTse
                ? $this->applyTSESignature($invoice, $paymentType, $amountPaid)
                : $this->createPlaceholderTSE();

            $invoiceNumber = Invoice::generateInvoiceNumber();

            // 3. تحديد حالة الفاتورة بناءً على المبلغ المدفوع
            $invoiceStatus = InvoiceStatus::PAID;

            // 4. تحديث الفاتورة
            $invoice->update([
                'invoice_number' => $invoiceNumber,
                'status' => $invoiceStatus,
                'notes' => $notes,
                'invoice_data' => array_merge(
                    $invoice->invoice_data ?? [],
                    [
                        'tse_data' => $tseData,
                        'finalized_at' => now()->toISOString(),
                        'finalized_by' => Auth::user()?->full_name ?? 'System',
                        'payment_type' => $paymentType,
                        'amount_paid' => $amountPaid,
                        'finalization_method' => 'api',
                    ]
                ),
            ]);

            // 5. تحديث حالة الحجز
            $this->updateAppointmentStatus(
                $invoice->appointment,
                $paymentType,
                $invoiceStatus
            );

            // 6. إنشاء سجل الدفع
            $payment = $this->createPaymentRecord(
                $invoice,
                $paymentType,
                $amountPaid,
                $tseData
            );

            DB::commit();

            // 7. تسجيل النجاح
            Log::info('Invoice finalized successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'amount_paid' => $amountPaid,
                'payment_id' => $payment->id,
                'tse_applied' => $applyTse,
            ]);

            return $invoice->fresh(['appointment', 'customer', 'items', 'payments']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to finalize invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException(
                'فشل في إتمام الفاتورة: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * تطبيق التوقيع الرقمي TSE
     */
    private function applyTSESignature(
        Invoice $invoice,
        string $paymentType,
        float $amountPaid
    ): array {

        // TODO: الربط الفعلي مع TSE Cloud Service
        // مثال على المزودين:
        // - fiskaly (https://fiskaly.com)
        // - epson TSE
        // - Swissbit TSE

        // للآن، نُنشئ بيانات placeholder
        return [
            'tse_enabled' => false,
            'tse_provider' => 'fiskaly', // سيتم تحديده لاحقاً
            'transaction_number' => null,
            'certified_timestamp' => now()->toISOString(),
            'signature_data' => null,
            'tse_serial_number' => null,
            'signature_algorithm' => 'ecdsa-plain-SHA256',
            'signature_counter' => null,
            'signature_value' => null,
            'public_key' => null,
            'certificate_serial' => null,
            'log_time' => now()->toISOString(),
            'note' => 'TSE not yet implemented - placeholder data',
        ];

        // الكود الفعلي سيكون شيء مثل:
        /*
        $tseClient = app(TSEClient::class);

        $tseResponse = $tseClient->signTransaction([
            'type' => 'POS_RECEIPT',
            'data' => [
                'invoice_number' => $invoice->id,
                'amount' => $amountPaid,
                'currency' => 'EUR',
                'payment_type' => $paymentType,
                'items' => $invoice->items->map(fn($item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                ]),
            ],
        ]);

        return [
            'tse_enabled' => true,
            'transaction_number' => $tseResponse['transaction_number'],
            'certified_timestamp' => $tseResponse['timestamp'],
            'signature_data' => $tseResponse['signature'],
            // ... باقي البيانات من TSE
        ];
        */
    }

    /**
     * إنشاء placeholder TSE (عندما TSE غير مفعل)
     */
    private function createPlaceholderTSE(): array
    {
        return [
            'tse_enabled' => false,
            'note' => 'TSE signature not applied',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * تحديد حالة الفاتورة بناءً على المبلغ المدفوع
     */
    private function determineInvoiceStatus(float $totalAmount, float $amountPaid): InvoiceStatus
    {
        $bcTotal = (string) $totalAmount;
        $bcPaid = (string) $amountPaid;

        $comparison = bccomp($bcPaid, $bcTotal, 2);

        if ($comparison === 0) {
            return InvoiceStatus::PAID;
        } elseif ($comparison === -1) {
            return InvoiceStatus::PARTIALLY_PAID;
        } else {
            return InvoiceStatus::PAID;
        }
    }

    /**
     * تحديث حالة الحجز
     */
    private function updateAppointmentStatus(
        Appointment $appointment,
        string $paymentType,
        InvoiceStatus $invoiceStatus
    ): void {

        $paymentStatus = match($invoiceStatus) {
            InvoiceStatus::PAID => PaymentStatus::from($paymentType),
            InvoiceStatus::PARTIALLY_PAID => PaymentStatus::PENDING,
            default => PaymentStatus::PENDING,
        };

        // TODO: ربط بجدول payment_methods
        $appointment->update([
            'payment_status' => $paymentStatus,
            'payment_method' => 'Cash',
        ]);
    }

    /**
     * إنشاء سجل الدفع
     */
    private function createPaymentRecord(
        Invoice $invoice,
        string $paymentType,
        float $amountPaid,
        array $tseData
    ): Payment {

        // حساب الضرائب على المبلغ المدفوع
        $taxRate = $invoice->tax_rate;
        $taxCalculation = $this->calculateReverseTax($amountPaid, $taxRate);

        return Payment::create([
            'payment_method_id' => null, // TODO: ربط بجدول payment_methods
            'payment_number' => Payment::generatePaymentNumber(),
            'amount' => $amountPaid,
            'subtotal' => $taxCalculation['subtotal'],
            'tax_amount' => $taxCalculation['tax_amount'],
            'status' => PaymentStatus::from($paymentType),
            'type' => $this->determinePaymentType($invoice->total_amount, $amountPaid),
            'paymentable_id' => $invoice->id,
            'paymentable_type' => Invoice::class,
            'payment_metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'appointment_number' => $invoice->appointment->number,
                'tse_transaction_number' => $tseData['transaction_number'] ?? null,
                'tse_timestamp' => $tseData['certified_timestamp'] ?? null,
                'payment_date' => now()->toISOString(),
                'collected_by' => auth()->user()?->full_name ?? 'System',
            ],
        ]);
    }

    /**
     * تحديد نوع الدفع
     */
    private function determinePaymentType(float $totalAmount, float $amountPaid): string
    {
        $comparison = bccomp((string)$amountPaid, (string)$totalAmount, 2);

        return match($comparison) {
            0 => Payment::TYPE_FULL,
            -1 => Payment::TYPE_PARTIAL,
            default => Payment::TYPE_FULL,
        };
    }

    /**
     * حساب الضريبة العكسية
     */
    private function calculateReverseTax(float $totalWithTax, float $taxRate): array
    {
        bcscale(6);

        $total = (string) $totalWithTax;
        $rate = (string) $taxRate;

        $factor = bcadd('1', bcdiv($rate, '100', 6), 6);
        $subtotal = bcdiv($total, $factor, 6);
        $taxAmount = bcsub($total, $subtotal, 6);

        return [
            'subtotal' => round((float)$subtotal, 2),
            'tax_amount' => round((float)$taxAmount, 2),
            'total' => $totalWithTax,
        ];
    }
}
