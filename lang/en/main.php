<?php

return [
    'validation' => [
        'failed' => 'Invalid data provided.',
    ],
    'cancel' => 'Cancel',
    'settings' => [
        'updated' => 'Setting updated successfully.',
        'not_found' => 'Setting not found.',
    ],
    'appointment' => [
        'validation' => [
            'appointment_id' => [
                'required' => 'Appointment is required.',
                'integer' => 'Invalid appointment id.',
                'exists' => 'Appointment not found or you do not have access.',
            ],
            'offset_hours' => [
                'required' => 'Choose when you want to be reminded.',
                'integer' => 'The reminder lead time must be a whole number of hours.',
                'in' => 'That reminder lead time is not one of the available options.',
                'conflict' => 'Send either offset_hours or remind_at, not both.',
                'too_late' => 'That lead time has already passed for this appointment. Pick a shorter one.',
            ],
            'remind_at' => [
                'required' => 'Reminder time is required.',
                'date' => 'Reminder time format is invalid.',
                'after' => 'Reminder time must be in the future.',
                'before_appointment' => 'Reminder time must be before appointment time.',
            ],
            'appointment_cancelled' => 'You cannot create a reminder for a cancelled appointment.',
            'appointment_past' => 'You cannot create a reminder for a past or started appointment.',
        ],
        'success' => [
            'reminder_created' => 'Reminder created successfully.',
            'reminder_deleted' => 'Reminder turned off.',
        ],
        'errors' => [
            'cancelled' => 'You cannot create a reminder for a cancelled appointment.',
            'past' => 'You cannot create a reminder for a past appointment.',
            'not_found' => 'Appointment not found.',
            'reminder_not_found' => 'This appointment has no active reminder.',
        ],
        'server_error' => 'An error occurred while creating the reminder.',
    ]
];
