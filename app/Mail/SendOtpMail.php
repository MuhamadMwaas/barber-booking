<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $userName,
        public CarbonInterface $expiresAt,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Your OTP Code')
            ->view('emails.otp');
    }
}
