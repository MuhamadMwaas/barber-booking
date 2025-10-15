<?php

namespace App\Services;

use App\Enum\OtpType;
use App\Models\Otp;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OtpService
{
    public function generate(User $user, $lenth = 6, OtpType $type = OtpType::EMAIL_OTP): string
    {
        $otp = str_pad(random_int(0, 999999), $lenth, '0', STR_PAD_LEFT);

        Otp::where('email', $user->email)
            ->where('used', false)
            ->update(['used' => true]);

        Otp::create([
            'email' => $user->email,
            'otp' => $otp,
            'type' => $type->value,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        if ($type->value == OtpType::EMAIL_OTP->value) {

            // Mail::to($user->email)->send(new \App\Mail\SendOtpMail($otp));

        }

        if ($type->value == OtpType::SMS_OTP->value) {

        }



        return $otp;
    }

    public function validate(string $target, string $otp, OtpType $type = OtpType::EMAIL_OTP): bool
    {
        if ($type->value == OtpType::EMAIL_OTP->value) {
            $record = Otp::where('email', $target)
                ->where('otp', $otp)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();
        } else if ($type->value == OtpType::SMS_OTP->value) {
            $record = Otp::where('phone', $target)
                ->where('otp', $otp)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first( );
        }


        if (!$record) {
            return false;
        }

        $record->update(['used' => true]);
        return true;
    }
}