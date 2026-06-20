<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Vonage\Client;
use Vonage\Client\Exception\Exception as VonageException;
use Vonage\SMS\Collection as VonageSmsCollection;
use Vonage\SMS\Message\SMS as VonageSmsMessage;

/**
 * SMS sender backed by the official vonage/vonage-laravel package.
 *
 * This keeps the app-level contract similar to SmsService while delegating the
 * transport to the SDK client registered by VonageServiceProvider.
 */
class VonageSdkSmsService
{
    /**
     * @param  array{
     *     from?: string,
     *     client_ref?: string,
     *     ttl?: int,
     *     type?: 'text'|'unicode',
     *     account_ref?: string
     * }  $options
     * @return array{
     *     sent: bool,
     *     skipped: bool,
     *     to: string,
     *     from: string|null,
     *     type: string|null,
     *     client_ref: string|null,
     *     message_count: int,
     *     message_ids: array<int, string>,
     *     remaining_balance: string|null,
     *     raw_messages: array<int, array<string, mixed>>
     * }
     */
    public function send(string $phone, string $text, array $options = []): array
    {
        $phone = trim($phone);
        $text = trim($text);

        if ($phone === '') {
            throw new InvalidArgumentException('Phone number is required.');
        }

        if ($text === '') {
            throw new InvalidArgumentException('SMS text is required.');
        }

        $config = config('services.vonage', []);
        $enabled = (bool) ($config['enabled'] ?? false);
        $defaultFrom = $this->normalizeNullableString($config['from'] ?? null);
        $apiKey = $this->normalizeNullableString(config('vonage.api_key'));
        $apiSecret = $this->normalizeNullableString(config('vonage.api_secret'));

        $from = $this->normalizeNullableString($options['from'] ?? $defaultFrom);
        $clientReference = $this->normalizeClientReference($options['client_ref'] ?? null);

        if (! $enabled || ! $apiKey || ! $apiSecret || ! $from) {
            Log::info('SDK SMS delivery skipped because Vonage is not fully configured.', [
                'phone' => $phone,
                'enabled' => $enabled,
                'has_api_key' => (bool) $apiKey,
                'has_api_secret' => (bool) $apiSecret,
                'from' => $from,
            ]);

            return [
                'sent' => false,
                'skipped' => true,
                'to' => $phone,
                'from' => $from,
                'type' => null,
                'client_ref' => $clientReference,
                'message_count' => 0,
                'message_ids' => [],
                'remaining_balance' => null,
                'raw_messages' => [],
            ];
        }

        $type = $options['type'] ?? (VonageSmsMessage::isGsm7($text) ? 'text' : 'unicode');
        $message = new VonageSmsMessage($phone, $from, $text, $type);

        if ($clientReference !== null) {
            $message->setClientRef($clientReference);
        }

        if (isset($options['ttl'])) {
            $message->setTtl((int) $options['ttl']);
        }

        if ($accountReference = $this->normalizeNullableString($options['account_ref'] ?? null)) {
            $message->setAccountRef($accountReference);
        }

        try {
            /** @var Client $client */
            $client = app(Client::class);
            $response = $client->sms()->send($message);
        } catch (ClientExceptionInterface|VonageException $exception) {
            throw new RuntimeException('Failed to deliver Vonage SMS via the SDK.', previous: $exception);
        }

        return $this->mapSuccessfulResponse(
            phone: $phone,
            from: $from,
            type: $type,
            clientReference: $clientReference,
            response: $response,
        );
    }

    /**
     * @return array{
     *     sent: bool,
     *     skipped: bool,
     *     to: string,
     *     from: string,
     *     type: string,
     *     client_ref: string|null,
     *     message_count: int,
     *     message_ids: array<int, string>,
     *     remaining_balance: string|null,
     *     raw_messages: array<int, array<string, mixed>>
     * }
     */
    private function mapSuccessfulResponse(
        string $phone,
        string $from,
        string $type,
        ?string $clientReference,
        VonageSmsCollection $response,
    ): array {
        $raw = $response->getAllMessagesRaw();
        $messages = is_array($raw['messages'] ?? null) ? $raw['messages'] : [];

        $failures = [];
        $messageIds = [];
        $remainingBalance = null;

        foreach ($messages as $index => $message) {
            if (! is_array($message)) {
                continue;
            }

            $status = (string) ($message['status'] ?? '0');

            if ($remainingBalance === null) {
                $remainingBalance = isset($message['remaining-balance'])
                    ? (string) $message['remaining-balance']
                    : null;
            }

            if (! empty($message['message-id'])) {
                $messageIds[] = (string) $message['message-id'];
            }

            if ($status !== '0') {
                $failures[] = sprintf(
                    'segment %d status %s - %s',
                    $index,
                    $status,
                    (string) ($message['error-text'] ?? 'Unknown error.')
                );
            }
        }

        if ($failures !== []) {
            throw new RuntimeException('Vonage SDK SMS delivery failed: ' . implode('; ', $failures));
        }

        $result = [
            'sent' => true,
            'skipped' => false,
            'to' => $phone,
            'from' => $from,
            'type' => $type,
            'client_ref' => $clientReference,
            'message_count' => (int) ($raw['message-count'] ?? count($messages)),
            'message_ids' => $messageIds,
            'remaining_balance' => $remainingBalance,
            'raw_messages' => $messages,
        ];

        Log::info('Vonage SDK SMS sent successfully.', [
            'to' => $phone,
            'from' => $from,
            'type' => $type,
            'client_ref' => $clientReference,
            'message_count' => $result['message_count'],
            'message_ids' => $messageIds,
        ]);

        return $result;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeClientReference(mixed $value): ?string
    {
        $value = $this->normalizeNullableString($value);

        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, 40);
    }
}
