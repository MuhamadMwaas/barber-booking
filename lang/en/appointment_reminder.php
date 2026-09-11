<?php

/**
 * Appointment-reminder texts.
 *
 * `title` and `message` are the reminder itself, shared verbatim by all three
 * channels (push, email, SMS) so the customer never sees three wordings of the
 * same message.
 *
 * `screen` and `options` are UI strings the mobile app fetches from
 * `GET /api/appointments/reminders/options`, so the dropdown does not have to be
 * hard-coded in the app or shipped in an update to change.
 */
return [
    'title' => 'Appointment Reminder',
    'message' => 'Reminder: your appointment #:number is at :time on :date.',

    'screen' => [
        'title' => 'Appointment reminder',
        'subtitle' => 'Get a reminder before your appointment.',
        'question' => 'When would you like to be reminded?',
    ],

    'options' => [
        1 => '1 hour before',
        2 => '2 hours before',
        3 => '3 hours before',
        4 => '4 hours before',
        5 => '5 hours before',
        6 => '6 hours before',
        24 => '24 hours before',
    ],

    'option_fallback' => ':hours hours before',
];
