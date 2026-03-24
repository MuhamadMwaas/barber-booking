<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $userName,
        public Carbon $expiresAt,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Your OTP Code')
            ->view('emails.otp');
    }
}
