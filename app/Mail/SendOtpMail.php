<?php

namespace App\Mail;

use App\Enum\OtpPurpose;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * One-time code by email — account verification or password reset.
 *
 * The locale is the one the code was requested in (captured at dispatch by
 * OtpService); a locale without a translation falls back to German.
 */
class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    private const FALLBACK_LOCALE = 'de';

    public function __construct(
        public string $otp,
        public string $userName,
        public CarbonInterface $expiresAt,
        public OtpPurpose $purpose = OtpPurpose::ACCOUNT_VERIFICATION,
        ?string $locale = null,
        public ?string $companyName = null,
    ) {
        $this->locale = $locale !== null && Lang::has('otp_email.greeting', $locale, false)
            ? $locale
            : self::FALLBACK_LOCALE;
    }

    public function build(): self
    {
        $company = $this->companyName ?: config('app.name');

        // Shown in the app timezone, the same clock the expiry is stored in.
        $expiresAt = $this->expiresAt->copy()->setTimezone(config('app.timezone'));

        return $this
            ->subject(__('otp_email.subject.' . $this->purpose->value, ['company' => $company]))
            ->view('emails.otp', [
                'company' => $company,
                'intro' => __('otp_email.intro.' . $this->purpose->value),
                'expiryDate' => $expiresAt->format('d.m.Y'),
                'expiryTime' => $expiresAt->format('H:i'),
            ]);
    }
}
