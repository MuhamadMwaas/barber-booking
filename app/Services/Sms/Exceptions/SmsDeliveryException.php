<?php

namespace App\Services\Sms\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A configured gateway refused or failed to deliver the message.
 *
 * Extends RuntimeException so the call sites that already catch RuntimeException
 * (SendAppointmentReminderJob, the OTP delivery job's retry handling) keep
 * behaving exactly as they did before the seven.io migration.
 */
class SmsDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $statusCode = null,
        public readonly ?string $driver = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
