<?php

namespace App\Services\Sms;

/**
 * Outcome of a single send attempt, normalised across gateways.
 *
 * Callers that only care whether anything left the building read `sent`;
 * `skipped` distinguishes "the gateway is switched off" from "it failed",
 * which is the difference between a quiet no-op and an exception.
 */
final class SmsResult
{
    /**
     * @param  array<int, string>  $messageIds
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $sent,
        public readonly bool $skipped,
        public readonly string $driver,
        public readonly string $to,
        public readonly ?string $from = null,
        public readonly array $messageIds = [],
        public readonly ?float $price = null,
        public readonly ?float $balance = null,
        public readonly ?string $statusCode = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function skipped(string $driver, string $to, ?string $from = null, array $raw = []): self
    {
        return new self(
            sent: false,
            skipped: true,
            driver: $driver,
            to: $to,
            from: $from,
            raw: $raw,
        );
    }

    /**
     * Shape kept identical to the array VonageSdkSmsService already returns, so
     * existing consumers (and the /api/test/vonage-sms route) can read either.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sent' => $this->sent,
            'skipped' => $this->skipped,
            'driver' => $this->driver,
            'to' => $this->to,
            'from' => $this->from,
            'message_ids' => $this->messageIds,
            'price' => $this->price,
            'remaining_balance' => $this->balance,
            'status_code' => $this->statusCode,
            'raw' => $this->raw,
        ];
    }
}
