<?php

namespace App\Services\Fiskaly;

use App\Exceptions\FiskalyException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientService
{
    protected FiskalyClient $client;

    public function __construct(FiskalyClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create or update a client (cash register)
     */
    public function createOrUpdate(string $tssId, array $data = []): array
    {
        $clientId = $data['client_id'] ?? config('fiskaly.client.id') ?? null;
        if(is_null($clientId) || empty($clientId)){
            $clientId = Str::uuid()->toString();
        }
        $payload = [
            'serial_number' => $data['serial_number'] ?? config('fiskaly.client.serial_number'),
        ];

        try {
            $tssService = app(TssService::class);
            $tssService->authenticateAdmin($tssId, env('FISKALY_TSS_ADMIN_PIN', '') );

            $response = $this->client->put("/tss/{$tssId}/client/{$clientId}", $payload);

            $result = [
                'client_id' => $clientId,
                'tss_id' => $tssId,
                'serial_number' => $response['serial_number'] ?? $payload['serial_number'],
                'created_at' => $response['metadata']['created_at'] ?? now()->toIso8601String(),
            ];

            // Store in database
            $this->storeClientData($result,$response);

            // Store client_id in .env if not exists
            if (empty(config('fiskaly.client.id'))) {
                $this->updateEnvFile('FISKALY_CLIENT_ID', $clientId);
            }

            return $result;

        } catch (\Exception $e) {
            throw new FiskalyException("Failed to create/update client: " . $e->getMessage());
        }
    }

    /**
     * Store client data in database
     */
    protected function storeClientData(array $data,$response): void
    {
        try {
            DB::table('fiskaly_clients')->updateOrInsert(
                ['client_id' => $data['client_id']],
                [
                    'client_id' => $data['client_id'],
                    'tss_id' => $data['tss_id'],
                    'serial_number' => $data['serial_number'],
                    'metadata' => json_encode([
                        'created_via' => 'api',
                        'stored_at' => now()->toIso8601String(),
                        'id'=>$response['_id']??null,
                        'state'=>$response['state']??null,
                        '_type'=>$response['_type']??null,
                        '_version'=>$response['_version']??null,
                        '_env'=>$response['_env']??null,
                        "time_creation"=>$response['time_creation']
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            Log::info('[Fiskaly] Client data stored', [
                'client_id' => $data['client_id'],
                'tss_id' => $data['tss_id'],
            ]);

        } catch (\Exception $e) {
            Log::error('[Fiskaly] Failed to store client data', [
                'client_id' => $data['client_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update .env file with new value
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
     * Get client details
     */
    public function get(string $tssId, string $clientId): array
    {
        try {
            return $this->client->get("/tss/{$tssId}/client/{$clientId}");
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to get client: " . $e->getMessage());
        }
    }

    /**
     * List all clients for a TSS
     */
    public function list(string $tssId): array
    {
        try {
            $response = $this->client->get("/tss/{$tssId}/client");
            return $response['data'] ?? [];
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to list clients: " . $e->getMessage());
        }
    }

    /**
     * Delete a client
     *
     * Note: Deletion might not always be possible depending on transaction history
     */
    public function delete(string $tssId, string $clientId): bool
    {
        try {
            $this->client->delete("/tss/{$tssId}/client/{$clientId}");
            return true;
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to delete client: " . $e->getMessage());
        }
    }

    /**
     * Validate client configuration
     */
    public function validateConfiguration(string $tssId, string $clientId): bool
    {
        try {
            $client = $this->get($tssId, $clientId);
            return !empty($client['serial_number']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get or create default client
     */
    public function getOrCreateDefault(string $tssId): array
    {
        $clientId = config('fiskaly.client.id');

        if (empty($clientId)) {
            $clientId = 'client-' . Str::random(16);
        }

        try {
            // Try to get existing client
            return $this->get($tssId, $clientId);
        } catch (\Exception $e) {
            // Client doesn't exist, create it
            return $this->createOrUpdate($tssId, ['client_id' => $clientId]);
        }
    }

    /**
     * Update client serial number
     */
    public function updateSerialNumber(string $tssId, string $clientId, string $serialNumber): array
    {
        try {
            return $this->client->patch("/tss/{$tssId}/client/{$clientId}", [
                'serial_number' => $serialNumber
            ]);
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to update serial number: " . $e->getMessage());
        }
    }
}
