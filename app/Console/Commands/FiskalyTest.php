<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\FiskalyService;
use App\Services\Fiskaly\FiskalyClient;
use App\Services\Fiskaly\TssService;
use App\Services\Fiskaly\ClientService;
use App\Services\Fiskaly\TransactionService;

class FiskalyTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiskaly:test {--full : Run full test suite}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Fiskaly integration and connectivity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Fiskaly Integration Test');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $fullTest = $this->option('full');
        $passed = 0;
        $failed = 0;

        // Test 1: Configuration
        $this->info('🔍 Test 1: Configuration Check');
        if ($this->testConfiguration()) {
            $this->info('   ✓ Configuration is valid');
            $passed++;
        } else {
            $this->error('   ✗ Configuration has issues');
            $failed++;
        }
        $this->newLine();

        // Test 2: Authentication
        $this->info('🔍 Test 2: API Authentication');
        if ($this->testAuthentication()) {
            $this->info('   ✓ Authentication successful');
            $passed++;
        } else {
            $this->error('   ✗ Authentication failed');
            $failed++;
        }
        $this->newLine();

        // Test 3: TSS Status
        $this->info('🔍 Test 3: TSS Status Check');
        if ($this->testTssStatus()) {
            $this->info('   ✓ TSS is accessible and initialized');
            $passed++;
        } else {
            $this->error('   ✗ TSS check failed');
            $failed++;
        }
        $this->newLine();

        // Test 4: Client Status
        $this->info('🔍 Test 4: Client Status Check');
        if ($this->testClientStatus()) {
            $this->info('   ✓ Client is properly configured');
            $passed++;
        } else {
            $this->error('   ✗ Client check failed');
            $failed++;
        }
        $this->newLine();

        if ($fullTest) {
            // Test 5: Create Test Transaction
            $this->info('🔍 Test 5: Test Transaction');
            if ($this->testTransaction()) {
                $this->info('   ✓ Test transaction successful');
                $passed++;
            } else {
                $this->error('   ✗ Test transaction failed');
                $failed++;
            }
            $this->newLine();
        }

        // Summary
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Test Results');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $total = $passed + $failed;
        $this->table(
            ['Status', 'Count'],
            [
                ['Passed', "<fg=green>{$passed}</>"],
                ['Failed', $failed > 0 ? "<fg=red>{$failed}</>" : $failed],
                ['Total', $total],
            ]
        );

        if ($failed === 0) {
            $this->newLine();
            $this->info('🎉 All tests passed! Fiskaly integration is working correctly.');
            return self::SUCCESS;
        } else {
            $this->newLine();
            $this->error('⚠️  Some tests failed. Please check the errors above.');
            $this->newLine();
            $this->warn('Common solutions:');
            $this->line('1. Run "php artisan fiskaly:setup" if not initialized');
            $this->line('2. Check your .env file for correct credentials');
            $this->line('3. Ensure internet connectivity');
            $this->line('4. Check Fiskaly service status at status.fiskaly.com');
            return self::FAILURE;
        }
    }

    /**
     * Test configuration
     */
    protected function testConfiguration(): bool
    {
        try {
            $fiskalyService = app(FiskalyService::class);
            $validation = $fiskalyService->validateConfiguration();

            if (!$validation['valid']) {
                $this->warn('   Issues found:');
                foreach ($validation['issues'] as $issue) {
                    $this->line('   - ' . $issue);
                }
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->error('   Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test authentication
     */
    protected function testAuthentication(): bool
    {
        try {
            $client = app(FiskalyClient::class);
            $token = $client->authenticate();

            if (empty($token)) {
                $this->warn('   No token received');
                return false;
            }

            $this->line('   Token: ' . substr($token, 0, 20) . '...');
            return true;
        } catch (\Exception $e) {
            $this->error('   Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test TSS status
     */
    protected function testTssStatus(): bool
    {
        try {
            $tssService = app(TssService::class);
            $tssId = config('fiskaly.tss.id');

            if (empty($tssId)) {
                $this->warn('   TSS ID not configured');
                return false;
            }

            $tss = $tssService->get($tssId);
            $state = $tss['state'] ?? 'UNKNOWN';

            $this->line('   TSS ID: ' . $tssId);
            $this->line('   State: ' . $state);
            $this->line('   Serial: ' . ($tss['serial_number'] ?? 'N/A'));

            return $state === 'INITIALIZED';
        } catch (\Exception $e) {
            $this->error('   Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test client status
     */
    protected function testClientStatus(): bool
    {
        try {
            $clientService = app(ClientService::class);
            $tssId = config('fiskaly.tss.id');
            $clientId = config('fiskaly.client.id');

            if (empty($clientId)) {
                $this->warn('   Client ID not configured');
                return false;
            }

            $client = $clientService->get($tssId, $clientId);

            $this->line('   Client ID: ' . $clientId);
            $this->line('   Serial: ' . ($client['serial_number'] ?? 'N/A'));

            return !empty($client['serial_number']);
        } catch (\Exception $e) {
            $this->error('   Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test transaction
     */
    protected function testTransaction(): bool
    {
        try {
            $transactionService = app(TransactionService::class);
            $tssId = config('fiskaly.tss.id');
            $clientId = config('fiskaly.client.id');

            $this->line('   Creating test transaction...');

            // Start transaction
            $transaction = $transactionService->start($tssId, $clientId);
            $transactionId = $transaction['transaction_id'];

            $this->line('   Transaction started: ' . $transactionId);

            // Finish transaction with test data
            $result = $transactionService->finish($tssId, $transactionId, [
                'client_id' => $clientId,
                'items' => [
                    [
                        'name' => 'Test Service',
                        'amount' => 10.00,
                        'vat_rate' => 19,
                    ]
                ],
                'payments' => [
                    [
                        'type' => 'CASH',
                        'amount' => 10.00,
                    ]
                ],
            ]);

            $this->line('   Transaction finished');
            $this->line('   Transaction Number: ' . ($result['number'] ?? 'N/A'));
            $this->line('   Signature: ' . substr($result['signature']['value'] ?? 'N/A', 0, 32) . '...');

            return !empty($result['signature']);
        } catch (\Exception $e) {
            $this->error('   Error: ' . $e->getMessage());
            return false;
        }
    }
}
