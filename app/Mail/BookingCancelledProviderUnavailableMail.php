<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the CUSTOMER when their booking was cancelled because its provider
 * went on leave and no colleague was free to take it (LeaveReassignmentService).
 *
 * Queued: implements ShouldQueue so it never blocks granting the leave and a
 * failed SMTP send is retried by the queue worker.
 */
class BookingCancelledProviderUnavailableMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public string $companyName,
        public string $currency,
        string $locale,
    ) {
        // Render the email in the customer's language.
        $this->locale = $locale;
    }

    public function build(): self
    {
        return $this
            ->subject(__('booking_email.cancelled_provider_unavailable.subject', [
                'number' => $this->appointment->number,
            ]))
            ->view('emails.booking.cancelled-provider-unavailable', [
                'appointment' => $this->appointment,
                'companyName' => $this->companyName,
                'currency' => $this->currency,
                'audience' => 'customer',
            ]);
    }
}
