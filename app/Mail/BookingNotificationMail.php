<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the COMPANY inbox for every new booking.
 *
 * Queued: implements ShouldQueue so it never blocks the booking response
 * and a failed SMTP send is retried by the queue worker.
 */
class BookingNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public string $companyName,
        public string $currency,
        string $locale,
    ) {
        // Internal notification — rendered in the salon's default language.
        $this->locale = $locale;
    }

    public function build(): self
    {
        return $this
            ->subject(__('booking_email.subject_company', [
                'number' => $this->appointment->number,
            ]))
            ->view('emails.booking.notification', [
                'appointment' => $this->appointment,
                'companyName' => $this->companyName,
                'currency'    => $this->currency,
                'audience'    => 'company',
            ]);
    }
}
