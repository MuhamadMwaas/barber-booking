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

        $max_services_per_booking = SettingsService::get('max_services_per_booking', 10);

        if (count($services) > $max_services_per_booking) {
            throw new InvalidArgumentException("Maximum {$max_services_per_booking} services per booking");
        }

        $bookingDate = Carbon::parse($date);

        if ($bookingDate->lt(Carbon::today())) {
            throw new InvalidArgumentException('Cannot book in the past');
        }

        // $max_booking_days = get_setting('max_booking_days', 30);
        $max_booking_days = intval(SettingsService::get('max_booking_days', 10));

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
        Carbon $endTime,
        bool $allowSameDayPast = false,
        bool $bypassAvailability = false
    ): void {
        $date = $startTime->format('Y-m-d');

        // Provider availability window — working day (#1), working hours (#2),
        // full-day time off (#3) and hourly time off (#4).
        //
        // Trusted staff "force booking" ($bypassAvailability = true) may skip
        // this ENTIRE window — that is exactly the requested feature: book a VIP
        // while the provider is on leave, outside working hours, or on a day the
        // shop/provider is normally off. It is the single, isolated decision
        // point for "is this slot within the provider's allowed window?", so the
        // relaxation can never leak into the rules below.
        //
        // The hard conflict check (#5) and the past-time guard (#6/#7) below are
        // OUTSIDE this branch and ALWAYS run — an override can never double-book
        // a busy provider, and the past-time policy is untouched. Likewise the
        // "provider offers the service" check lives in validateProviderOffersService()
        // (called earlier in the flow) and is never bypassed here.
        if (! $bypassAvailability) {
            $this->validateProviderScheduleWindow($provider, $startTime, $endTime);
        }

        // 5. Check for conflicting appointments
        $hasConflictingAppointment = Appointment::where('provider_id', $provider->id)
            ->whereDate('appointment_date', $date)
            // TODO: rmov created_status check and make job for cleaning unpaid bookings
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

        // 6 + 7. Past-time / minimum-advance guard.
        //   Isolated in validateNotInPast() so trusted staff paths can opt-in to
        //   same-day back-dating WITHOUT touching any rule above (provider hours,
        //   conflicts, time-off all stay intact).
        $this->validateNotInPast($startTime, $allowSameDayPast);
    }

    /**
     * Provider availability window: working day, working hours, and time-off.
     *
     * This is the set of checks (#1–#4) that the trusted-staff "force booking"
     * path is allowed to bypass. It is intentionally extracted so that bypassing
     * is a single, auditable `if (! $bypassAvailability)` decision and CANNOT
     * touch the conflict / past-time / offers-service rules.
     *
     * @throws InvalidArgumentException when the slot falls outside the window.
     */
    private function validateProviderScheduleWindow(
        User $provider,
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
    }

    /**
     * Past-time & minimum-advance guard for a single slot start.
     *
     * Default (customer-facing) behaviour — unchanged:
     *   - the slot may not start before "now"; and
     *   - it must respect the `book_buffer` minimum-advance window.
     *
     * Same-day-past mode ($allowSameDayPast = true) — opted into ONLY by trusted
     * staff paths (Staff Dashboard + Filament admin):
     *   - ANY time within the CURRENT day is accepted (past or future) with no
     *     buffer, so staff can record a walk-in that already started today; but
     *   - earlier calendar days stay blocked (a past day is never "today"), and
     *     future days fall through to the normal checks (buffer still applies).
     *
     * This is the single, isolated place that decides "is this start time in the
     * past?" — by design, so the relaxation can never leak into other rules.
     */
    private function validateNotInPast(Carbon $startTime, bool $allowSameDayPast = false): void
    {
        // Trusted staff back-dating: permitted only ever within today.
        if ($allowSameDayPast && $startTime->isToday()) {
            return;
        }

        // 6. Check time slot is not in the past
        if ($startTime->lt(Carbon::now())) {
            throw new InvalidArgumentException(
                "Cannot book time slot in the past"
            );
        }

        // 7. Check minimum advance booking time
        $book_buffer = intval(get_setting('book_buffer', 60));

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
     * Validate no duplicate booking for guest customers using phone number
     *
     * @param string|null $customerPhone
     * @param Carbon $startTime
     * @param array $serviceIds
     * @throws InvalidArgumentException
     */
    public function validateNoDuplicateBookingByPhone(
        ?string $customerPhone,
        Carbon $startTime,
        array $serviceIds
    ): void {

        if (empty($customerPhone)) {
            return;
        }

        $existingBooking = Appointment::where('customer_phone', $customerPhone)
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
        $max_daily_bookings = SettingsService::get('max_daily_bookings', 10);



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
