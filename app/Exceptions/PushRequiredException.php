<?php

namespace App\Exceptions;

/**
 * Thrown by BookingService::addServiceToBooking() when the new service
 * cannot fit without pushing one or more subsequent bookings of the
 * same provider.
 *
 * The caller (StaffDashboard Livewire component) catches this and shows
 * the push-preview modal. If the user confirms, it re-invokes
 * addServiceToBooking() with apply_push=true.
 */
class PushRequiredException extends \RuntimeException
{
    /**
     * @param array $plan The push plan produced by PushBookingsService::planPushFrom().
     *                    Each entry: [
     *                      'appointment_id', 'appointment_number', 'customer_name',
     *                      'has_customer_account', 'original_start', 'original_end',
     *                      'new_start', 'new_end', 'push_minutes',
     *                    ]
     */
    public function __construct(public array $plan)
    {
        parent::__construct('Push required to fit new service');
    }
}
