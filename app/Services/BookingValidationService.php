<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\ProviderTimeOff;
use App\Models\Service;
use App\Models\User;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            throw new InvalidArgumentException('Cannot book more than '.$max_booking_days.' days in advance');
        }

        $serviceIds = array_column($services, 'service_id');
        if (count($serviceIds) !== count(array_unique($serviceIds))) {
            throw new InvalidArgumentException('Duplicate services are not allowed in the same booking');
        }
    }

    /**
     * @param  User|null     $provider            null when the id resolved to nothing
     * @param  Service|null  $service             null when the id resolved to nothing
     * @param  int|null      $requestedProviderId the id that was asked for, for the log
     * @param  int|null      $requestedServiceId  the id that was asked for, for the log
     */
    public function validateProviderOffersService(
        ?User $provider,
        ?Service $service,
        ?int $requestedProviderId = null,
        ?int $requestedServiceId = null,
    ): void {
        // Nullable on purpose. Callers resolve ids into models before getting here,
        // and that lookup can come back empty — most easily because the row was
        // soft-deleted, since `exists:` rules query the table raw and match deleted
        // rows that Eloquent's global scope then hides. Typed non-null parameters
        // turned that into a TypeError, which is an Error rather than an Exception
        // and so escaped every catch on the way out: a bare 500 (BOOK-09).
        //
        // A missing row is rejected exactly like an unavailable pair, and for the
        // same reason as the cases below: telling the caller WHICH id does not
        // exist hands them a way to enumerate services and users.
        if ($provider === null || $service === null) {
            $this->rejectProviderServicePair(
                $provider?->id ?? $requestedProviderId,
                $service?->id ?? $requestedServiceId,
                $provider === null && $service === null
                    ? 'provider_and_service_not_found'
                    : ($provider === null ? 'provider_not_found' : 'service_not_found'),
            );
        }

        // This method is also called by trusted staff flows that do not pass through
        // BookingCreateRequest. Keep the authorization invariant here as a second
        // boundary: a bookable provider must be an active user with provider role.
        if (! $provider->is_active || ! $provider->hasRole('provider')) {
            $this->rejectProviderServicePair($provider->id, $service->id, 'user_is_not_an_active_provider');
        }

        if (! $service->is_active) {
            $this->rejectProviderServicePair($provider->id, $service->id, 'service_is_inactive');
        }

        $offers = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->exists();

        if (! $offers) {
            $this->rejectProviderServicePair($provider->id, $service->id, 'provider_service_pair_is_unavailable');
        }
    }

    /**
     * Keep provider/service identifiers in the private log context while returning
     * one public message that cannot be used to enumerate users or infer names.
     *
     * Takes ids rather than models so the "row not found" case can report which id
     * was asked for — the whole point is that the caller learns nothing while the
     * log learns everything.
     */
    private function rejectProviderServicePair(?int $providerId, ?int $serviceId, string $reason): never
    {
        Log::notice('Booking provider/service validation failed.', [
            'reason' => $reason,
            'provider_id' => $providerId,
            'service_id' => $serviceId,
        ]);

        throw new InvalidArgumentException(__('booking.provider_unavailable_for_service'));
    }

    public function validateSequentialTiming(?Carbon $previousEndTime, Carbon $currentStartTime, int $serviceIndex): void
    {
        if ($previousEndTime === null) {
            return;
        }

        if ($currentStartTime->lt($previousEndTime)) {
            throw new InvalidArgumentException(
                "Service at position {$serviceIndex} start time ({$currentStartTime->format('H:i')}) ".
                "must be after or equal to previous service end time ({$previousEndTime->format('H:i')}). ".
                'Services must be sequential.'
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

        // 5. Check for conflicting appointments.
        $this->assertNoConflictingAppointment($provider, $startTime, $endTime);

        // 6 + 7. Past-time / minimum-advance guard.
        //   Isolated in validateNotInPast() so trusted staff paths can opt-in to
        //   same-day back-dating WITHOUT touching any rule above (provider hours,
        //   conflicts, time-off all stay intact).
        $this->validateNotInPast($startTime, $allowSameDayPast);
    }

    /**
     * THE conflict check: is this provider already occupied in [start, end)?
     *
     * Public and standalone because it has more than one caller. Every path that
     * puts an appointment onto a provider's calendar — creating a booking,
     * moving one in StaffDashboard, adding a service to an existing booking —
     * must ask this exact question, or they drift apart the way the availability
     * and booking layers once did (BOOK-01).
     *
     * $ignoreAppointmentId exists for the move case: an appointment must not be
     * considered to be in conflict with itself when its own time is edited.
     *
     * MUST be called inside the booking transaction, after the provider row is
     * locked — see BookingLockService. Calling it outside a lock only tells you
     * the slot was free a moment ago (BOOK-02).
     *
     * @throws SlotUnavailableException when the window is taken.
     */
    public function assertNoConflictingAppointment(
        User $provider,
        Carbon $startTime,
        Carbon $endTime,
        ?int $ignoreAppointmentId = null,
    ): void {
        $hasConflict = Appointment::where('provider_id', $provider->id)
            ->whereDate('appointment_date', $startTime->format('Y-m-d'))
            ->blocksProviderTime()
            ->overlapping($startTime, $endTime)
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->exists();

        if ($hasConflict) {
            throw new SlotUnavailableException(__('booking.time_slot_unavailable'));
        }
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

        if (! $schedule) {
            throw new InvalidArgumentException(__('booking.provider_unavailable_on_date'));
        }

        // 2. Check time is within working hours
        $workStart = Carbon::parse($date.' '.$schedule->start_time);
        $workEnd = Carbon::parse($date.' '.$schedule->end_time);

        if ($startTime->lt($workStart) || $endTime->gt($workEnd)) {
            throw new InvalidArgumentException(
                "Time slot is outside provider's working hours ".
                "({$workStart->format('H:i')} - {$workEnd->format('H:i')})"
            );
        }

        // 3. Check for full day time off.
        //    scopeCoveringDate() is the shared range rule: it honours a multi-day
        //    leave and reads a null end_date as "start_date only". The old
        //    `end_date >= $date` silently dropped null-ended rows, because in SQL
        //    `NULL >= '2026-09-10'` is UNKNOWN, not false (BOOK-04).
        $hasFullDayOff = ProviderTimeOff::where('user_id', $provider->id)
            ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
            ->coveringDate($date)
            ->exists();

        if ($hasFullDayOff) {
            throw new InvalidArgumentException(__('booking.provider_unavailable_on_date'));
        }

        // 4. Check for hourly time off conflicts.
        //    Candidates are filtered by the shared date scope and then evaluated
        //    with blocksWindow(), the same call ServiceAvailabilityService makes,
        //    so the two layers cannot disagree about which hours a leave eats.
        //    Previously this matched only `start_date = $date`, so every day of a
        //    multi-day hourly leave except the first was invisible here while the
        //    availability layer was hiding all of them.
        $hourlyTimeOffs = ProviderTimeOff::where('user_id', $provider->id)
            ->where('type', ProviderTimeOff::TYPE_HOURLY)
            ->coveringDate($date)
            ->get();

        foreach ($hourlyTimeOffs as $timeOff) {
            if ($timeOff->blocksWindow($startTime, $endTime)) {
                throw new InvalidArgumentException(
                    'Provider has time off during the requested time slot'
                );
            }
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
                'Cannot book time slot in the past'
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
     * The customer cannot be in two places at once.
     *
     * This replaced a check that asked a much narrower question — "do you already
     * have a booking starting at this EXACT instant that shares at least one
     * service?" — which let two real problems through (BOOK-06):
     *
     *   - same instant, different provider, no shared service: accepted, so the
     *     customer held two simultaneous appointments;
     *   - partial overlap (10:00-11:00 already booked, new booking at 10:30):
     *     accepted, because the start times were not identical.
     *
     * The rule is now simply time overlap, and it says nothing about services or
     * providers: booking a haircut at 10:00 and a face mask at 10:30 is fine when
     * the haircut ends at 10:30, and refused when it does not. Overlap is
     * half-open, so a booking may start exactly when the previous one ends.
     *
     * Identity spans both shapes a customer can take. A registered customer is
     * matched by id AND by phone, so somebody who booked once as a guest and once
     * from their account is still recognised as one person; guests are matched on
     * the phone number alone, through PhoneNumber's comparison key rather than
     * raw string equality.
     *
     * @param  int|null  $ignoreAppointmentId  the appointment being moved, which
     *                                         must not conflict with itself.
     *
     * @throws InvalidArgumentException when the customer is already busy.
     */
    public function assertCustomerIsFree(
        ?User $customer,
        ?string $customerPhone,
        Carbon $startTime,
        Carbon $endTime,
        ?int $ignoreAppointmentId = null,
    ): void {
        $phoneKey = PhoneNumber::key($customerPhone ?? $customer?->phone);

        // Nothing to match on: an anonymous booking with no phone cannot be tied
        // to any earlier one, and guessing would collide every such customer.
        if ($customer === null && $phoneKey === null) {
            return;
        }

        // Candidates are the appointments that occupy this exact window on this
        // day - across all providers, since the clash is with the customer, not a
        // chair. Anchoring on the window keeps the set tiny (at most one per
        // provider), which is what makes it affordable to compare phone keys in
        // PHP instead of trying to normalise phone numbers in SQL.
        $candidates = Appointment::query()
            ->whereDate('appointment_date', $startTime->format('Y-m-d'))
            ->blocksProviderTime()
            ->overlapping($startTime, $endTime)
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->get(['id', 'customer_id', 'customer_phone']);

        foreach ($candidates as $candidate) {
            $sameAccount = $customer !== null
                && (int) $candidate->customer_id === (int) $customer->id;

            $samePhone = $phoneKey !== null
                && $phoneKey === PhoneNumber::key($candidate->getRawOriginal('customer_phone'));

            if ($sameAccount || $samePhone) {
                throw new InvalidArgumentException(__('booking.customer_already_booked'));
            }
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
