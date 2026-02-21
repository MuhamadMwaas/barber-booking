<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\TssService;
use Illuminate\Support\Facades\DB;

class FiskalyTssAdminAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiskaly:tss-admin-auth
                            {tss_id : The TSS ID to authenticate}
                            {pin : The admin PIN for the TSS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Authenticate TSS admin PIN and store it in fiskaly_tss metadata';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tssId = (string) $this->argument('tss_id');
        $pin = (string) $this->argument('pin');

        $this->info('Authenticating TSS admin PIN...');
        $this->line('TSS ID: ' . $tssId);
        $this->newLine();

        try {
            $service = app(TssService::class);
            $result = $service->authenticateAdmin($tssId, $pin);

            $this->info('Admin authentication successful');
            $this->newLine();
            $this->line('Result (detailed):');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();

            $row = DB::table('fiskaly_tss')->where('tss_id', $tssId)->first();
            if ($row) {
                $metadata = [];
                if (!empty($row->metadata)) {
                    $decoded = json_decode($row->metadata, true);
                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                $metadata['admin_pin'] = encrypt($pin);
                $metadata['admin_pin_set_at'] = now()->toIso8601String();

                DB::table('fiskaly_tss')
                    ->where('tss_id', $tssId)
                    ->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);

                $this->info('Stored admin PIN in fiskaly_tss metadata (encrypted)');
            } else {
                $this->warn('No fiskaly_tss row found for this TSS ID. Metadata not updated.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Authentication failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
