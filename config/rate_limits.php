<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication rate limits (AUTH-01)
    |--------------------------------------------------------------------------
    |
    | Every sensitive auth endpoint is limited on TWO independent dimensions:
    |
    |   per_ip       — brakes an attacker working through many accounts from one
    |                  place (credential stuffing, OTP flooding).
    |   per_account  — brakes an attacker working on ONE account from many places
    |                  (password spraying, distributed OTP brute force). The
    |                  attacker controls their address; they do not control which
    |                  account they are attacking, so this dimension is the one
    |                  that survives a rotating source IP.
    |
    | The per_ip numbers are deliberately generous rather than tight: mobile
    | carriers put large numbers of real customers behind a single CGNAT address,
    | so a strict per-IP cap punishes real users first. Tightness lives in the
    | per_account dimension, where a legitimate user never comes close.
    |
    | The limiters themselves are registered in AppServiceProvider::boot() and
    | applied as `throttle:<name>` in routes/api.php.
    |
    */

    /*
    | Login. A real user needs 1–3 attempts. `per_account_hour` is the long-window
    | backstop that turns "5 guesses a minute, forever" into 480 guesses a day.
    */
    'login' => [
        'per_ip' => (int) env('RL_LOGIN_PER_IP', 100),
        'per_account' => (int) env('RL_LOGIN_PER_ACCOUNT', 50),
        'per_account_hour' => (int) env('RL_LOGIN_PER_ACCOUNT_HOUR', 200),
    ],

    /*
    | Registration. Keyed on IP only — the account does not exist yet, so there is
    | no second dimension to key on. An hour window (not a minute) because signing
    | up is a once-per-lifetime action; anything faster is automation.
    */
    'register' => [
        'per_ip_hour' => (int) env('RL_REGISTER_PER_IP_HOUR', 50),
    ],

    /*
    | Token refresh. The mobile app refreshes roughly every 15 minutes, so this is
    | orders of magnitude above normal use; it exists to stop a stolen refresh
    | token being replayed in a tight loop.
    */
    'refresh' => [
        'per_ip' => (int) env('RL_REFRESH_PER_IP', 20),
    ],

    /*
    | Sending an OTP. This one spends real money (SMS credit), so the destination
    | dimension matters as much as the IP one: without it an attacker burns your
    | Vonage balance — and floods one victim's phone — from rotating addresses.
    |
    | OtpService already enforces a 60-second gap between sends to the same
    | destination (config/otp.php: resend_cooldown_seconds). This is the hourly
    | ceiling on top of that gap, not a replacement for it.
    */
    'otp_send' => [
        'per_ip' => (int) env('RL_OTP_SEND_PER_IP', 3),
        'per_destination_hour' => (int) env('RL_OTP_SEND_PER_DESTINATION_HOUR', 5),
    ],

    /*
    | Verifying an OTP. The most security-critical limit in this file: a 6-digit
    | code is a 1,000,000-value space, which is trivially exhaustible if requests
    | are unbounded.
    |
    | OtpService caps evaluated guesses per issued code (otp.max_attempts) with an
    | atomic conditional UPDATE, so parallel requests cannot exceed that cap.
    | These cache-backed limits remain a separate defence-in-depth layer: they
    | bound traffic across newly issued codes and reject abusive requests before
    | they reach the database.
    |
    | At 20 attempts/hour against a destination, trying 1,000,000 values would take
    | ~5.7 years, against an OTP that lives for only 10 minutes.
    */
    'otp_verify' => [
        'per_ip' => (int) env('RL_OTP_VERIFY_PER_IP', 10),
        'per_destination' => (int) env('RL_OTP_VERIFY_PER_DESTINATION', 5),
        'per_destination_hour' => (int) env('RL_OTP_VERIFY_PER_DESTINATION_HOUR', 20),
    ],

    /*
    | Google sign-in. Each call makes an outbound token-verification request to
    | Google, so an unbounded endpoint is both a cost and a dependency risk.
    */
    'social' => [
        'per_ip' => (int) env('RL_SOCIAL_PER_IP', 20),
    ],

];
