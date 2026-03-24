<?php

namespace App\Services;

use App\Enum\OtpType;
use App\Mail\SendOtpMail;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function generate(User $user, int $length = 6, OtpType $type = OtpType::EMAIL_OTP): string
    {
        $otp = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(10);

        DB::transaction(function () use ($user, $otp, $type, $expiresAt) {
            $this->invalidateUnusedOtps($user, $type);

            Otp::create([
                'email' => $type === OtpType::EMAIL_OTP ? $user->email : null,
                'phone' => $type === OtpType::SMS_OTP ? $user->phone : null,
                'otp' => $otp,
                'type' => $type->value,
                'expires_at' => $expiresAt,
            ]);

            if ($type === OtpType::EMAIL_OTP) {
                Mail::to($user->email)->send(
                    new SendOtpMail(
                        otp: $otp,
                        userName: $user->full_name,
                        expiresAt: $expiresAt
                    )
                );
            }

            if ($type->value == OtpType::EMAIL_OTP->value) {


            }

            if ($type->value == OtpType::SMS_OTP->value) {
            }

        });

        return $otp;
    }

    public function validate(string $target, string $otp, OtpType $type = OtpType::EMAIL_OTP): bool
    {
        if ($type === OtpType::EMAIL_OTP) {
            $record = Otp::where('email', $target)
                ->where('otp', $otp)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();
        } elseif ($type === OtpType::SMS_OTP) {
            $record = Otp::where('phone', $target)
                ->where('otp', $otp)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();
        } else {
            return false;
        }

        if (!$record) {
            return false;
        }

        $record->update(['used' => true]);

        return true;
    }

    protected function invalidateUnusedOtps(User $user, OtpType $type): void
    {
        $query = Otp::query()->where('used', false)->where('type', $type->value);

        if ($type === OtpType::EMAIL_OTP) {
            $query->where('email', $user->email);
        } elseif ($type === OtpType::SMS_OTP) {
            $query->where('phone', $user->phone);
        }

        $query->update(['used' => true]);
    }
}
