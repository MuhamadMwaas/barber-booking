<?php

namespace App\Services;

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use App\Enum\RegistrationMethod;
use App\Models\PasswordResetGrant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Channel-agnostic "forgot password" flow.
 *
 * The channel is chosen by the *caller* (`registration_method`), not by how the
 * account was originally created: an account that signed up by email but has a
 * phone number on file can still recover over SMS, and a phone-only account —
 * which has no email address at all — recovers over SMS as its only option.
 *
 * Three steps:
 *   1. sendResetOtp()  — issue + deliver a PASSWORD_RESET code
 *   2. issueGrant()    — exchange a correct code for a single-use grant token
 *   3. resetPassword() — redeem the grant, set the password, kill every session
 */
class PasswordResetService
{
    public function __construct(
        private OtpService $otpService,
        private AccountVerificationService $verificationService,
    ) {
    }

    public function channelFor(RegistrationMethod $method): OtpType
    {
        return $method === RegistrationMethod::PHONE
            ? OtpType::SMS_OTP
            : OtpType::EMAIL_OTP;
    }

    /**
     * Locate the account addressed by a reset request. Returns null when there is
     * no such account so the controller can decide how loudly to say so.
     */
    public function findUser(RegistrationMethod $method, string $identifier): ?User
    {
        return $method === RegistrationMethod::PHONE
            ? User::query()->where('phone', $identifier)->first()
            : User::query()->where('email', $identifier)->first();
    }

    public function cooldownRemaining(string $identifier, OtpType $channel): int
    {
        return $this->otpService->cooldownRemaining($identifier, $channel, OtpPurpose::PASSWORD_RESET);
    }

    /**
     * Issue and dispatch a password-reset code over the requested channel.
     *
     * @return array{otp: string, masked_destination: string, expires_in: int, resend_after: int}
     */
    public function sendResetOtp(User $user, OtpType $channel, string $identifier): array
    {
        $otp = $this->otpService->generate(
            user: $user,
            length: (int) config('otp.length', 6),
            type: $channel,
            purpose: OtpPurpose::PASSWORD_RESET,
        );

        return [
            'otp' => $otp,
            'masked_destination' => $this->verificationService->maskTarget($identifier, $channel),
            'expires_in' => (int) config('otp.ttl_minutes', 10) * 60,
            'resend_after' => (int) config('otp.resend_cooldown_seconds', 60),
        ];
    }

    public function verifyOtp(string $identifier, string $otp, OtpType $channel): bool
    {
        return $this->otpService->validate($identifier, $otp, $channel, OtpPurpose::PASSWORD_RESET);
    }

    /**
     * Mint the single-use token that authorises the actual password change.
     * Only its hash is persisted, and any previously issued grant for the same
     * user is revoked so exactly one reset can ever be in flight.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    public function issueGrant(User $user, OtpType $channel, ?string $ip = null, ?string $userAgent = null): array
    {
        $plain = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes((int) config('otp.password_reset.token_ttl_minutes', 15));

        DB::transaction(function () use ($user, $channel, $plain, $expiresAt, $ip, $userAgent) {
            $this->revokeGrants($user);

            PasswordResetGrant::create([
                'user_id' => $user->id,
                'token_hash' => $this->hashToken($plain),
                'channel' => $channel->value,
                'expires_at' => $expiresAt,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
            ]);
        });

        return ['token' => $plain, 'expires_at' => $expiresAt];
    }

    /**
     * Resolve a plaintext grant token to a still-redeemable grant, or null.
     */
    public function findRedeemableGrant(string $plainToken): ?PasswordResetGrant
    {
        $grant = PasswordResetGrant::query()
            ->with('user')
            ->where('token_hash', $this->hashToken($plainToken))
            ->first();

        return $grant && $grant->isRedeemable() ? $grant : null;
    }

    /**
     * Commit the new password.
     *
     * Everything here is deliberately destructive to existing access: a reset is
     * the remedy for a compromised account, so every issued access token, refresh
     * token, grant and pending reset code dies with the old password.
     */
    public function resetPassword(User $user, string $newPassword, OtpType $channel, ?PasswordResetGrant $grant = null): User
    {
        DB::transaction(function () use ($user, $newPassword, $channel, $grant) {
            $user->forceFill(['password' => Hash::make($newPassword)])->save();

            // Completing an OTP round-trip proves control of the destination, so
            // the channel used counts as verified. Without this a user could reset
            // their password and still be bounced to the activation screen.
            $this->verificationService->markVerified($user, $channel);

            $user->tokens()->delete();
            $user->refreshTokens()->update(['revoked' => true]);

            if ($grant) {
                $grant->forceFill(['used_at' => now()])->save();
            }

            $this->revokeGrants($user, exceptId: $grant?->id);
        });

        return $user->refresh();
    }

    private function revokeGrants(User $user, ?int $exceptId = null): void
    {
        PasswordResetGrant::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['used_at' => now()]);
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
