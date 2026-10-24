<?php

return [
    // Subjects
    'subject_customer' => 'Booking Confirmation — :number',
    'subject_company'  => 'New Booking Received — :number',

    // Intros
    'greeting'       => 'Hello :name,',
    'confirm_intro'  => 'Thank you for booking with :company. Your booking has been received successfully.',
    'company_intro'  => 'A new online booking has just been created. Details are below:',

    // Booking fields
    'booking_number' => 'Booking Number',
    'date'           => 'Date',
    'time'           => 'Time',
    'duration'       => 'Duration',
    'provider'       => 'Staff member',
    'payment_method' => 'Payment Method',
    'source'         => 'Booking Source',
    'notes'          => 'Notes',

    // Services table
    'services' => 'Services',
    'service'  => 'Service',
    'price'    => 'Price',
    'subtotal' => 'Subtotal',
    'tax'      => 'Tax',
    'total'    => 'Total price',

    // Customer block (company email)
    'customer' => 'Customer Details',
    'name'     => 'Name',
    'email'    => 'Email',
    'phone'    => 'Phone',

    // Split booking (one booking, several appointments)
    'appointments_heading' => 'Your :count appointments',
    'appointments_intro'   => 'Your services are at different times, so they are booked as separate appointments. Each one can be cancelled on its own.',
    'appointment_n'        => 'Appointment :n',
    'group_total'          => 'Total for all appointments',

    // Customer view (confirmation + reminder)
    'details_heading'   => 'Your booking details',
    'services_customer' => 'Your services',
    'payment_summary'   => 'Payment summary',
    'payment_methods'   => [
        'cash'   => 'Cash',
        'online' => 'Online',
    ],

    // Appointment reminder email (own layout, salon-approved wording)
    'reminder' => [
        'subject'      => ':company – Reminder of your appointment',
        'greeting'     => 'Hello :name,',
        'intro'        => 'We would like to remind you that you have an appointment at :company.',
        'details_lead' => 'Here are the details of your appointment:',
        'date'         => 'Date',
        'time'         => 'Time',
        'provider'     => 'Barber',
        'service'      => 'Service',
        'salon'        => 'Salon',
        'date_format'  => 'l, j F Y',
        'time_value'   => ':time',
        'closing'      => 'We look forward to your visit!',
        'signoff'      => 'Your :company Team',
        'tagline'      => "More than a haircut.\nYour look. Our goal.",
    ],

    // Footer
    'thanks'   => 'Thank you for choosing :company! We look forward to your visit.',
    'footer'   => 'This is an automatically generated email. Please do not reply directly to this message.',
];
