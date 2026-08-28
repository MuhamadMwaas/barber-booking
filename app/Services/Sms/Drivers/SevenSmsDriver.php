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
 * seven.io SMS gateway (https://docs.seven.io/en/rest-api/endpoints/sms).
 *
 * Transport is Laravel's HTTP client rather than the seven.io PHP SDK: the API
 * is a single form POST, and staying on Http keeps the driver fakeable with
 * Http::fake() in tests without pulling in another dependency.
 *
 * Two seven.io quirks drive the shape of this class:
 *
 *  1. The HTTP status is 200 even for rejected messages — the real outcome is
 *     the `success` field in the body, which is a *string* code. So a successful
 *     response is not enough; the code has to be read and mapped.
 *  2. The endpoint can answer in plain text (a bare code) when the JSON
 *     negotiation does not take, so the parser handles both shapes.
 */
class SevenSmsDriver implements SmsGateway
{
    /**
     * Documented seven.io return codes. Anything outside this list is reported
     * verbatim so an unknown future code is still actionable in the logs.
     *
     * @var array<string, string>
     */
    private const STATUS_MESSAGES = [
        '100' => 'Message accepted by the gateway.',
        '101' => 'Delivery to at least one recipient failed.',
        '201' => 'Invalid sender ID — max 11 alphanumeric or 16 numeric characters.',
        '202' => 'Invalid recipient number.',
        '300' => 'Missing or invalid user credentials.',
        '301' => 'Missing recipient ("to") parameter.',
        '305' => 'Missing or invalid message text.',
        '308' => 'Unknown or unsupported parameter submitted.',
        '400' => 'Invalid message type.',
        '401' => 'Message text exceeds the allowed length.',
        '402' => 'Duplicate message: the same text went to this number within the last 180 seconds.',
        '403' => 'Daily message limit for this recipient has been reached.',
        '500' => 'Insufficient account credit.',
        '600' => 'The carrier reported a sending error.',
        '700' => 'Unknown error.',
        '801' => 'Invalid foreign_id provided.',
        '802' => 'Invalid label provided.',
        '900' => 'Authentication failed — check SEVEN_API_KEY.',
        '901' => 'Signature hash verification failed.',
        '902' => 'The API key has no access rights for this endpoint.',
        '903' => 'The requesting server IP is not on the account whitelist.',
    ];

    private const SUCCESS_CODE = '100';

    public function __construct(private readonly PhoneNumberNormalizer $normalizer)
    {
    }

    public function name(): string
    {
        return 'seven';
    }

    /**
     * @param  array{from?: string|null, ttl?: int|null, label?: string|null, flash?: bool, foreign_id?: string|null}  $options
     */
    public function send(string $to, string $text, array $options = []): SmsResult
    {
        $config = (array) config('sms.drivers.seven', []);
        $apiKey = $this->stringOrNull($config['api_key'] ?? null);
        $recipient = $this->normalizer->toGatewayFormat($to, $this->stringOrNull(config('sms.default_country_code')));
        $from = $this->resolveSender($options['from'] ?? ($config['from'] ?? null));

        // A missing key is a configuration state, not a failure: skip quietly so
        // an unconfigured gateway never takes down the calling flow.
        if ($apiKey === null || $recipient === '' || trim($text) === '') {
            Log::info('SMS skipped: seven.io is not fully configured.', [
                'driver' => $this->name(),
                'to' => $to,
                'has_api_key' => $apiKey !== null,
                'normalized_recipient' => $recipient,
                'has_text' => trim($text) !== '',
            ]);

            return SmsResult::skipped($this->name(), $recipient !== '' ? $recipient : $to, $from);
        }

        $payload = $this->buildPayload($recipient, $text, $from, $config, $options);
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://gateway.seven.io/api'), '/');

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout((int) ($config['timeout'] ?? 15))
                ->asForm()
                ->post($baseUrl . '/sms', $payload);
        } catch (ConnectionException $exception) {
            throw new SmsDeliveryException(
                'Failed to connect to the seven.io SMS API.',
                driver: $this->name(),
                previous: $exception,
            );
        }

        return $this->interpret($response->status(), $response->body(), $recipient, $from);
    }

    /**
     * Current account credit, or null when the API key is not configured.
     *
     * This endpoint is free — it neither sends nor costs anything — which makes
     * it the cheapest possible proof that a key is valid and has access rights.
     *
     * @return array{amount: float, currency: string}|null
     *
     * @throws SmsDeliveryException
     */
    public function balance(): ?array
    {
        $config = (array) config('sms.drivers.seven', []);
        $apiKey = $this->stringOrNull($config['api_key'] ?? null);

        if ($apiKey === null) {
            return null;
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://gateway.seven.io/api'), '/');

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout((int) ($config['timeout'] ?? 15))
                ->get($baseUrl . '/balance');
        } catch (ConnectionException $exception) {
            throw new SmsDeliveryException(
                'Failed to connect to the seven.io balance API.',
                driver: $this->name(),
                previous: $exception,
            );
        }

        $body = trim($response->body());
        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['amount'])) {
            return [
                'amount' => (float) $decoded['amount'],
                'currency' => (string) ($decoded['currency'] ?? 'EUR'),
            ];
        }

        // Legacy shape: the body is a bare decimal amount.
        if (is_numeric($body)) {
            return ['amount' => (float) $body, 'currency' => 'EUR'];
        }

        throw new SmsDeliveryException(
            sprintf('Unreadable balance response from seven.io (HTTP %d): %s', $response->status(), mb_substr($body, 0, 200)),
            statusCode: preg_match('/^\d{3}$/', $body) === 1 ? $body : null,
            driver: $this->name(),
        );
    }

    /**
     * Build the form body, omitting every optional parameter that has no value.
     *
     * Omission matters here: seven.io answers code 308 for parameters it does
     * not recognise, and an empty `from` would be read as an invalid sender
     * rather than as "use the account default".
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $options
     * @return array<string, scalar>
     */
    private function buildPayload(string $recipient, string $text, ?string $from, array $config, array $options): array
    {
        $payload = [
            'to' => $recipient,
            'text' => trim($text),
        ];

        if ($from !== null) {
            $payload['from'] = $from;
        }

        $ttl = $options['ttl'] ?? ($config['ttl'] ?? null);
        if ($ttl !== null && (int) $ttl > 0) {
            $payload['ttl'] = (int) $ttl;
        }

        $label = $this->stringOrNull($options['label'] ?? ($config['label'] ?? null));
        if ($label !== null) {
            $payload['label'] = mb_substr($label, 0, 100);
        }

        $foreignId = $this->stringOrNull($options['foreign_id'] ?? null);
        if ($foreignId !== null) {
            $payload['foreign_id'] = mb_substr($foreignId, 0, 64);
        }

        if (! empty($options['flash'])) {
            $payload['flash'] = 1;
        }

        // Dry-run mode: seven.io validates and prices the message but neither
        // sends it nor charges for it.
        if (filter_var($config['debug'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $payload['debug'] = 1;
        }

        return $payload;
    }

    /**
     * seven.io returns HTTP 200 for rejected messages, so the body is the only
     * source of truth. It may be JSON or a bare status code.
     */
    private function interpret(int $httpStatus, string $body, string $recipient, ?string $from): SmsResult
    {
        $decoded = json_decode($body, true);
        $decoded = is_array($decoded) ? $decoded : [];

        // Plain-text fallback: the whole body is the status code.
        $code = isset($decoded['success'])
            ? (string) $decoded['success']
            : trim($body);

        if ($code === '' || preg_match('/^\d{3}$/', $code) !== 1) {
            throw new SmsDeliveryException(
                sprintf('Unreadable response from the seven.io SMS API (HTTP %d): %s', $httpStatus, mb_substr($body, 0, 200)),
                driver: $this->name(),
            );
        }

        $messages = is_array($decoded['messages'] ?? null) ? $decoded['messages'] : [];
        $failures = $this->collectMessageFailures($messages);

        if ($code !== self::SUCCESS_CODE || $failures !== []) {
            throw new SmsDeliveryException(
                $this->describeFailure($code, $failures),
                statusCode: $code,
                driver: $this->name(),
            );
        }

        $result = new SmsResult(
            sent: true,
            skipped: false,
            driver: $this->name(),
            to: $recipient,
            from: $from,
            messageIds: $this->collectMessageIds($messages),
            price: isset($decoded['total_price']) ? (float) $decoded['total_price'] : null,
            balance: isset($decoded['balance']) ? (float) $decoded['balance'] : null,
            statusCode: $code,
            raw: $decoded,
        );

        Log::info('SMS sent via seven.io.', [
            'to' => $recipient,
            'from' => $from,
            'message_ids' => $result->messageIds,
            'price' => $result->price,
            'balance' => $result->balance,
        ]);

        return $result;
    }

    /**
     * A batch can be partially rejected: the envelope says 100 while individual
     * message objects carry their own error. Those must not read as a success.
     *
     * @param  array<int, mixed>  $messages
     * @return array<int, string>
     */
    private function collectMessageFailures(array $messages): array
    {
        $failures = [];

        foreach ($messages as $index => $message) {
            if (! is_array($message) || ($message['success'] ?? true) !== false) {
                continue;
            }

            $error = $this->stringOrNull($message['error'] ?? null);

            $failures[] = sprintf(
                'recipient %s: %s',
                (string) ($message['recipient'] ?? '#' . $index),
                $this->stringOrNull($message['error_text'] ?? null)
                    ?? ($error !== null ? $this->describeCode($error) : 'unknown error'),
            );
        }

        return $failures;
    }

    /**
     * @param  array<int, mixed>  $messages
     * @return array<int, string>
     */
    private function collectMessageIds(array $messages): array
    {
        $ids = [];

        foreach ($messages as $message) {
            if (is_array($message) && ! empty($message['id'])) {
                $ids[] = (string) $message['id'];
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, string>  $failures
     */
    private function describeFailure(string $code, array $failures): string
    {
        $message = sprintf('seven.io SMS delivery failed (code %s): %s', $code, $this->describeCode($code));

        return $failures === []
            ? $message
            : $message . ' [' . implode('; ', $failures) . ']';
    }

    private function describeCode(string $code): string
    {
        return self::STATUS_MESSAGES[$code] ?? 'Undocumented gateway status code.';
    }

    /**
     * seven.io rejects senders longer than 11 alphanumeric characters with code
     * 201. Truncating silently would send under a name nobody configured, so the
     * over-long value is dropped and logged: the account default sender is used
     * instead and the message still gets through.
     */
    private function resolveSender(mixed $from): ?string
    {
        $from = $this->stringOrNull($from);

        if ($from === null) {
            return null;
        }

        $isNumeric = preg_match('/^\+?\d+$/', $from) === 1;
        $limit = $isNumeric ? 16 : 11;
        $length = mb_strlen(ltrim($from, '+'));

        if ($length > $limit) {
            Log::warning('Ignoring the configured seven.io sender ID: it exceeds the gateway limit.', [
                'from' => $from,
                'length' => $length,
                'limit' => $limit,
            ]);

            return null;
        }

        return $from;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
