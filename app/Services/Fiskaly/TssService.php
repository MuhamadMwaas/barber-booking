<?php

namespace App\Services\Fiskaly;

use App\Exceptions\FiskalyException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TssService
{
    protected FiskalyClient $client;

    public function __construct(FiskalyClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new TSS (Technical Security System)
     *
     * IMPORTANT: The PUK (Personal Unblocking Key) is returned ONLY ONCE!
     * You MUST store it securely for future use.
     */
    public function create(array $data = []): array
    {
        $tssId = $data['tss_id'] ?? Str::uuid()->toString();

        $payload = [
            'description' => $data['description'] ?? config('fiskaly.tss.description'),
            // 'state' => 'INITIALIZED',
        ];

        try {
            $response = $this->client->put("/tss/{$tssId}", $payload);

            // Store ALL TSS data in database
            $this->storeTssData(
                tssId: $tssId,
                puk: $response['puk'] ?? $response['public_key'] ?? null,
                serialNumber: $response['serial_number'] ?? null,
                certificate: $response['certificate'] ?? null,
                state: $response['state'] ?? 'INITIALIZED',
                description: $data['description'] ?? config('fiskaly.tss.description'),
                other: $response,
            );

            return [
                'tss_id' => $tssId,
                'puk' => $response['puk'] ?? null,
                'serial_number' => $response['serial_number'] ?? null,
                'certificate' => $response['certificate'] ?? null,
                'state' => $response['state'] ?? 'INITIALIZED',
                'created_at' => $response['metadata']['created_at'] ?? now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to create TSS: " . $e->getMessage());
        }
    }

    /**
     * Store complete TSS data in database
     */
    protected function storeTssData(
        string $tssId,
        ?string $puk,
        ?string $serialNumber,
        ?string $certificate,
        string $state,
        ?string $description,
        ?array $other,
    ): void {
        try {
            // Store in .env file
            $this->updateEnvFile('FISKALY_TSS_ID', $tssId);
            if ($puk) {
                $this->updateEnvFile('FISKALY_TSS_PUK', $puk);
            }

            // Store in database with ALL information
            DB::table('fiskaly_tss')->updateOrInsert(
                ['tss_id' => $tssId],
                [
                    'tss_id' => $tssId,
                    'puk' => $puk ? encrypt($puk) : null,
                    'serial_number' => $serialNumber,
                    'certificate' => $certificate,
                    'state' => $state,
                    'description' => $description,
                    'metadata' => json_encode([
                        'created_via' => 'api',
                        'stored_at' => now()->toIso8601String(),
                        'public_key'=>$other['public_key']??null,
                        'responce'=>$other

                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            Log::info('[Fiskaly] Complete TSS data stored', [
                'tss_id' => $tssId,
                'has_puk' => !empty($puk),
                'has_serial' => !empty($serialNumber),
                'has_certificate' => !empty($certificate),
                'state' => $state,
            ]);

        } catch (\Exception $e) {
            Log::error('[Fiskaly] Failed to store TSS data', [
                'tss_id' => $tssId,
                'error' => $e->getMessage(),
            ]);

            // Continue - at least we have it in .env
        }
    }

    /**
     * Get TSS details
     */
    public function get(string $tssId): array
    {
        try {
            return $this->client->get("/tss/{$tssId}");
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to get TSS: " . $e->getMessage());
        }
    }

    /**
     * List all TSS instances
     */
    public function list(): array
    {
        try {
            $response = $this->client->get('/tss');
            return $response['data'] ?? [];
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to list TSS: " . $e->getMessage());
        }
    }

    /**
     * Initialize TSS (change state to INITIALIZED)
     */
    public function initialize(string $tssId): array
    {
        try {
            return $this->client->patch("/tss/{$tssId}", [
                'state' => 'INITIALIZED'
            ]);
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to initialize TSS: " . $e->getMessage());
        }
    }

    /**
     * Disable TSS (for decommissioning)
     */
    public function disable(string $tssId): array
    {
        try {
            return $this->client->patch("/tss/{$tssId}", [
                'state' => 'DISABLED'
            ]);
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to disable TSS: " . $e->getMessage());
        }
    }

    /**
     * Authenticate as admin (required for certain operations)
     *
     * Note: This is different from the main API authentication.
     * It's used for TSS-specific administrative operations.
     */
    public function authenticateAdmin(string $tssId, string $adminPin): array
    {
        try {
            return $this->client->post("/tss/{$tssId}/admin/auth", [
                'admin_pin' => $adminPin
            ]);
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to authenticate admin: " . $e->getMessage());
        }
    }

    /**
     * Change admin PIN
     */
    public function changeAdminPin(string $tssId, string $oldPin, string $newPin): array
    {
        try {
            return $this->client->patch("/tss/{$tssId}/admin", [
                'admin_pin' => $oldPin,
                'new_admin_pin' => $newPin
            ]);
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to change admin PIN: " . $e->getMessage());
        }
    }

    /**
     * Unblock TSS with PUK (if PIN is blocked)
     */
    public function unblockWithPuk(string $tssId, string $puk, string $newPin): array
    {
        try {
            return $this->client->patch("/tss/{$tssId}/admin", [
                'puk' => $puk,
                'new_admin_pin' => $newPin
            ]);
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to unblock TSS: " . $e->getMessage());
        }
    }

    /**
     * Export TSS data (for tax authorities)
     */
    public function export(string $tssId, array $options = []): array
    {
        $params = array_merge([
            'start_date' => $options['start_date'] ?? null,
            'end_date' => $options['end_date'] ?? null,
            'client_id' => $options['client_id'] ?? null,
        ], array_filter($options));

        try {
            return $this->client->get("/tss/{$tssId}/export", $params);
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to export TSS data: " . $e->getMessage());
        }
    }

    /**
     * Get TSS certificate
     */
    public function getCertificate(string $tssId): string
    {
        try {
            $response = $this->client->get("/tss/{$tssId}");
            return $response['certificate'] ?? '';
        } catch (\Exception $e) {
            throw new FiskalyException("Failed to get certificate: " . $e->getMessage());
        }
    }

    /**
     * Check TSS state
     */
    public function checkState(string $tssId): string
    {
        try {
            $response = $this->client->get("/tss/{$tssId}");
            return $response['state'] ?? 'UNKNOWN';
        } catch (\Exception $e) {
            return 'UNKNOWN';
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

        // Check if key exists
        if (strpos($content, "{$key}=") !== false) {
            // Update existing key
            $content = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $content
            );
        } else {
            // Add new key
            $content .= "\n{$key}={$value}\n";
        }

        file_put_contents($path, $content);
    }

    /**
     * Validate TSS configuration
     */
    public function validateConfiguration(): bool
    {
        $tssId = config('fiskaly.tss.id');

        if (empty($tssId)) {
            throw new FiskalyException('TSS ID not configured');
        }

        try {
            $tss = $this->get($tssId);
            return $tss['state'] === 'INITIALIZED';
        } catch (\Exception $e) {
            return false;
        }
    }
}
