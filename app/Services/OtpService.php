<?php

namespace App\Services;

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use App\Jobs\SendOtpDeliveryJob;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class OtpService
{
    /**
     * Issue a fresh code for a user, invalidating any previous code of the same
     * channel *and* purpose. Purposes are isolated on purpose: requesting a
     * password-reset code must not silently kill a pending activation code.
     */
    public function generate(
        User $user,
        ?int $length = null,
        OtpType $type = OtpType::EMAIL_OTP,
        OtpPurpose $purpose = OtpPurpose::ACCOUNT_VERIFICATION,
    ): string {
        $length = $length ?: (int) config('otp.length', 6);
        $otp = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes((int) config('otp.ttl_minutes', 10));

        DB::transaction(function () use ($user, $otp, $type, $purpose, $expiresAt) {
            $this->invalidateUnusedOtps($user, $type, $purpose);

            Otp::create([
                'email' => $type === OtpType::EMAIL_OTP ? $user->email : null,
                'phone' => $type === OtpType::SMS_OTP ? $user->phone : null,
                'otp' => $otp,
                'type' => $type->value,
                'purpose' => $purpose->value,
                'attempts' => 0,
                'expires_at' => $expiresAt,
            ]);

            SendOtpDeliveryJob::dispatch(
                userId: $user->id,
                otp: $otp,
                type: $type,
                expiresAt: $expiresAt->toIso8601String(),
            )->afterCommit();
        });

        return $otp;
    }

    /**
     * Redeem a code. Success burns it; failure burns one of a limited number of
     * attempts, after which the code is burned too — a 6-digit secret that stays
     * guessable for ten minutes is only safe if guesses are capped.
     */
    public function validate(
        string $target,
        string $otp,
        OtpType $type = OtpType::EMAIL_OTP,
        OtpPurpose $purpose = OtpPurpose::ACCOUNT_VERIFICATION,
    ): bool {
        $record = $this->latestLiveOtp($target, $type, $purpose);

        if (!$record) {
            return false;
        }

        $maxAttempts = max(1, (int) config('otp.max_attempts', 5));

        if ($record->attempts >= $maxAttempts) {
            $record->update(['used' => true]);

            return false;
        }

        if (!hash_equals((string) $record->otp, $otp)) {
            $record->increment('attempts');

            if ($record->attempts >= $maxAttempts) {
                $record->update(['used' => true]);
            }

            return false;
        }

        $record->update(['used' => true]);

        return true;
    }

    /**
     * Seconds the caller must still wait before another code may be sent to this
     * destination for this purpose, or 0 when sending is allowed.
     */
    public function cooldownRemaining(
        string $target,
        OtpType $type,
        OtpPurpose $purpose = OtpPurpose::ACCOUNT_VERIFICATION,
    ): int {
        $cooldown = (int) config('otp.resend_cooldown_seconds', 60);

        if ($cooldown <= 0) {
            return 0;
        }

        $lastOtp = $this->scopeToTarget(Otp::query(), $target, $type)
            ->where('purpose', $purpose->value)
            ->latest('created_at')
            ->first();

        if (!$lastOtp || !$lastOtp->created_at) {
            return 0;
        }

        $elapsed = now()->getTimestamp() - $lastOtp->created_at->getTimestamp();

        return $elapsed >= $cooldown ? 0 : $cooldown - $elapsed;
    }

    /**
     * The only code that may currently be redeemed for this destination/purpose.
     * generate() invalidates older ones, so "newest live row" is the whole set.
     */
    protected function latestLiveOtp(string $target, OtpType $type, OtpPurpose $purpose): ?Otp
    {
        return $this->scopeToTarget(Otp::query(), $target, $type)
            ->where('purpose', $purpose->value)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    protected function invalidateUnusedOtps(User $user, OtpType $type, OtpPurpose $purpose): void
    {
        $target = $type === OtpType::SMS_OTP ? $user->phone : $user->email;

        if (!$target) {
            return;
        }

        $this->scopeToTarget(Otp::query(), $target, $type)
            ->where('purpose', $purpose->value)
            ->where('used', false)
            ->update(['used' => true]);
    }

    /**
     * SMS codes live in the `phone` column and email codes in `email`; the two
     * are mutually exclusive, so matching the right column also pins the channel.
     */
    protected function scopeToTarget($query, string $target, OtpType $type)
    {
        return $type === OtpType::SMS_OTP
            ? $query->where('phone', $target)
            : $query->where('email', $target);
    }
}
