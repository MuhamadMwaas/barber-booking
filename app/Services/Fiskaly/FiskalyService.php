<?php

namespace App\Services\Fiskaly;

use App\Models\Invoice;
use App\Exceptions\FiskalyException;
use Illuminate\Support\Facades\Log;

class FiskalyService
{
    protected FiskalyClient $client;
    protected TssService $tssService;
    protected ClientService $clientService;
    protected TransactionService $transactionService;
    protected ReceiptService $receiptService;

    public function __construct(
        FiskalyClient $client,
        TssService $tssService,
        ClientService $clientService,
        TransactionService $transactionService,
        ReceiptService $receiptService
    ) {
        $this->client = $client;
        $this->tssService = $tssService;
        $this->clientService = $clientService;
        $this->transactionService = $transactionService;
        $this->receiptService = $receiptService;
    }

    /**
     * Initialize Fiskaly (one-time setup)
     *
     * This method should be run once during initial setup to:
     * 1. Create organization (done via dashboard)
     * 2. Create TSS
     * 3. Create Client
     */
    public function initialize(): array
    {
        Log::info('[Fiskaly] Starting initialization');

        try {
            // Step 1: Authenticate
            $this->client->authenticate();
            Log::info('[Fiskaly] Authentication successful');

            // Step 2: Create TSS
            $tss = $this->tssService->create([
                'description' => config('fiskaly.tss.description'),
            ]);
            Log::info('[Fiskaly] TSS created', ['tss_id' => $tss['tss_id']]);

            if(!isset($tss['tss_id'])){
                throw new FiskalyException('TSS ID not returned from Fiskaly');
            }


            // CRITICAL: Save the PUK immediately!
            if (isset($tss['puk'])) {
                Log::warning('[Fiskaly] PUK received - SAVE THIS SECURELY: ' . $tss['puk']);
            }

            // Step 3: Initialize TSS
            $this->tssService->initialize($tss['tss_id']);
            Log::info('[Fiskaly] TSS initialized');

            // Step 3.5: Authenticate admin (required for creating client)
            // For new TSS, the default admin PIN is EMPTY (empty string)
            //             $this->tssService->authenticateAdmin($tss['tss_id'], env('FISKALY_TSS_ADMIN_PIN'));
            $adminPin = config('fiskaly.tss.admin_pin', '');
            try {
                $this->tssService->authenticateAdmin($tss['tss_id'], $adminPin);
                Log::info('[Fiskaly] Admin authenticated successfully');
            } catch (\Exception $e) {
                Log::warning('[Fiskaly] Admin authentication failed (this may be normal for new TSS)', [
                    'error' => $e->getMessage()
                ]);
                // Continue anyway - some operations may work without auth
            }

            // Step 4: Create Client
            $client = $this->clientService->createOrUpdate($tss['tss_id'], [
                'serial_number' => config('fiskaly.client.serial_number'),
            ]);
            Log::info('[Fiskaly] Client created', ['client_id' => $client['client_id']]);

            return [
                'success' => true,
                'tss' => $tss,
                'client' => $client,
                'message' => 'Fiskaly initialized successfully. SAVE THE PUK SECURELY!',
            ];

        } catch (\Exception $e) {
            Log::error('[Fiskaly] Initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new FiskalyException('Initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Process invoice payment and sign with TSE
     *
     * This is the main method you'll call when payment is completed
     */
    public function signInvoice(Invoice $invoice): array
    {
        $tssId = config('fiskaly.tss.id');
        $clientId = config('fiskaly.client.id');

        // Validate configuration
        if (empty($tssId) || empty($clientId)) {
            throw new FiskalyException('Fiskaly not configured. Run initialize() first.');
        }

        try {
            Log::info('[Fiskaly] Signing invoice', ['invoice_id' => $invoice->id]);

            // Check if Fiskaly is available
            if (!$this->client->isAvailable() && config('fiskaly.offline_mode.enabled')) {
                Log::warning('[Fiskaly] Service unavailable, processing in offline mode');
                return $this->processOfflineInvoice($invoice);
            }

            // Create and finish transaction
            $transaction = $this->transactionService->createFromInvoice($invoice);

            // Store Fiskaly data in invoice
            $this->storeTransactionData($invoice, $transaction);

            Log::info('[Fiskaly] Invoice signed successfully', [
                'invoice_id' => $invoice->id,
                'transaction_id' => $transaction['transaction_id'],
            ]);
            $invoice->update(['segnture' => $transaction['fiskaly_signature']??null]);
            $invoice->refresh();
            return [
                'success' => true,
                'transaction' => $transaction,
                'invoice_id' => $invoice->id,
            ];

        } catch (\Exception $e) {
            Log::error('[Fiskaly] Failed to sign invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            // If offline mode is enabled, process offline
            if (config('fiskaly.offline_mode.enabled')) {
                Log::warning('[Fiskaly] Processing invoice in offline mode due to error');
                return $this->processOfflineInvoice($invoice);
            }
            $invoice->update(['signature_missing_reason' =>'Failed to sign invoice: ' . $e->getMessage()]);
            throw new FiskalyException('Failed to sign invoice: ' . $e->getMessage());
        }
    }

    /**
     * Process invoice in offline mode
     */
    protected function processOfflineInvoice(Invoice $invoice): array
    {
        // Store offline indicator
        $invoice->update([
            'invoice_data' => array_merge($invoice->invoice_data ?? [], [
                'fiskaly_status' => 'offline',
                'offline_timestamp' => now()->toIso8601String(),
            ])
        ]);

        return [
            'success' => true,
            'offline' => true,
            'invoice_id' => $invoice->id,
            'message' => 'Invoice processed in offline mode',
        ];
    }

    /**
     * Store transaction data in invoice
     */
    protected function storeTransactionData(Invoice $invoice, array $transaction): void
    {
        $invoice->update([
            'invoice_data' => array_merge($invoice->invoice_data ?? [], [
                'fiskaly_transaction_id' => $transaction['transaction_id'],
                'fiskaly_transaction_number' => $transaction['number'],
                'fiskaly_signature' => $transaction['signature'],
                'fiskaly_qr_code' => $transaction['qr_code_data'] ?? null,
                'fiskaly_tss_serial' => $transaction['tss_serial_number'] ?? null,
                'fiskaly_client_serial' => $transaction['client_serial_number'] ?? null,
                'fiskaly_time_start' => $transaction['time_start'] ?? null,
                'fiskaly_time_end' => $transaction['time_end'] ?? null,
                'fiskaly_status' => 'signed',
            ])
        ]);
    }

    /**
     * Generate and return signed receipt
     */
    public function generateReceipt(Invoice $invoice): string
    {
        $fiskalyData = $this->getFiskalyDataFromInvoice($invoice);
        return $this->receiptService->generateForInvoice($invoice, $fiskalyData);
    }

    /**
     * Print signed receipt
     */
    public function printReceipt(Invoice $invoice): bool
    {
        $fiskalyData = $this->getFiskalyDataFromInvoice($invoice);
        return $this->receiptService->print($invoice, $fiskalyData);
    }

    /**
     * Get Fiskaly data from invoice
     */
    protected function getFiskalyDataFromInvoice(Invoice $invoice): ?array
    {
        $data = $invoice->invoice_data ?? [];

        if (($data['fiskaly_status'] ?? null) === 'offline') {
            return null; // Offline receipt
        }

        if (!isset($data['fiskaly_transaction_id'])) {
            return null;
        }

        return [
            'transaction_id' => $data['fiskaly_transaction_id'],
            'number' => $data['fiskaly_transaction_number'] ?? null,
            'signature' => $data['fiskaly_signature'] ?? null,
            'qr_code_data' => $data['fiskaly_qr_code'] ?? null,
            'tss_serial_number' => $data['fiskaly_tss_serial'] ?? null,
            'client_serial_number' => $data['fiskaly_client_serial'] ?? null,
            'time_start' => $data['fiskaly_time_start'] ?? null,
            'time_end' => $data['fiskaly_time_end'] ?? null,
        ];
    }

    /**
     * Validate system configuration
     */
    public function validateConfiguration(): array
    {
        $issues = [];

        // Check API credentials
        if (empty(config('fiskaly.api_key')) || empty(config('fiskaly.api_secret'))) {
            $issues[] = 'API credentials not configured';
        }

        // Check TSS configuration
        if (empty(config('fiskaly.tss.id'))) {
            $issues[] = 'TSS ID not configured';
        }

        // Check Client configuration
        if (empty(config('fiskaly.client.id'))) {
            $issues[] = 'Client ID not configured';
        }

        // Try to authenticate
        try {
            $this->client->authenticate();
        } catch (\Exception $e) {
            $issues[] = 'Authentication failed: ' . $e->getMessage();
        }

        // Check if TSS is accessible
        if (!empty(config('fiskaly.tss.id'))) {
            try {
                $state = $this->tssService->checkState(config('fiskaly.tss.id'));
                if ($state !== 'INITIALIZED') {
                    $issues[] = "TSS state is {$state}, expected INITIALIZED";
                }
            } catch (\Exception $e) {
                $issues[] = 'TSS not accessible: ' . $e->getMessage();
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'Configuration valid' : 'Configuration has issues',
        ];
    }

    /**
     * Get system status
     */
    public function getStatus(): array
    {
        return [
            'fiskaly_available' => $this->client->isAvailable(),
            'authenticated' => $this->client->getToken() !== null,
            'configuration' => $this->validateConfiguration(),
            'environment' => config('fiskaly.environment'),
        ];
    }

    /**
     * Export data for tax authorities (DSFinV-K)
     */
    public function exportForTaxAuthorities(
        string $startDate,
        string $endDate,
        ?string $clientId = null
    ): array {
        $tssId = config('fiskaly.tss.id');

        try {
            return $this->tssService->export($tssId, [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'client_id' => $clientId,
            ]);
        } catch (\Exception $e) {
            throw new FiskalyException('Export failed: ' . $e->getMessage());
        }
    }
}
