<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\PhoneNumberNormalizer;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes the message to the log instead of sending it.
 *
 * The local-development counterpart of MAIL_MAILER=log: SMS_DRIVER=log lets the
 * full OTP flow be exercised end to end — including the code text — without a
 * gateway account or a spent credit.
 */
class LogSmsDriver implements SmsGateway
{
    public function __construct(private readonly PhoneNumberNormalizer $normalizer)
    {
    }

    public function name(): string
    {
        return 'log';
    }

    /**
     * @param  array{from?: string|null}  $options
     */
    public function send(string $to, string $text, array $options = []): SmsResult
    {
        $config = (array) config('sms.drivers.log', []);
        $recipient = $this->normalizer->normalize($to, (string) config('sms.default_country_code') ?: null);
        $from = $options['from'] ?? ($config['from'] ?? null);
        $messageId = (string) Str::uuid();

        $logger = ($channel = $config['channel'] ?? null)
            ? Log::channel((string) $channel)
            : Log::getFacadeRoot();

        $logger->info('SMS (log driver — not actually sent).', [
            'to' => $recipient,
            'from' => $from,
            'text' => $text,
            'message_id' => $messageId,
        ]);

        return new SmsResult(
            sent: true,
            skipped: false,
            driver: $this->name(),
            to: $recipient,
            from: is_string($from) ? $from : null,
            messageIds: [$messageId],
            statusCode: '100',
        );
    }
}
