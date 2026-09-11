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
     *
     * CONCURRENCY (AUTH-02)
     * ---------------------
     * This used to read the row, compare `attempts` against the cap in PHP, and
     * only then write. Between the read and the write there was nothing stopping
     * other requests doing the same, so every request in a simultaneous burst
     * read the same pre-increment value and passed the cap check together:
     *
     *     session 0 reads attempts=0 -> 0 < 5 -> passes
     *     session 1 reads attempts=0 -> 0 < 5 -> passes
     *     ...                                            (8 sessions measured)
     *     final attempts = 8, i.e. 8 guesses evaluated against a cap of 5
     *
     * Note the counter itself was never wrong — `increment()` issues
     * `SET attempts = attempts + 1`, which is atomic. It was the CHECK that was
     * racy, and a check that is not fused to the write it guards is not a check.
     *
     * The fix fuses them: `claimAttempt()` below is a single conditional UPDATE,
     * so the database — not PHP — decides whether a slot was available, and it
     * decides for one caller at a time. `max_attempts` slots exist per code, and
     * a request either claims exactly one or is turned away.
     *
     * `lockForUpdate()` inside a transaction would also close the race, but it
     * would hold the row lock while PHP compares the code and performs the final
     * write. The conditional UPDATE keeps that critical section to one statement
     * and reduces contention with `invalidateUnusedOtps()` during a resend.
     *
     * It also gives the desired fail-closed boundary: once claimAttempt() returns,
     * the attempt is already committed. If the request dies before comparison,
     * the claimed slot remains spent. Putting the increment in the surrounding
     * verification transaction would roll it back when that transaction fails.
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

        // Claim one of the capped attempt slots. Whoever gets a slot may evaluate
        // a guess; everyone else is refused without ever reaching the comparison.
        if (!$this->claimAttempt($record->id, $maxAttempts)) {
            // No slot left (or the code was consumed/expired in the meantime).
            // Burn it so the remaining lifetime cannot be probed further.
            $this->burn($record->id);

            return false;
        }

        // The secret is immutable for a given row, so comparing against the value
        // read above is safe even though the read was not locked. hash_equals is
        // constant-time: a plain === leaks the code one character at a time
        // through response timing.
        if (!hash_equals((string) $record->otp, $otp)) {
            // attempts was already incremented by the claim, so re-read it rather
            // than trusting the stale in-memory value.
            if ((int) Otp::query()->whereKey($record->id)->value('attempts') >= $maxAttempts) {
                $this->burn($record->id);
            }

            return false;
        }

        // Correct code. Burn it as part of the same guarded write: two requests
        // arriving with the correct code at once must not both redeem it, because
        // callers exchange a successful validation for real authority (a password
        // reset grant, a verified account).
        return $this->burn($record->id);
    }

    /**
     * Atomically consume one attempt slot for a code.
     *
     * The whole guard lives in the WHERE clause, so the database evaluates the
     * cap and performs the increment as one indivisible operation. The affected
     * row count is the answer: 1 means this caller owns an attempt, 0 means the
     * cap was already spent — or the row was consumed or expired between the read
     * and now, which are re-checked here for exactly that reason.
     */
    protected function claimAttempt(int $otpId, int $maxAttempts): bool
    {
        return Otp::query()
            ->whereKey($otpId)
            ->where('attempts', '<', $maxAttempts)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->update(['attempts' => DB::raw('attempts + 1')]) === 1;
    }

    /**
     * Retire a code. Guarded on `used = false` so the caller can tell whether it
     * was the one that consumed it — that is what makes redemption single-use
     * under concurrency.
     */
    protected function burn(int $otpId): bool
    {
        return Otp::query()
            ->whereKey($otpId)
            ->where('used', false)
            ->update(['used' => true]) === 1;
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
