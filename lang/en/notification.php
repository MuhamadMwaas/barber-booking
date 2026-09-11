<?php

return [
    'new_appointment' => 'New Appointment',
    'new_appointment_message' => 'A new booking for :service has been created on :date at :time. Payment method: :payment_type.',

    // Sent when a booking gets rescheduled by staff because of a service addition on an earlier booking.
    'booking_pushed_title' => 'Booking Rescheduled',
    'booking_pushed_body' => 'Your booking #:number has been moved to :time (delayed by :minutes minutes).',
    // Raised when a customer cancels for the second time (or beyond) inside a week.
    'repeat_cancellation_title' => 'Repeat cancellations: :customer',
    'repeat_cancellation_body' => ':customer has cancelled :count bookings in the last :days days. Most recent: #:number.',
    'repeat_cancellation_view_customer' => 'View customer',
];
