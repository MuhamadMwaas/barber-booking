<?php

/**
 * One-time code email (SendOtpMail). One text per purpose (OtpPurpose).
 * :company = brand name, :date/:time = when the code expires.
 */
return [
    'subject' => [
        'account_verification' => ':company – Confirm your email address',
        'password_reset'       => ':company – Reset your password',
    ],

    'greeting' => 'Hello :name,',

    'intro' => [
        'account_verification' => 'Please enter the following verification code to confirm your email address and continue your registration.',
        'password_reset'       => 'Please enter the following verification code to reset your password.',
    ],

    'validity'      => 'The code is valid until :date at :time.',
    'not_requested' => 'If you did not request this code, you can simply ignore this email.',
    'signoff'       => 'Your :company team',
];
