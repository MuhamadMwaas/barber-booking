<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\FiskalyService;
use App\Services\Fiskaly\TssService;
use App\Services\Fiskaly\ClientService;

class FiskalySetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiskaly:setup
                            {--force : Force re-initialization even if already configured}
                            {--test : Use test environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize Fiskaly TSE system for the first time';

    protected FiskalyService $fiskalyService;
    protected TssService $tssService;
    protected ClientService $clientService;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Fiskaly TSE Setup - KassenSichV Compliance');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        // Check if already configured
        if (!$this->option('force') && $this->isAlreadyConfigured()) {
            $this->warn('Fiskaly is already configured!');
            $this->info('TSS ID: ' . config('fiskaly.tss.id'));
            $this->info('Client ID: ' . config('fiskaly.client.id'));
            $this->newLine();

            if (!$this->confirm('Do you want to re-initialize? This will create a NEW TSS!')) {
                $this->info('Setup cancelled.');
                return self::SUCCESS;
            }
        }

        // Confirm credentials
        $this->info('Checking API credentials...');
        if (!$this->checkCredentials()) {
            $this->error('❌ API credentials not configured!');
            $this->warn('Please set FISKALY_API_KEY and FISKALY_API_SECRET in your .env file');
            return self::FAILURE;
        }
        $this->info('✓ API credentials found');
        $this->newLine();

        // Initialize
        $this->info('🚀 Initializing Fiskaly...');
        $this->newLine();

        try {
            $this->fiskalyService = app(FiskalyService::class);
            $this->tssService = app(TssService::class);
            $this->clientService = app(ClientService::class);

            $result = $this->fiskalyService->initialize();

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════');
            $this->info('  ✅ Fiskaly Initialized Successfully!');
            $this->info('═══════════════════════════════════════════════════');
            $this->newLine();

            // Display results
            $this->displayResults($result);

            // Save to .env
            $this->info('💾 Saving configuration to .env file...');
            $this->saveToEnv($result);
            $this->info('✓ Configuration saved');
            $this->newLine();

            // Final instructions
            $this->displayFinalInstructions();

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('═══════════════════════════════════════════════════');
            $this->error('  ❌ Setup Failed');
            $this->error('═══════════════════════════════════════════════════');
            $this->newLine();
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            $this->warn('Troubleshooting tips:');
            $this->line('1. Check your API credentials in .env file');
            $this->line('2. Ensure you have internet connectivity');
            $this->line('3. Verify your Fiskaly account is active');
            $this->line('4. Check the logs for more details');

            return self::FAILURE;
        }
    }

    /**
     * Check if Fiskaly is already configured
     */
    protected function isAlreadyConfigured(): bool
    {
        return !empty(config('fiskaly.tss.id')) && !empty(config('fiskaly.client.id'));
    }

    /**
     * Check if API credentials are configured
     */
    protected function checkCredentials(): bool
    {
        return !empty(config('fiskaly.api_key')) && !empty(config('fiskaly.api_secret'));
    }

    /**
     * Display setup results
     */
    protected function displayResults(array $result): void
    {
        $this->table(
            ['Component', 'Value'],
            [
                ['TSS ID', $result['tss']['tss_id']],
                ['TSS Serial Number', $result['tss']['serial_number'] ?? 'N/A'],
                ['TSS State', $result['tss']['state']],
                ['Client ID', $result['client']['client_id']],
                ['Client Serial Number', $result['client']['serial_number']],
            ]
        );

        $this->newLine();

        // PUK Warning
        if (isset($result['tss']['puk'])) {
            $this->warn('⚠️  CRITICAL: PUK (Personal Unblocking Key)');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line('PUK: ' . $result['tss']['puk']);
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();
            $this->error('⚠️  SAVE THIS PUK IMMEDIATELY!');
            $this->warn('You will NEVER see this PUK again!');
            $this->warn('You need this PUK if your TSS PIN gets blocked.');
            $this->newLine();
        }
    }

    /**
     * Save configuration to .env file
     */
    protected function saveToEnv(array $result): void
    {
        $this->updateEnvFile('FISKALY_TSS_ID', $result['tss']['tss_id']);
        $this->updateEnvFile('FISKALY_CLIENT_ID', $result['client']['client_id']);

        if (isset($result['tss']['puk'])) {
            $this->updateEnvFile('FISKALY_TSS_PUK', $result['tss']['puk']);
        }
    }

    /**
     * Update .env file
     */
    protected function updateEnvFile(string $key, string $value): void
    {
        $path = base_path('.env');

        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        if (strpos($content, "{$key}=") !== false) {
            $content = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $content
            );
        } else {
            $content .= "\n{$key}={$value}\n";
        }

        file_put_contents($path, $content);
    }

    /**
     * Display final instructions
     */
    protected function displayFinalInstructions(): void
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  📝 Next Steps');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $this->line('1. ✓ Fiskaly is now initialized and ready to use');
        $this->line('2. ✓ Configuration has been saved to .env file');
        $this->line('3. ⚠️  BACKUP your PUK in a secure location');
        $this->line('4. 🧪 Test the integration with: php artisan fiskaly:test');
        $this->line('5. 📄 Start signing invoices when payments are completed');
        $this->newLine();

        $this->info('Usage example:');
        $this->line('   $fiskalyService = app(FiskalyService::class);');
        $this->line('   $result = $fiskalyService->signInvoice($invoice);');
        $this->newLine();

        $this->info('For more information, visit:');
        $this->line('   https://developer.fiskaly.com');
        $this->newLine();
    }
}
