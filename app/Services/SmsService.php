<?php

namespace App\Services;

use App\Services\Sms\SmsGateway;

/**
 * Generic SMS sender.
 *
 * Extracted so that any feature — OTP delivery, appointment reminders, … — can
 * send an SMS through one place. Since the seven.io migration the transport is
 * no longer hard-wired to one provider: this is a thin façade over
 * {@see \App\Services\Sms\SmsManager}, which picks the gateway named by
 * `config('sms.driver')`.
 *
 * The contract callers rely on is unchanged: if the SMS channel is switched off
 * or the gateway is not fully configured, the send is logged and skipped (no
 * exception), so a disabled gateway never breaks the calling flow. A *configured*
 * gateway that refuses the message still throws — a RuntimeException, exactly as
 * before, via {@see \App\Services\Sms\Exceptions\SmsDeliveryException}.
 */
class SmsService
{
    public function __construct(private readonly SmsGateway $gateway)
    {
    }

    public function send(string $phone, string $text): void
    {
        $this->gateway->send($phone, $text);
    }
}
