<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email channel for appointment reminders.
 *
 * Same layout and booking-details block as the booking confirmation, so the
 * customer sees one consistent design. Push and SMS keep the short shared
 * reminder text (appointment_reminder.message); the email has room for the
 * full details instead.
 */
class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public string $companyName,
        public string $currency,
        string $locale,
    ) {
        $this->locale = $locale;
    }

    public function build(): self
    {
        return $this
            ->subject(__('booking_email.reminder_subject', [
                'number' => $this->appointment->number,
            ]))
            ->view('emails.appointment-reminder', [
                'appointment' => $this->appointment,
                'companyName' => $this->companyName,
                'currency'    => $this->currency,
                'audience'    => 'customer',
            ]);
    }
}
