<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FiskalySetClient extends Command
{
    protected $signature = 'fiskaly:set-client
                            {client_id : The Client ID from Fiskaly Dashboard}
                            {--serial= : Client serial number (optional)}';

    protected $description = 'Save Client ID after creating it manually from Dashboard';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Fiskaly - Save Client Configuration');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        $clientId = $this->argument('client_id');
        $serialNumber = $this->option('serial') ?? config('fiskaly.client.serial_number', 'POS-BERLIN-STORE01-2026');
        $tssId = config('fiskaly.tss.id');

        // Validate UUID format
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($uuidPattern, $clientId)) {
            $this->error('❌ Invalid Client ID format!');
            $this->warn('Client ID must be a valid UUID (e.g., xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)');
            return self::FAILURE;
        }

        if (empty($tssId)) {
            $this->error('❌ TSS ID not found! Please run fiskaly:setup first.');
            return self::FAILURE;
        }

        $this->info("TSS ID: {$tssId}");
        $this->info("Client ID: {$clientId}");
        $this->info("Serial Number: {$serialNumber}");
        $this->newLine();

        try {
            // Save to database
            DB::table('fiskaly_clients')->updateOrInsert(
                ['client_id' => $clientId],
                [
                    'client_id' => $clientId,
                    'tss_id' => $tssId,
                    'serial_number' => $serialNumber,
                    'metadata' => json_encode([
                        'created_via' => 'manual_dashboard',
                        'stored_at' => now()->toIso8601String(),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->info('✓ Saved to database');

            // Save to .env
            $this->updateEnvFile('FISKALY_CLIENT_ID', $clientId);
            $this->info('✓ Saved to .env file');

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════');
            $this->info('  ✅ Fiskaly Setup Complete!');
            $this->info('═══════════════════════════════════════════════════');
            $this->newLine();

            $this->line('Your Fiskaly configuration:');
            $this->line("  TSS ID: {$tssId}");
            $this->line("  Client ID: {$clientId}");
            $this->line("  Serial Number: {$serialNumber}");
            $this->newLine();

            $this->info('🎉 Fiskaly is now ready to use!');
            $this->info('You can now sign invoices when payments are completed.');
            $this->newLine();

            $this->info('Test the setup with:');
            $this->line('  php artisan fiskaly:test');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to save client configuration');
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

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
}
