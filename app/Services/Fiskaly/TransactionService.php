<?php

namespace App\Services\Fiskaly;

use App\Exceptions\FiskalyException;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    protected FiskalyClient $client;

    public function __construct(FiskalyClient $client)
    {
        $this->client = $client;
    }

    /**
     * Start a new transaction
     */
    public function start(string $tssId, string $clientId, array $data = []): array
    {
        $transactionId = $data['transaction_id'] ?? Str::uuid()->toString();

        $payload = [
            'state' => 'ACTIVE',
            'client_id' => $clientId,
        ];

        try {
            $response = $this->client->put(
                "/tss/{$tssId}/tx/{$transactionId}" . "?tx_revision=1",
                $payload
            );

            return [
                'transaction_id' => $transactionId,
                'tss_id' => $tssId,
                'client_id' => $clientId,
                'number' => $response['number'] ?? null,
                'time_start' => $response['time_start'] ?? now()->toIso8601String(),
                'state' => $response['state'] ?? 'ACTIVE',
            ];
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to start transaction: " . $e->getMessage());
        }
    }

    /**
     * Update transaction with line items
     */
    public function update(string $tssId, string $transactionId, array $data): array
    {
        try {
            $response = $this->client->put(
                "/tss/{$tssId}/tx/{$transactionId}",
                [
                    'state' => 'ACTIVE',
                    'schema' => $this->buildTransactionSchema($data),
                ]
            );

            return [
                'transaction_id' => $transactionId,
                'state' => $response['state'] ?? 'ACTIVE',
                'updated_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to update transaction: " . $e->getMessage());
        }
    }

    /**
     * Finish transaction and get signature
     */
    public function finish(string $tssId, string $transactionId, array $data): array
    {
        try {
            $schema = $this->buildTransactionSchema($data);

            $response = $this->client->put(
                "/tss/{$tssId}/tx/{$transactionId}" . '?tx_revision=2',
                [
                    'state' => 'FINISHED',
                    'client_id' => $data['client_id'] ?? config('fiskaly.client.id'),
                    'schema' => $schema,
                ]
            );

            $result = [
                'transaction_id' => $transactionId,
                'number' => $response['number'] ?? null,
                'time_start' => $response['time_start'] ?? null,
                'time_end' => $response['time_end'] ?? now()->toIso8601String(),
                'signature' => [
                    'value' => $response['signature']['value'] ?? null,
                    'algorithm' => $response['signature']['algorithm'] ?? null,
                    'counter' => $response['signature']['counter'] ?? null,
                    'public_key' => $response['signature']['public_key'] ?? null,
                ],
                'qr_code_data' => $response['qr_code_data'] ?? null,
                'tss_serial_number' => $response['tss_serial_number'] ?? null,
                'client_serial_number' => $response['client_serial_number'] ?? null,
                'state' => 'FINISHED',
                'schema' => $response['schema'] ?? null,
                'revision' => $response['revision'] ?? null,
                'build_schema' => $schema,
            ];

            // Store transaction in database
            // $this->storeTransactionData($tssId, $result, $schema);

            return $result;

        } catch (\Exception $e) {
            throw new FiskalyException("Failed to finish transaction: " . $e->getMessage());
        }
    }

    /**
     * Store transaction data in database
     */
    protected function storeTransactionData(string $tssId, array $result, array $schema, ?int $invoiceId = null): void
    {
        try {
            DB::table('fiskaly_transactions')->updateOrInsert(
                ['transaction_id' => $result['transaction_id']],
                [
                    'transaction_id'    => $result['transaction_id'],
                    'tss_id'            => $tssId,
                    'client_id'         => config('fiskaly.client.id'),
                    'invoice_id'        => $invoiceId,
                    'transaction_number' => $result['number'],
                    'state'             => $result['state'],
                    'time_start'        => $this->normalizeFiskalyTime($result['time_start']) ,
                    'time_end'          => $this->normalizeFiskalyTime( $result['time_end']),
                    'signature'         => json_encode($result['signature']),
                    'qr_code_data'      => $result['qr_code_data'],
                    'tss_serial_number' => $result['tss_serial_number'],
                    'client_serial_number' => $result['client_serial_number'],
                    'schema_data'       => json_encode($schema),
                    'metadata'          => json_encode([
                        'stored_at' => now()->toIso8601String(),
                        'has_invoice' => !empty($invoiceId),
                    ]),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );


            Log::info('[Fiskaly] Transaction data stored', [
                'transaction_id' => $result['transaction_id'],
                'transaction_number' => $result['number'],
                'invoice_id' => $invoiceId,
            ]);

        } catch (\Exception $e) {
            Log::error('[Fiskaly] Failed to store transaction data', [
                'transaction_id' => $result['transaction_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel transaction
     */
    public function cancel(string $tssId, string $transactionId): array
    {
        try {
            $response = $this->client->put(
                "/tss/{$tssId}/tx/{$transactionId}",
                [
                    'state' => 'CANCELLED',
                ]
            );

            return [
                'transaction_id' => $transactionId,
                'state' => 'CANCELLED',
                'cancelled_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to cancel transaction: " . $e->getMessage());
        }
    }

    /**
     * Get transaction details
     */
    public function get(string $tssId, string $transactionId): array
    {
        try {
            return $this->client->get("/tss/{$tssId}/tx/{$transactionId}");
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to get transaction: " . $e->getMessage());
        }
    }

    /**
     * List transactions
     */
    public function list(string $tssId, array $filters = []): array
    {
        try {
            $response = $this->client->get("/tss/{$tssId}/tx", $filters);
            return $response['data'] ?? [];
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to list transactions: " . $e->getMessage());
        }
    }

    /**
     * Build transaction schema according to KassenSichV requirements
     *
     * This creates the AEAO (Anwendungserlass zur Abgabenordnung) compliant schema
     */
    protected function  buildTransactionSchema(array $data): array
    {
        $schema = [
            'standard_v1' => [
                'receipt' => [
                    'receipt_type' => $data['receipt_type'] ?? 'RECEIPT', // RECEIPT, INVOICE, etc.
                    'amounts_per_vat_rate' => $this->buildVatRates($data['items'] ?? []),
                    // 'amounts_per_vat_rate' => $this->buildVatRatesFromInvoice($data['items'] ?? []),
                    'amounts_per_payment_type' => $this->buildPaymentTypes($data['payments'] ?? []),
                ]
            ]
        ];

        return $schema;
    }

    /**
     * Build VAT rates structure
     */
    protected function buildVatRates(array $items): array
    {
        // $vatGroups = [];

        // foreach ($items as $item) {
        //     $vatRate = $item['vat_rate'] ?? config('fiskaly.tax.rates.standard');
        //     $amount = $item['amount'] ?? 0;

        //     $key = number_format($vatRate, 2);

        //     if (!isset($vatGroups[$key])) {
        //         $vatGroups[$key] = [
        //             'vat_rate' =>(string) $vatRate,
        //             'amount' => 0,
        //         ];
        //     }

        //     $vatGroups[$key]['amount'] += $amount;
        // }

        // return array_values($vatGroups);

        $groups = [];

        foreach ($items as $item) {
            /** @var \App\Models\InvoiceItem $item */
            $rate = rtrim(number_format((float) ($item->tax_rate ?? config('fiskaly.tax.rates.standard')), 2, '.', ''), '0');
            $rate = rtrim($rate, '.');
            $gross = number_format((float) ($item->total_amount ?? 0), 2, '.', ''); // gross line total

            if (!isset($groups[$rate])) {
                $groups[$rate] = ['vat_rate' => $rate, 'amount' => '0'];
            }

            $groups[$rate]['amount'] = bcadd($groups[$rate]['amount'], $gross, 2);
        }

        return array_values($groups);
    }

    /**
     * Build payment types structure
     */
    protected function buildPaymentTypes(array $payments, string $currency = 'EUR'): array
    {
        $groups = [];

        foreach ($payments as $payment) {
            /** @var \App\Models\Payment $payment */



            $type = $this->mapPaymentType($payment['type']);

            $amount = number_format((float) ($payment['amount'] ?? 0), 2, '.', '');



            if (!isset($groups[$type])) {
                $groups[$type] = [
                    'payment_type' => $type,
                    'amount' => '0.00',
                    'currency_code' => $currency,
                ];
            }

            $groups[$type]['amount'] = bcadd($groups[$type]['amount'], $amount, 2);
        }

        // 7. تصفية القيم الصفرية (اختياري - حسب متطلبات Fiskaly)
        $groups = array_filter($groups, fn($g) => bccomp($g['amount'], '0.00', 2) !== 0);

        // 8. تحويل إلى 5 أرقام عشرية كما يتطلب Fiskaly
        // foreach ($groups as &$group) {
        //     $group['amount'] = number_format((float) $group['amount'], 5, '.', '');
        // }

        return array_values($groups);
    }

    /**
     * Map payment type to Fiskaly format
     */
    protected function mapPaymentType(string $type): string
    {
        return match (strtoupper($type)) {
            'CASH' => 'CASH',
            default => 'NON_CASH',
        };
    }

    /**
     * Process a complete transaction (start, update, finish in one call)
     *
     * This is a convenience method for simple transactions
     */
    public function process(string $tssId, string $clientId, array $data): array
    {
        try {
            // Start transaction
            $transaction = $this->start($tssId, $clientId, $data);
            $transactionId = $transaction['transaction_id'];

            // Finish with data immediately
            return $this->finish($tssId, $transactionId, $data);

        } catch (\Exception $e) {
            throw new FiskalyException("Failed to process transaction: " . $e->getMessage());
        }
    }

    /**
     * Create transaction from invoice
     */
    public function createFromInvoice(\App\Models\Invoice $invoice): array
    {
        $tssId = config('fiskaly.tss.id');
        $clientId = config('fiskaly.client.id');

        // Build items array
        $items = [];
        // foreach ($invoice->items as $item) {
        //     $items[] = [
        //         'name' => $item->itemable?->name ?? 'Service',
        //         'amount' => (float) $item->unit_price,
        //         'vat_rate' => (float) $invoice->tax_rate,
        //     ];
        // }
        $rate = rtrim(number_format((float) ($invoice->tax_rate ?? config('fiskaly.tax.rates.standard')), 2, '.', ''), '0');
        $rate = rtrim($rate, '.');
        $items = [
            'name' => 'Invoice #' . $invoice->invoice_number,
            'amount' => (float) $invoice->total_amount,
            'vat_rate' => (float) $rate,
        ];

        // Build payments array
        $payments = [];
        foreach ($invoice->payments as $payment) {
            $payments[] = [
                'type' => $payment->payment_method ?? 'CASH',
                'amount' => (float) $payment->amount,
            ];
        }

        // If no payments yet (draft invoice), assume cash
        if (empty($payments)) {
            $payments[] = [
                'type' => 'CASH',
                'amount' => (float) $invoice->total_amount,
            ];
        }

        $data = [
            'receipt_type' => 'RECEIPT',
            'items' => $items,
            'payments' => $payments,
            'client_id' => $clientId,
        ];

        // Create transaction with invoice link
        $transactionId = Str::uuid()->toString();

        // Start transaction
        $transaction = $this->start($tssId, $clientId, ['transaction_id' => $transactionId]);

        // Finish and get signature
        $result = $this->finish($tssId, $transactionId, $data);

        // Store with invoice_id link
        // $schema = $this->buildTransactionSchema($data);
        $this->storeTransactionData($tssId, $result, $result['build_schema'], $invoice->id);
        $invoice->update([
            'segnture' => $result['signature']['value'] ?? null,
        ]);
        return $result;
    }

    public function normalizeFiskalyTime($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    // لو رقم أو نص رقمي => Unix seconds
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return Carbon::createFromTimestampUTC((int) $value)->toDateTimeString();
    }

    // لو ISO string
    try {
        return Carbon::parse($value)->utc()->toDateTimeString();
    } catch (\Throwable $e) {
        // كحل أخير: رجّع null لتفادي كسر التخزين
        return null;
    }
}
}
