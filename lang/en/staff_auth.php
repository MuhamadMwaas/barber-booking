<?php

/*
 * Staff Dashboard sign-in page (resources/views/auth/staff-login.blade.php).
 *
 * Separate from the per-locale auth.php on purpose: that file is shared with the
 * API and the Filament panel, and these strings belong to one screen. Refusals
 * (disabled account, non-staff account) still come from auth.php via
 * StaffLoginDenial, so the two surfaces never word the same refusal differently.
 */

return [
    'title' => 'Staff Sign In',
    'subtitle' => 'Staff Dashboard',
    'email' => 'Email',
    'password' => 'Password',
    'remember' => 'Keep me signed in',
    'submit' => 'Sign in',
    'help' => 'Trouble signing in? Contact the salon administration.',

    'logout_confirm_title' => 'You are still checked in',
    'logout_confirm_body' => 'Do you want to check out before signing out?',
    'logout_with_check_out' => 'Check out and sign out',
    'logout_only' => 'Sign out only',
    'cancel' => 'Cancel',
];
