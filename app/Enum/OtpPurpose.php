<?php

namespace App\Enum;

/**
 * What an OTP is allowed to do once redeemed.
 *
 * OtpType says *how* a code travels (email vs SMS); OtpPurpose says *what it
 * unlocks*. Keeping them separate is what stops an activation code from being
 * replayed against the password-reset endpoint, and vice versa.
 */
enum OtpPurpose: string
{
    case ACCOUNT_VERIFICATION = 'account_verification';
    case PASSWORD_RESET = 'password_reset';
}
