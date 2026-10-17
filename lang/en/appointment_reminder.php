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
    // Current wording (client-approved). Placeholders are filled by
    // AppointmentReminderService::buildPayload() at send time:
    // :name customer first name, :salon company name, :weekday localised day,
    // :date dd.mm.yyyy, :time HH:MM, :provider staff first name.
    // Keep the SMS body GSM-7 (no "–", no emoji) or each SMS part drops from
    // 160 to 70 characters.
    'v2' => [
        'title' => ':salon – Appointment Reminder',
        'message' => 'Hi :name, this is a reminder of your appointment at :salon on :weekday, :date at :time with :provider. We look forward to seeing you! Your :salon Team',
    ],

    // LEGACY — do not delete: stored in-app notifications reference these keys
    // and are re-translated on every read (NotificationResource).
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
