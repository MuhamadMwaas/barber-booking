<?php

namespace App\Services\Sms;

/**
 * The one contract every SMS provider in this app implements.
 *
 * Application code (OTP delivery, appointment reminders, …) depends on this and
 * never on a concrete gateway, so switching providers is a config change.
 */
interface SmsGateway
{
    /**
     * Deliver a text message.
     *
     * Implementations MUST return a skipped result (never throw) when the
     * gateway is not configured — a disabled SMS channel must not break the
     * calling flow — and MUST throw {@see Exceptions\SmsDeliveryException} when
     * a configured gateway refuses or fails the message.
     *
     * @param  array{from?: string|null, ttl?: int|null, label?: string|null, flash?: bool, foreign_id?: string|null}  $options
     *
     * @throws Exceptions\SmsDeliveryException
     */
    public function send(string $to, string $text, array $options = []): SmsResult;

    /**
     * Machine name of this gateway, as used by `config('sms.driver')`.
     */
    public function name(): string;
}
