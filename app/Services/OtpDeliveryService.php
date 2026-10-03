<?php

namespace App\Services;

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use App\Mail\SendOtpMail;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OtpDeliveryService
{
    /**
     * The salon is in Germany: a code requested without a usable language
     * (console, a locale with no SMS translation) goes out in German.
     */
    private const FALLBACK_LOCALE = 'de';

    public function __construct(private readonly SmsGateway $sms)
    {
    }

    public function deliver(
        User $user,
        string $otp,
        Carbon $expiresAt,
        OtpType $type,
        OtpPurpose $purpose = OtpPurpose::ACCOUNT_VERIFICATION,
        ?string $locale = null,
    ): void {
        if ($type === OtpType::EMAIL_OTP) {
            Mail::to($user->email)->send(
                new SendOtpMail(
                    otp: $otp,
                    userName: $user->full_name,
                    expiresAt: $expiresAt,
                    purpose: $purpose,
                    locale: $locale,
                    companyName: app(BookingMailService::class)->companyName(),
                )
            );

            return;
        }

        if ($type === OtpType::SMS_OTP) {
            $this->sendSmsOtp($user, $otp, $expiresAt, $purpose, $locale);
        }
    }

    protected function sendSmsOtp(
        User $user,
        string $otp,
        Carbon $expiresAt,
        OtpPurpose $purpose,
        ?string $locale,
    ): void {
        $phone = $user->phone;

        if (!$phone) {
            throw new RuntimeException('Cannot send SMS OTP without a phone number.');
        }

        $minutes = max(1, (int) ceil(now()->diffInSeconds($expiresAt, absolute: true) / 60));

        // The gateway is told the code's own lifetime: an OTP that arrives after
        // it has already expired is worse than one that never arrives, because
        // the user retypes it and gets a failure they cannot explain.
        $this->sms->send(
            $phone,
            $this->smsText($otp, $minutes, $purpose, $locale),
            [
                'ttl' => $minutes,
                'label' => 'otp',
            ],
        );
    }

    /**
     * The SMS body in the language the code was requested in. The minutes come
     * from the code's real expiry, so the text can never promise a lifetime
     * that OTP_TTL_MINUTES does not grant.
     */
    public function smsText(string $otp, int $minutes, OtpPurpose $purpose, ?string $locale): string
    {
        $key = 'sms.otp.' . $purpose->value;

        if ($locale === null || !Lang::has($key, $locale, false)) {
            $locale = self::FALLBACK_LOCALE;
        }

        return __($key, [
            'code' => $otp,
            'validity' => trans_choice('sms.minutes', $minutes, [], $locale),
        ], $locale);
    }
}
