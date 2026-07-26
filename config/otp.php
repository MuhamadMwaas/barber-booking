<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Code shape & lifetime
    |--------------------------------------------------------------------------
    |
    | `length` is how many digits a generated OTP has. `ttl_minutes` is how long
    | it stays redeemable. These mirror the values that used to be read straight
    | from env()/hard-coded inside OtpService.
    |
    */

    'length' => (int) env('OTP_LENGTH', 6),

    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Abuse protection
    |--------------------------------------------------------------------------
    |
    | `resend_cooldown_seconds` is the minimum gap between two OTP sends to the
    | same destination for the same purpose — it protects the SMS budget as much
    | as the user's inbox.
    |
    | `max_attempts` is how many wrong codes may be submitted against a single
    | issued OTP before it is burned. Without it a 6-digit code sitting valid for
    | ten minutes is trivially brute-forced.
    |
    */

    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),

    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Password reset
    |--------------------------------------------------------------------------
    |
    | Verifying a reset OTP exchanges it for a single-use grant token. That token
    | — not the OTP — authorises the actual password change, so the OTP is never
    | transmitted twice and the client can split "enter code" and "choose a new
    | password" across two screens.
    |
    */

    'password_reset' => [
        'token_ttl_minutes' => (int) env('PASSWORD_RESET_TOKEN_TTL_MINUTES', 15),
    ],

];
