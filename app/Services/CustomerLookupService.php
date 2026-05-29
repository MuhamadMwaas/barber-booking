<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomerLookupService
{
    public function searchRegisteredCustomers(string $q, int $limit = 25): Collection
    {
        return User::whereHas('roles', fn ($query) => $query->where('name', 'customer'))
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name',  'like', "%{$q}%")
                      ->orWhere('email',      'like', "%{$q}%")
                      ->orWhere('phone',      'like', "%{$q}%");
            })
            ->withCount('customerAppointments')
            ->orderBy('first_name')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);
    }

    public function searchGuestAppointments(string $q, int $limit = 50): Collection
    {
        return Appointment::whereNull('customer_id')
            ->where(function ($query) use ($q) {
                // Must query raw columns — not the accessor — for guests
                $query->where('customer_name',  'like', "%{$q}%")
                      ->orWhere('customer_email', 'like', "%{$q}%")
                      ->orWhere('customer_phone', 'like', "%{$q}%");
            })
            ->with([
                'provider:id,first_name,last_name',
                'services_record:id,appointment_id,service_name,sequence_order',
            ])
            ->withCount('colorRecords')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getCustomerAppointments(int $userId, int $limit = 100): Collection
    {
        return Appointment::where('customer_id', $userId)
            ->with([
                'provider:id,first_name,last_name',
                'services_record:id,appointment_id,service_name,sequence_order',
            ])
            ->withCount('colorRecords')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->limit($limit)
            ->get();
    }
}
