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

    'requirements' => [
        'title' => 'Password requirements',
        'minimum_length' => 'At least :min characters',
        'latin_only' => 'Latin characters only',
        'uppercase' => 'At least one uppercase letter (A-Z)',
        'number' => 'At least one number',
        'edit_helper' => 'Leave blank to keep the current password.',
        'state' => [
            'met' => 'requirement met',
            'unmet' => 'requirement not met',
        ],
        'validation' => [
            'minimum_length' => 'The password must contain at least :min characters.',
            'latin_only' => 'Use Latin letters, numbers, and symbols only, without spaces.',
            'uppercase' => 'The password must contain at least one uppercase letter (A-Z).',
            'number' => 'The password must contain at least one number.',
        ],
    ],

];
