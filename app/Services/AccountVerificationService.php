<?php

namespace App\Services;

use App\Enum\OtpType;
use App\Enum\RegistrationMethod;
use App\Models\User;
use InvalidArgumentException;

class AccountVerificationService
{
    public function resolveRegistrationMethod(User $user, ?RegistrationMethod $fallback = null): RegistrationMethod
    {
        if ($user->registration_method instanceof RegistrationMethod) {
            return $user->registration_method;
        }

        if (is_string($user->registration_method) && $user->registration_method !== '') {
            return RegistrationMethod::from($user->registration_method);
        }

        if ($fallback instanceof RegistrationMethod) {
            return $fallback;
        }

        return $user->getRegistrationMethodEnum();
    }

    public function resolveOtpType(User $user, ?RegistrationMethod $fallback = null): OtpType
    {
        return $this->resolveRegistrationMethod($user, $fallback) === RegistrationMethod::PHONE
            ? OtpType::SMS_OTP
            : OtpType::EMAIL_OTP;
    }

    public function resolveVerificationTarget(User $user, ?RegistrationMethod $fallback = null): string
    {
        $otpType = $this->resolveOtpType($user, $fallback);
        $target = $otpType === OtpType::SMS_OTP ? $user->phone : $user->email;

        if (!$target) {
            throw new InvalidArgumentException('The user does not have a valid verification target for the selected channel.');
        }

        return $target;
    }

    public function maskTarget(string $target, OtpType $otpType): string
    {
        if ($otpType === OtpType::EMAIL_OTP) {
            [$localPart, $domain] = explode('@', $target, 2);
            $visiblePrefix = substr($localPart, 0, min(2, strlen($localPart)));
            $maskedLocal = $visiblePrefix . str_repeat('*', max(strlen($localPart) - strlen($visiblePrefix), 2));

            return $maskedLocal . '@' . $domain;
        }

        $normalized = preg_replace('/\s+/', '', $target) ?? $target;
        $visibleSuffix = substr($normalized, -4);

        return str_repeat('*', max(strlen($normalized) - 4, 4)) . $visibleSuffix;
    }

    public function buildVerificationPayload(User $user, ?RegistrationMethod $fallback = null): array
    {
        $method = $this->resolveRegistrationMethod($user, $fallback);
        $otpType = $this->resolveOtpType($user, $fallback);
        $target = $this->resolveVerificationTarget($user, $fallback);

        return [
            'registration_method' => $method->value,
            'verification_channel' => $method->value,
            'masked_destination' => $this->maskTarget($target, $otpType),
            'email_verified' => (bool) $user->email_verified_at,
            'phone_verified' => (bool) $user->phone_verified_at,
            'is_account_verified' => $user->is_account_verified,
            'requires_otp_verification' => $user->requires_otp_verification,
        ];
    }

    public function markVerified(User $user, OtpType $otpType): User
    {
        $attributes = [];

        if ($otpType === OtpType::EMAIL_OTP) {
            $attributes['email_verified_at'] = $user->email_verified_at ?? now();
            $attributes['email_verified_via_otp_at'] = now();
        }

        if ($otpType === OtpType::SMS_OTP) {
            $attributes['phone_verified_at'] = $user->phone_verified_at ?? now();
        }

        $user->forceFill($attributes)->save();

        return $user->refresh();
    }
}