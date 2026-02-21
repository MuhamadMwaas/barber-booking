<?php

namespace App\Services\Fiskaly;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\FiskalyException;

class FiskalyClient
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $baseUrl;
    protected ?string $token = null;
    protected bool $loggingEnabled;

    public function __construct()
    {
        $this->apiKey = config('fiskaly.api_key');
        $this->apiSecret = config('fiskaly.api_secret');
        $this->baseUrl = config('fiskaly.base_url');
        $this->loggingEnabled = config('fiskaly.logging.enabled', true);

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new FiskalyException('Fiskaly API credentials not configured');
        }
    }

    /**
     * Authenticate and get JWT token
     */
    public function authenticate(): string
    {
        // Check if we have a cached token
        $cachedToken = Cache::get(config('fiskaly.cache.token_key'));
        if ($cachedToken) {
            $this->token = $cachedToken;
            return $cachedToken;
        }

        $this->log('info', 'Authenticating with Fiskaly API');

        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/auth", [
                    'api_key' => $this->apiKey,
                    'api_secret' => $this->apiSecret
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->token = $data['access_token'] ?? null;

                if ($this->token) {
                    // Cache the token (default 1 hour minus 5 minutes for safety)
                    $ttl = config('fiskaly.cache.token_ttl', 3600) - 600;
                    Cache::put(config('fiskaly.cache.token_key'), $this->token, $ttl);

                    $this->log('info', 'Successfully authenticated with Fiskaly');
                    return $this->token;
                }
            }

            throw new FiskalyException('Failed to obtain access token: ' . $response->body());
        } catch (\Exception $e) {
            $this->log('error', 'Authentication failed', ['error' => $e->getMessage()]);
            throw new FiskalyException('Fiskaly authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Make authenticated request to Fiskaly API
     */
    public function request(string $method, string $endpoint, array $data = [], int $retryCount = 0): array
    {
        // Ensure we have a valid token
        if (!$this->token) {
            $this->authenticate();
        }

        $url = $this->baseUrl . $endpoint;
        $maxRetries = config('fiskaly.offline_mode.max_retry_attempts', 3);

        $this->log('debug', "Making {$method} request", [
            'url' => $url,
            'data' => $data,
            'retry' => $retryCount
        ]);

        try {
            $request = Http::withToken($this->token)
                ->timeout(30);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'PATCH' => $request->patch($url, $data),
                'DELETE' => $request->delete($url, $data),
                default => throw new FiskalyException("Unsupported HTTP method: {$method}"),
            };

            // Handle 401 Unauthorized - token might be expired
            if ($response->status() === 401 && $retryCount === 0) {
                $this->log('warning', 'Token expired, re-authenticating');
                Cache::forget(config('fiskaly.cache.token_key'));
                $this->token = null;
                $this->authenticate();
                return $this->request($method, $endpoint, $data, $retryCount + 1);
            }

            // Handle rate limiting or server errors with retry
            if ($response->status() >= 500 && $retryCount < $maxRetries) {
                $delay = config('fiskaly.offline_mode.retry_delay', 2);
                $this->log('warning', "Server error, retrying in {$delay}s", [
                    'status' => $response->status(),
                    'retry' => $retryCount + 1
                ]);
                sleep($delay);
                return $this->request($method, $endpoint, $data, $retryCount + 1);
            }

            if ($response->successful()) {
                $this->log('debug', 'Request successful', ['status' => $response->status()]);
                return $response->json() ?? [];
            }

            $error = $response->json();
            $errorMessage = $error['message'] ?? $error['error'] ?? 'Unknown error';

            $this->log('error', 'Request failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'response' => $error
            ]);

            throw new FiskalyException("Fiskaly API error: {$errorMessage}", $response->status());

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->log('error', 'Connection failed', ['error' => $e->getMessage()]);

            if ($retryCount < $maxRetries) {
                $delay = config('fiskaly.offline_mode.retry_delay', 2);
                sleep($delay);
                return $this->request($method, $endpoint, $data, $retryCount + 1);
            }

            throw new FiskalyException('Unable to connect to Fiskaly: ' . $e->getMessage());
        }
    }

    /**
     * GET request
     */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * POST request
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * PUT request
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * PATCH request
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * DELETE request
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Check if Fiskaly service is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/health');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get current token
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * Clear cached token
     */
    public function clearToken(): void
    {
        Cache::forget(config('fiskaly.cache.token_key'));
        $this->token = null;
    }

    /**
     * Log message
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $channel = config('fiskaly.logging.channel', 'daily');
        $configLevel = config('fiskaly.logging.level', 'info');

        // Check if we should log this level
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        if (($levels[$level] ?? 0) < ($levels[$configLevel] ?? 1)) {
            return;
        }

        Log::channel($channel)->$level('[Fiskaly] ' . $message, $context);
    }
}
