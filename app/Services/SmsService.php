<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generic SMS sender (Vonage / Nexmo).
 *
 * Extracted so that any feature — OTP delivery, appointment reminders, … — can
 * send an SMS through one place. Mirrors the proven Vonage flow already used by
 * {@see OtpDeliveryService}: if Vonage is not fully configured it logs and skips
 * (no exception), so a disabled SMS gateway never breaks the calling flow.
 */
class SmsService
{
    public function send(string $phone, string $text): void
    {
        $config = config('services.vonage', []);
        $enabled = (bool) ($config['enabled'] ?? false);
        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;
        $from = $config['from'] ?? null;
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://rest.nexmo.com'), '/');

        if (! $enabled || ! $key || ! $secret || ! $from) {
            Log::info('SMS delivery skipped because Vonage is not fully configured.', [
                'phone' => $phone,
            ]);

            return;
        }

        try {
            $response = Http::asForm()->post($baseUrl . '/sms/json', [
                'api_key' => $key,
                'api_secret' => $secret,
                'to' => $phone,
                'from' => $from,
                'text' => $text,
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Failed to connect to the Vonage SMS API.', previous: $exception);
        }

        $message = $response->json('messages.0');

        if (! $response->successful() || ! is_array($message) || ($message['status'] ?? '0') !== '0') {
            $status = (string) ($message['status'] ?? $response->status());
            $errorText = (string) ($message['error-text'] ?? 'Unknown error.');

            throw new RuntimeException(sprintf('Vonage SMS delivery failed: status %s - %s', $status, $errorText));
        }
    }
}
