<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\TssService;
use Illuminate\Support\Facades\DB;

class FiskalyUnblockPin extends Command
{
    protected $signature = 'fiskaly:unblock-pin
                            {--pin= : New admin PIN to set (default: empty string)}';

    protected $description = 'Unblock TSS admin PIN using PUK';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Fiskaly TSS - Unblock Admin PIN');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        // Get TSS ID from config
        $tssId = config('fiskaly.tss.id');
        if (empty($tssId)) {
            $this->error('❌ TSS ID not found in configuration!');
            return self::FAILURE;
        }

        $this->info("TSS ID: {$tssId}");
        $this->newLine();

        // Try to get PUK from multiple sources (in order of priority)

        // 1. First try from config (.env file)
        // $adminPuk = config('fiskaly.tss.puk');

        // 2. If not in config, try from database

           $tssRecord = DB::table('fiskaly_tss')
                ->where('tss_id', $tssId)
                ->first();
                $metadata = json_decode($tssRecord->metadata, true);
                $adminPuk = $metadata['responce']['admin_puk'] ??
                           $metadata['admin_puk'] ?? null;

        if (empty($adminPuk)) {
            $this->error('❌ Admin PUK not found!');
            $this->newLine();
            $this->warn('Checked locations:');
            $this->line('  1. .env file (FISKALY_TSS_PUK)');
            $this->line('  2. Database (fiskaly_tss.puk - encrypted)');
            $this->line('  3. Database metadata (public_key)');
            $this->newLine();
            $this->warn('The PUK was only shown once during TSS creation.');
            $this->warn('If you lost it, you need to create a new TSS.');
            return self::FAILURE;
        }

        // Show PUK source and preview
        $pukPreview = strlen($adminPuk) > 40
            ? substr($adminPuk, 0, 20) . '...' . substr($adminPuk, -10)
            : $adminPuk;

        $this->info("✓ Admin PUK found: {$pukPreview}");
        $this->line("  Length: " . strlen($adminPuk) . " characters");
        $this->line("  adminPuk: " . $adminPuk);
        $this->newLine();

        // Get new PIN from option or prompt
        $newPin = $this->option('pin');
        if (is_null($newPin)) {
            $this->warn('⚠️  Note: Fiskaly allows empty PIN for test environments');
            $newPin = $this->ask('Enter new admin PIN (press Enter for empty PIN)', '');
        }

        $this->newLine();
        $this->info('🔓 Unblocking TSS with PUK...');

        try {
            $tssService = app(TssService::class);

            $result = $tssService->unblockWithPuk($tssId, $adminPuk, $newPin);

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════');
            $this->info('  ✅ PIN Unblocked Successfully!');
            $this->info('═══════════════════════════════════════════════════');
            $this->newLine();

            if (!empty($newPin)) {
                $this->info("New PIN: {$newPin}");
                $this->warn('⚠️  Save this PIN securely!');

                // Save to .env
                $this->updateEnvFile('FISKALY_TSS_ADMIN_PIN', $newPin);
                $this->updateAdminPinDatabase($tssId, $newPin);
                $this->info('✓ PIN saved to .env file');
            } else {
                $this->info('PIN is now empty (no PIN required)');
            }

            $this->newLine();
            $this->info('You can now continue with the setup!');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Failed to unblock PIN');
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

    public function updateAdminPinDatabase(string $tssId, string $newPin): void
    {
        DB::table('fiskaly_tss')
            ->where('tss_id', $tssId)
            ->update(['admin_pin' => encrypt($newPin)]);

    }
}
