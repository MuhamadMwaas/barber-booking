<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\TssService;
use Illuminate\Support\Facades\DB;

class FiskalyInitializeTss extends Command
{
    protected $signature = 'fiskaly:initialize-tss
                            {tss_id? : TSS ID to initialize (optional, uses current from .env)}';

    protected $description = 'Initialize TSS (change state from CREATED to INITIALIZED)';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  Fiskaly - Initialize TSS');
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        // Get TSS ID from argument or config
        $tssId = $this->argument('tss_id') ?? config('fiskaly.tss.id');

        if (empty($tssId)) {
            $this->error('❌ TSS ID not found!');
            $this->warn('Please provide TSS ID or run fiskaly:setup first.');
            return self::FAILURE;
        }

        $this->info("TSS ID: {$tssId}");

        // Get current state from database
        $tssRecord = DB::table('fiskaly_tss')
            ->where('tss_id', $tssId)
            ->first();

        if ($tssRecord) {
            $this->line("Current State: {$tssRecord->state}");
        }

        $this->newLine();

        try {
            $tssService = app(TssService::class);

            $this->info('🔄 Initializing TSS...');

            // Initialize TSS (change state to INITIALIZED)
            $result = $tssService->initialize($tssId);

            $newState = $result['state'] ?? 'INITIALIZED';

            // Update state in database
            DB::table('fiskaly_tss')
                ->where('tss_id', $tssId)
                ->update([
                    'state' => $newState,
                    'updated_at' => now(),
                ]);

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════');
            $this->info('  ✅ TSS Initialized Successfully!');
            $this->info('═══════════════════════════════════════════════════');
            $this->newLine();

            $this->line("TSS ID: {$tssId}");
            $this->line("New State: {$newState}");
            $this->newLine();

            if ($newState === 'INITIALIZED') {
                $this->info('✅ TSS is now ready for creating clients and transactions!');
            } else {
                $this->warn("⚠️  State is '{$newState}', expected 'INITIALIZED'");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Failed to initialize TSS');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            $this->warn('Troubleshooting:');
            $this->line('1. Check that TSS exists in Fiskaly Dashboard');
            $this->line('2. Verify API credentials are correct');
            $this->line('3. Check that TSS is in CREATED state');

            return self::FAILURE;
        }
    }
}
