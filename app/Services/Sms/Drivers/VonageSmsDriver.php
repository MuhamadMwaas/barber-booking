<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Exceptions\SmsDeliveryException;
use App\Services\Sms\PhoneNumberNormalizer;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vonage (Nexmo) SMS gateway — the provider used before the seven.io migration.
 *
 * Kept as a working fallback rather than deleted: setting SMS_DRIVER=vonage
 * restores the previous behaviour with no code change. The request/response
 * handling is the same logic that used to live inline in OtpDeliveryService and
 * SmsService, moved here unchanged apart from the shared number normalisation.
 */
class VonageSmsDriver implements SmsGateway
{
    public function __construct(private readonly PhoneNumberNormalizer $normalizer)
    {
    }

    public function name(): string
    {
        return 'vonage';
    }

    /**
     * @param  array{from?: string|null}  $options
     */
    public function send(string $to, string $text, array $options = []): SmsResult
    {
        $config = (array) config('sms.drivers.vonage', []);
        $key = $this->stringOrNull($config['key'] ?? null);
        $secret = $this->stringOrNull($config['secret'] ?? null);
        $from = $this->stringOrNull($options['from'] ?? ($config['from'] ?? null));
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://rest.nexmo.com'), '/');

        // Vonage has no account-default sender, so `from` is required here —
        // unlike seven.io, where omitting it is a valid choice.
        $recipient = $this->normalizer->toGatewayFormat($to, $this->stringOrNull(config('sms.default_country_code')));

        if ($key === null || $secret === null || $from === null || $recipient === '') {
            Log::info('SMS skipped: Vonage is not fully configured.', [
                'driver' => $this->name(),
                'to' => $to,
                'has_key' => $key !== null,
                'has_secret' => $secret !== null,
                'from' => $from,
            ]);

            return SmsResult::skipped($this->name(), $recipient !== '' ? $recipient : $to, $from);
        }

        try {
            $response = Http::timeout((int) ($config['timeout'] ?? 15))
                ->asForm()
                ->post($baseUrl . '/sms/json', [
                    'api_key' => $key,
                    'api_secret' => $secret,
                    'to' => $recipient,
                    'from' => $from,
                    'text' => trim($text),
                ]);
        } catch (ConnectionException $exception) {
            throw new SmsDeliveryException(
                'Failed to connect to the Vonage SMS API.',
                driver: $this->name(),
                previous: $exception,
            );
        }

        $message = $response->json('messages.0');

        if (! $response->successful() || ! is_array($message) || ($message['status'] ?? '0') !== '0') {
            $status = (string) ($message['status'] ?? $response->status());

            throw new SmsDeliveryException(
                sprintf(
                    'Vonage SMS delivery failed: status %s - %s',
                    $status,
                    (string) ($message['error-text'] ?? 'Unknown error.'),
                ),
                statusCode: $status,
                driver: $this->name(),
            );
        }

        return new SmsResult(
            sent: true,
            skipped: false,
            driver: $this->name(),
            to: $recipient,
            from: $from,
            messageIds: empty($message['message-id']) ? [] : [(string) $message['message-id']],
            balance: isset($message['remaining-balance']) ? (float) $message['remaining-balance'] : null,
            statusCode: (string) ($message['status'] ?? '0'),
            raw: is_array($response->json()) ? $response->json() : [],
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
