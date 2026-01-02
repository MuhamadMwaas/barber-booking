<?php

return [
    'validation' => [
        'failed' => 'Invalid data provided.',
    ],
    'cancel' => 'Cancel',
    'appointment' => [
        'validation' => [
            'appointment_id' => [
                'required' => 'Appointment is required.',
                'integer' => 'Invalid appointment id.',
                'exists' => 'Appointment not found or you do not have access.',
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
        'errors' => [
            'cancelled' => 'You cannot create a reminder for a cancelled appointment.',
            'past' => 'You cannot create a reminder for a past appointment.',
            'not_found' => 'Appointment not found.',
        ],

        'success' => [
            'reminder_created' => 'Reminder created successfully.',
        ],

        'server_error' => 'An error occurred while creating the reminder.',
    ]
];
