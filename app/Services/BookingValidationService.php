<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ProviderTimeOff;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\User;
use App\Enum\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;


class BookingValidationService
{

    public function validateBasicData(array $services, string $date): void
    {
        if (empty($services)) {
            throw new InvalidArgumentException('At least one service must be selected');
        }

        $max_services_per_booking =  SettingsService::get('max_services_per_booking', 10);

        if (count($services) > $max_services_per_booking) {
            throw new InvalidArgumentException("Maximum {$max_services_per_booking} services per booking");
        }

        $bookingDate = Carbon::parse($date);

        if ($bookingDate->lt(Carbon::today())) {
            throw new InvalidArgumentException('Cannot book in the past');
        }

        // $max_booking_days = get_setting('max_booking_days', 30);
        $max_booking_days=SettingsService::get('max_booking_days', 10);

        if ($bookingDate->gt(Carbon::today()->addDays($max_booking_days))) {
            throw new InvalidArgumentException('Cannot book more than ' . $max_booking_days . ' days in advance');
        }

        $serviceIds = array_column($services, 'service_id');
        if (count($serviceIds) !== count(array_unique($serviceIds))) {
            throw new InvalidArgumentException('Duplicate services are not allowed in the same booking');
        }
    }


    public function validateProviderOffersService(User $provider, Service $service): void
    {
        $offers = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->exists();

        if (!$offers) {
            throw new InvalidArgumentException(
                "Provider '{$provider->full_name}' does not offer service '{$service->name}'"
            );
        }

        if (!$provider->is_active) {
            throw new InvalidArgumentException("Provider '{$provider->full_name}' is not active");
        }

        if (!$service->is_active) {
            throw new InvalidArgumentException("Service '{$service->name}' is not active");
        }
    }


    public function validateSequentialTiming(?Carbon $previousEndTime, Carbon $currentStartTime, int $serviceIndex): void
    {
        if ($previousEndTime === null) {
            return;
        }

        if ($currentStartTime->lt($previousEndTime)) {
            throw new InvalidArgumentException(
                "Service at position {$serviceIndex} start time ({$currentStartTime->format('H:i')}) " .
                "must be after or equal to previous service end time ({$previousEndTime->format('H:i')}). " .
                "Services must be sequential."
            );
        }

        // Optional: Check for excessive gaps (more than 2 hours)
        if ($currentStartTime->diffInMinutes($previousEndTime) > 120) {
            // You can add a warning or log here
        }
    }


    public function validateTimeSlotAvailability(
        User $provider,
        Service $service,
        Carbon $startTime,
        Carbon $endTime
    ): void {
        $date = $startTime->format('Y-m-d');
        $dayOfWeek = $startTime->dayOfWeek;

        // 1. Check provider's work schedule
        $schedule = DB::table('provider_scheduled_works')
            ->where('user_id', $provider->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->first();

        if (!$schedule) {
            throw new InvalidArgumentException(
                "Provider '{$provider->full_name}' does not work on " . $startTime->format('l')
            );
        }

        // 2. Check time is within working hours
        $workStart = Carbon::parse($date . ' ' . $schedule->start_time);
        $workEnd = Carbon::parse($date . ' ' . $schedule->end_time);

        if ($startTime->lt($workStart) || $endTime->gt($workEnd)) {
            throw new InvalidArgumentException(
                "Time slot is outside provider's working hours " .
                "({$workStart->format('H:i')} - {$workEnd->format('H:i')})"
            );
        }

        // 3. Check for full day time off
        $hasFullDayOff = ProviderTimeOff::where('user_id', $provider->id)
            ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();

        if ($hasFullDayOff) {
            throw new InvalidArgumentException(
                "Provider '{$provider->full_name}' is not available on " . $startTime->format('Y-m-d')
            );
        }

        // 4. Check for hourly time off conflicts
        $hasHourlyTimeOff = ProviderTimeOff::where('user_id', $provider->id)
            ->where('type', ProviderTimeOff::TYPE_HOURLY)
            ->whereDate('start_date', $date)
            ->where(function ($query) use ($startTime, $endTime, $date) {
                $query->where(function ($q) use ($startTime, $endTime, $date) {
                    $q->whereRaw("TIME(CONCAT(?, ' ', start_time)) < ?", [$date, $endTime->format('H:i:s')])
                        ->whereRaw("TIME(CONCAT(?, ' ', end_time)) > ?", [$date, $startTime->format('H:i:s')]);
                });
            })
            ->exists();

        if ($hasHourlyTimeOff) {
            throw new InvalidArgumentException(
                "Provider has time off during the requested time slot"
            );
        }

        // 5. Check for conflicting appointments
        $hasConflictingAppointment = Appointment::where('provider_id', $provider->id)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::COMPLETED->value])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($hasConflictingAppointment) {
            throw new InvalidArgumentException(
                "Time slot {$startTime->format('H:i')} - {$endTime->format('H:i')} " .
                "is already booked for provider '{$provider->full_name}'"
            );
        }

        // 6. Check time slot is not in the past
        if ($startTime->lt(Carbon::now())) {
            throw new InvalidArgumentException(
                "Cannot book time slot in the past"
            );
        }

        // 7. Check minimum advance booking time
        $book_buffer = get_setting('book_buffer', 60);

        if ($startTime->lt(Carbon::now()->addMinutes($book_buffer))) {
            throw new InvalidArgumentException(
                "Booking must be at least {$book_buffer} minutes in advance"
            );
        }
    }

    /**
     * Validate no duplicate booking
     */
    public function validateNoDuplicateBooking(User $customer, Carbon $startTime, array $serviceIds): void
    {
        $existingBooking = Appointment::where('customer_id', $customer->id)
            ->where('start_time', $startTime)
            ->whereIn('status', [AppointmentStatus::PENDING->value])
            ->whereHas('services', function ($query) use ($serviceIds) {
                $query->whereIn('services.id', $serviceIds);
            })
            ->exists();

        if ($existingBooking) {
            throw new InvalidArgumentException(
                'You already have a booking for the same time and services'
            );
        }
    }

    /**
     * Validate daily booking limit
     */
    public function validateDailyBookingLimit(User $customer, string $date): void
    {
        // $max_daily_bookings = get_setting('max_daily_bookings', null);
        $max_daily_bookings=SettingsService::get('max_daily_bookings', 10);



        if ($max_daily_bookings) {
            $todayBookingsCount = Appointment::where('customer_id', $customer->id)
                ->whereDate('appointment_date', $date)
                ->whereIn('status', [AppointmentStatus::PENDING->value])
                ->count();

            if ($todayBookingsCount >= $max_daily_bookings) {
                throw new InvalidArgumentException(
                    "Maximum {$max_daily_bookings} bookings per day reached"
                );
            }
        }
    }
}
