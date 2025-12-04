<?php

return [
    // Appointment Creation Validation Messages
    'appointment' => [
        'at_least_one_service' => 'At least one service must be selected',
        'select_customer' => 'Customer must be selected',
        'select_provider' => 'Service provider must be selected',
        'select_date' => 'Appointment date must be selected',
        'select_start_time' => 'Start time must be selected',
        'duration_greater_than_zero' => 'Total duration must be greater than zero',
        'service_not_found' => 'Service not found',

        // Success Messages
        'created_successfully' => 'Appointment created successfully',
        'booking_number' => 'Booking Number',

        // Error Titles
        'validation_error' => 'Validation Error',
        'creation_error' => 'Appointment Creation Error',
    ],

    // General Validation Messages
    'validation' => [
        'required' => 'This field is required',
        'invalid_data' => 'Invalid data provided',
    ],
];
