<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    */

    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset code is invalid or has expired.',
    'user' => "We can't find an account matching those details.",

    // OTP-based reset flow (App\Http\Controllers\Api\PasswordResetController).
    'sent_email' => 'We have emailed you a password reset code.',
    'sent_sms' => 'We have sent a password reset code to your phone number.',
    'otp_verified' => 'Code verified. You can now choose a new password.',
    'cooldown' => 'Please wait :seconds seconds before requesting a new code.',

];
