<?php

namespace App\Services;

use App\Enum\OtpType;
use App\Mail\SendOtpMail;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OtpDeliveryService
{
    public function __construct(private readonly SmsGateway $sms)
    {
    }

    public function deliver(User $user, string $otp, Carbon $expiresAt, OtpType $type): void
    {
        if ($type === OtpType::EMAIL_OTP) {
            Mail::to($user->email)->send(
                new SendOtpMail(
                    otp: $otp,
                    userName: $user->full_name,
                    expiresAt: $expiresAt,
                )
            );

            return;
        }

        if ($type === OtpType::SMS_OTP) {
            $this->sendSmsOtp($user, $otp, $expiresAt);
        }
    }

    protected function sendSmsOtp(User $user, string $otp, Carbon $expiresAt): void
    {
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
            sprintf('Your OTP code is %s. It expires in %d minutes.', $otp, $minutes),
            [
                'ttl' => $minutes,
                'label' => 'otp',
            ],
        );
    }
}
