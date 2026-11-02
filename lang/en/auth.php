<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // AUTH-01 — returned with HTTP 429 by every limiter in config/rate_limits.php.
    // Deliberately does not say WHICH limit was hit: telling an attacker whether
    // they exhausted the per-IP or the per-account bucket confirms the account exists.
    'throttle_generic' => 'Too many requests. Please try again in :seconds seconds.',

    'account_disabled_title' => 'Account Disabled',
    'account_disabled_body' => 'Your account has been disabled. Please contact the administration for assistance.',
    'account_disabled' => 'Your account has been disabled. Please contact the administration for assistance.',

    'customer_not_allowed_title' => 'Access Denied',
    'customer_not_allowed_body' => 'Customer accounts cannot access the admin panel.',

    // Returned (HTTP 409) when registering with an email that already exists.
    'email_exists_title' => 'Account already exists',
    'email_exists_body' => 'An account with this email already exists. Please sign in to continue.',

    // Returned (HTTP 409) when registering with a phone number that already exists.
    'phone_exists_title' => 'This phone number is already linked to another account.',
    'phone_exists_body' => 'Please use a different phone number or sign in.',

    // Buttons of the "account already exists" dialog (email and phone).
    'account_exists_login' => 'Sign in',
    'account_exists_close' => 'Close',

];
