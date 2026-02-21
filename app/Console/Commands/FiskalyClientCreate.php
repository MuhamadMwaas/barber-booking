<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fiskaly\ClientService;

class FiskalyClientCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fiskaly:client-create
                            {tss_id : The TSS ID to attach the client to}
                            {--client_id= : Optional client ID (UUID). If omitted, service generates one}
                            {--serial= : Optional serial number override}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update a Fiskaly client for a TSS and print detailed result';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tssId = (string) $this->argument('tss_id');
        // $clientId = $this->option('client_id');
        $clientId = env('FISKALY_CLIENT_ID') ?? $this->option('client_id');
        $serial = $this->option('serial');

        $payload = [];
        if (!empty($clientId)) {
            $payload['client_id'] = $clientId;
        }
        if (!empty($serial)) {
            $payload['serial_number'] = $serial;
        }

        $this->info('Creating or updating Fiskaly client...');
        $this->line('TSS ID: ' . $tssId);
        $this->line('Client ID: ' . ($clientId ?: '(auto)'));
        $this->line('Serial: ' . ($serial ?: '(config/default)'));
        $this->newLine();

        try {
            $service = app(ClientService::class);
            $result = $service->createOrUpdate($tssId, $payload);

            $this->info('Client created/updated successfully');
            $this->newLine();

            $this->line('Result (detailed):');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to create/update client: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
