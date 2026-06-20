<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email channel for appointment reminders.
 *
 * Receives the ALREADY-translated subject/body (resolved by NotificationService
 * in the recipient's locale) so the same reminder text is shared across the push,
 * email and SMS channels.
 */
class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $body,
        public string $userName
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->title)
            ->view('emails.appointment-reminder');
    }
}
