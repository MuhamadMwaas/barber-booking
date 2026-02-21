<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\FiskalyClient;
use Illuminate\Support\Facades\DB;

class FiskalyDeleteTss extends Command
{
    protected $signature = 'fiskaly:delete-tss
                            {--force : Skip confirmation}';

    protected $description = 'Delete current TSS (TEST environment only)';

    public function handle(): int
    {
        $this->warn('═══════════════════════════════════════════════════');
        $this->warn('  ⚠️  Delete TSS - TEST Environment Only');
        $this->warn('═══════════════════════════════════════════════════');
        $this->newLine();

        // Check environment
        if (config('fiskaly.environment') !== 'test') {
            $this->error('❌ This command only works in TEST environment!');
            $this->error('Current environment: ' . config('fiskaly.environment'));
            return self::FAILURE;
        }

        // Get TSS ID
        $tssId = config('fiskaly.tss.id');
        if (empty($tssId)) {
            $this->error('❌ No TSS ID found in configuration!');
            return self::FAILURE;
        }

        $this->info("TSS ID to delete: {$tssId}");
        $this->newLine();

        // Confirmation
        if (!$this->option('force')) {
            $this->warn('⚠️  This will permanently delete the TSS!');
            $this->warn('⚠️  All transactions and data will be lost!');
            $this->newLine();

            if (!$this->confirm('Are you sure you want to delete this TSS?')) {
                $this->info('Deletion cancelled.');
                return self::SUCCESS;
            }
        }

        try {
            $client = app(FiskalyClient::class);

            // Try to delete TSS
            $this->info('Deleting TSS...');
            $client->delete("/tss/{$tssId}");

            // Clear from database
            DB::table('fiskaly_tss')->where('tss_id', $tssId)->delete();
            DB::table('fiskaly_clients')->where('tss_id', $tssId)->delete();
            DB::table('fiskaly_transactions')->where('tss_id', $tssId)->delete();

            // Clear from .env
            $this->updateEnvFile('FISKALY_TSS_ID', '');
            $this->updateEnvFile('FISKALY_TSS_PUK', '');
            $this->updateEnvFile('FISKALY_CLIENT_ID', '');

            $this->newLine();
            $this->info('✅ TSS deleted successfully!');
            $this->newLine();
            $this->info('You can now run: php artisan fiskaly:setup');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Failed to delete TSS');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            $this->warn('Note: In TEST environment, TSS may auto-delete after 24-48 hours');
            $this->warn('You can also delete it manually from Fiskaly Dashboard:');
            $this->warn('https://dashboard.fiskaly.com');

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
