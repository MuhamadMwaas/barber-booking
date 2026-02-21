<?php

namespace App\Services\Payments;

use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InvoicePaymentService
{
    private const BC_SCALE = 2;

    /**
     * Create a payment for an invoice and update invoice status.
     */
    public function createFromInvoice(
        Invoice $invoice,
        string|float|int $amount,
        int|string $paymentMethod,
        PaymentStatus $status,
        array $metadata = []
    ): Payment {
        return DB::transaction(function () use ($invoice, $amount, $paymentMethod, $status, $metadata) {
            $invoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$invoice->status->isPayable()) {
                throw new InvalidArgumentException('Invoice is not payable.');
            }

            $amountStr = $this->normalizeAmount($amount);
            if (bccomp($amountStr, '0', self::BC_SCALE) <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            $totalAmount = $this->normalizeAmount($invoice->total_amount ?? '0');
            $paidTotal = $this->getPaidTotal($invoice);
            $remaining = $this->bcsub($totalAmount, $paidTotal);

            if (bccomp($remaining, '0', self::BC_SCALE) <= 0) {
                throw new RuntimeException('Invoice is already fully paid.');
            }
            if (bccomp($amountStr, $remaining, self::BC_SCALE) === 1) {
                throw new InvalidArgumentException('Payment amount exceeds remaining balance.');
            }

            $paymentMethodId = $this->resolvePaymentMethodId($paymentMethod);
            if (!$paymentMethodId) {
                throw new InvalidArgumentException('Payment method not found.');
            }

            $type = bccomp($amountStr, $remaining, self::BC_SCALE) === 0
                ? Payment::TYPE_FULL
                : Payment::TYPE_PARTIAL;

            $payment = Payment::create([
                'payment_method_id' => $paymentMethodId,
                'payment_number' => Payment::generatePaymentNumber(),
                'amount' => $amountStr,
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'status' => $status,
                'type' => $type,
                'payment_gateway_id' => null,
                'payment_metadata' => $metadata,
                'paymentable_id' => $invoice->id,
                'paymentable_type' => Invoice::class,
            ]);

            // Only update invoice status if payment is successful
            if ($status->isSuccessful()) {
                $newPaidTotal = $this->bcadd($paidTotal, $amountStr);
                $invoice->status = bccomp($newPaidTotal, $totalAmount, self::BC_SCALE) === 0
                    ? InvoiceStatus::PAID
                    : InvoiceStatus::PARTIALLY_PAID;
                $invoice->save();
            }

            return $payment->fresh();
        });
    }

    private function getPaidTotal(Invoice $invoice): string
    {
        $sum = $invoice->payments()->successful()->sum('amount');
        return $this->normalizeAmount($sum ?? '0');
    }

    private function resolvePaymentMethodId(int|string $paymentMethod): ?int
    {
        $query = PaymentMethod::query()->where('status', true);

        if (is_int($paymentMethod) || ctype_digit((string) $paymentMethod)) {
            return $query->whereKey((int) $paymentMethod)->value('id');
        }

        return $query->where('code', (string) $paymentMethod)->value('id');
    }

    private function normalizeAmount(string|float|int $amount): string
    {
        if (is_string($amount)) {
            $amount = trim($amount);
            if ($amount === '') {
                return '0';
            }
            if (!is_numeric($amount)) {
                throw new InvalidArgumentException('Amount must be numeric.');
            }
            return bcadd($amount, '0', self::BC_SCALE);
        }

        return number_format((float) $amount, self::BC_SCALE, '.', '');
    }

    private function bcadd(string $left, string $right): string
    {
        return bcadd($left, $right, self::BC_SCALE);
    }

    private function bcsub(string $left, string $right): string
    {
        return bcsub($left, $right, self::BC_SCALE);
    }
}
