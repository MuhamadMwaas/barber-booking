<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use App\Models\Appointment;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\SalonSchedule;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceAvailabilityService
{

    private const SLOT_BUFFER = 0;


    private const CACHE_DURATION = 1;

    // Machine readable reasons returned to the app when a provider has no slots.
    public const REASON_AVAILABLE = 'available';
    public const REASON_NOT_WORKING_DAY = 'not_working_day';
    public const REASON_ON_LEAVE = 'on_leave';
    public const REASON_FULLY_BOOKED = 'fully_booked';
    public const REASON_OUTSIDE_BOOKING_WINDOW = 'outside_booking_window';
    public const REASON_SALON_CLOSED = 'salon_closed';

    // Day-level reasons used only by the month views.
    public const REASON_PAST = 'past';
    public const REASON_NO_AVAILABILITY = 'no_availability';

    /**
     * The two booking limits this service has to honour so that it never offers a
     * slot {@see \App\Services\BookingValidationService} would go on to reject:
     *
     *  - `book_buffer`      minimum minutes between "now" and the slot start;
     *  - `max_booking_days` how far ahead a date may be booked at all.
     *
     * Both are read per call rather than cached on the instance because a salon
     * admin can change them at any moment from the settings screen.
     */
    private function bookBufferMinutes(): int
    {
        return max(0, (int) get_setting('book_buffer', 60));
    }

    /**
     * The earliest instant a slot may start. Slots before it are dropped because
     * the booking request would fail the minimum-advance check.
     */
    private function earliestBookableTime(): Carbon
    {
        return Carbon::now()->addMinutes($this->bookBufferMinutes());
    }

    /**
     * The last date that may be booked. Mirrors the `max_booking_days` guard in
     * BookingValidationService::validateBasicData().
     */
    public function lastBookableDate(): Carbon
    {
        return Carbon::today()->addDays(max(0, (int) get_setting('max_booking_days', 10)));
    }

    /**
     * @param int $serviceId
     * @param string $date
     * @param int|null $branchId
     * @return array
     */
    public function getAvailableSlotsByDate(int $serviceId, string $date, ?int $branchId = null): array
    {
        $cacheKey = "availability_service_{$serviceId}_date_{$date}_branch_" . ($branchId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($serviceId, $date, $branchId) {
            $service = Service::findOrFail($serviceId);
            $carbonDate = Carbon::parse($date);

            if ($carbonDate->lt(Carbon::today())) {
                throw new InvalidArgumentException('Cannot get availability for past dates');
            }

            //   get active provider foth this service
            $providers = $this->bookableProviders($service, $branchId);
            $calendar = $this->loadCalendarData($providers, $carbonDate, $carbonDate);

            // get free times for each providers
            $providersWithSlots = $providers->map(function ($provider) use ($service, $carbonDate, $calendar) {
                $availability = $this->getProviderDayAvailability($provider, $service, $carbonDate, $calendar);

                return [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->full_name,
                    'provider_avatar' => $provider->avatar,
                    'branch' => $this->formatBranchData($provider->branch),
                    'service_pricing' => $this->servicePricing($service, $provider->pivot->custom_price),
                    'is_available' => $availability['is_available'],
                    'reason_code' => $availability['reason_code'],
                    'available_slots' => $availability['available_slots'],
                ];
            })->filter(function ($provider) {
                // Providers on leave / off duty / fully booked are never listed as bookable.
                return $provider['is_available'] && count($provider['available_slots']) > 0;
            })->values();

            return [
                'service' => $this->formatService($service),
                'date' => $carbonDate->format('Y-m-d'),
                'day_name' => $carbonDate->format('l'),
                'formatted_date' => $carbonDate->format('l, F d, Y'),
                'is_today' => $carbonDate->isToday(),
                'is_tomorrow' => $carbonDate->isTomorrow(),
                // Surfaced so the app can grey out dates the booking call would
                // refuse instead of discovering the limit only on submit.
                'last_bookable_date' => $calendar['last_bookable_date']->format('Y-m-d'),
                'total_providers' => $providersWithSlots->count(),
                'providers' => $providersWithSlots,
            ];
        });
    }

    /**
     *
     * @param int $serviceId
     * @param int $providerId
     * @param string $date
     * @return array
     */
    public function getProviderAvailableSlotsByDate(int $serviceId, int $providerId, string $date): array
    {
        $cacheKey = "availability_service_{$serviceId}_provider_{$providerId}_date_{$date}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($serviceId, $providerId, $date) {
            $service = Service::findOrFail($serviceId);
            User::findOrFail($providerId);
            $carbonDate = Carbon::parse($date);

            // Validate date
            if ($carbonDate->lt(Carbon::today())) {
                throw new \InvalidArgumentException('Cannot get availability for past dates');
            }

            // Validate provider offers this service — and is someone the booking
            // call would accept at all (active provider, active service).
            $provider = $this->bookableProviders($service, providerId: $providerId)->first();

            if (! $provider) {
                throw new \InvalidArgumentException('Provider does not offer this service');
            }

            $availability = $this->getProviderDayAvailability($provider, $service, $carbonDate);
            $slots = $availability['available_slots'];

            return [
                'is_available' => $availability['is_available'],
                'reason_code' => $availability['reason_code'],
                'unavailable_reason' => $availability['reason'],
                'leave_start_date' => $availability['leave_start_date'] ?? null,
                'leave_end_date' => $availability['leave_end_date'] ?? null,
                'provider' => [
                    'id' => $provider->id,
                    'name' => $provider->full_name,
                    'avatar' => $provider->avatar,
                    'phone' => $provider->phone,
                    'branch' => $this->formatBranchData($provider->branch),
                ],
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $this->getEffectiveDuration($provider, $service),
                    'formatted_duration' => $this->formatDuration($this->getEffectiveDuration($provider, $service)),
                ],
                'pricing' => $this->servicePricing($service, $provider->pivot->custom_price),
                'date' => $carbonDate->format('Y-m-d'),
                'day_name' => $carbonDate->format('l'),
                'formatted_date' => $carbonDate->format('l, F d, Y'),
                'total_slots' => count($slots),
                'available_slots' => $slots,
            ];
        });
    }

    /**
     * One provider's month: which days of it still have at least one bookable
     * slot for this service.
     *
     * @param string|null $month `Y-m`; null means the current month
     *
     * @throws InvalidArgumentException when the provider cannot take this service
     *                                  or the month is outside the booking window
     */
    public function getProviderMonthAvailability(int $serviceId, int $providerId, ?string $month = null): array
    {
        $service = Service::findOrFail($serviceId);
        $provider = $this->bookableProviders($service, providerId: $providerId)->first();

        if (! $provider) {
            // One message for "no such user", "not a provider", "inactive" and
            // "does not offer it" — the same non-enumerable rule the booking call
            // applies in BookingValidationService::rejectProviderServicePair().
            throw new InvalidArgumentException(__('booking.provider_unavailable_for_service'));
        }

        $window = $this->monthWindow($month);
        $calendar = $this->loadCalendarData(collect([$provider]), $window['from'], $window['to']);

        $days = $this->eachMonthDay(
            $window,
            function (Carbon $date) use ($provider, $service, $calendar) {
                $availability = $this->getProviderDayAvailability($provider, $service, $date, $calendar);
                $slots = $availability['available_slots'];

                return [
                    'is_available' => $availability['is_available'],
                    'reason_code' => $availability['reason_code'],
                    'available_slots_count' => count($slots),
                    'first_available_slot' => $slots[0]['start_time'] ?? null,
                ];
            },
            ['available_slots_count' => 0, 'first_available_slot' => null],
        );

        return [
            'service' => $this->formatService($service),
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->full_name,
                'avatar' => $provider->avatar,
                'branch' => $this->formatBranchData($provider->branch),
            ],
            'pricing' => $this->servicePricing($service, $provider->pivot->custom_price),
            ...$this->monthSummary($window, $days),
        ];
    }

    /**
     * A service's month: which days have at least one provider with a bookable
     * slot, and who those providers are. Slots themselves are not listed — the
     * app fetches them for the day the customer taps via getAvailableSlotsByDate().
     *
     * @param string|null $month `Y-m`; null means the current month
     *
     * @throws InvalidArgumentException when the month is outside the booking window
     */
    public function getServiceMonthAvailability(int $serviceId, ?string $month = null, ?int $branchId = null): array
    {
        $service = Service::findOrFail($serviceId);
        $window = $this->monthWindow($month);
        $providers = $this->bookableProviders($service, $branchId);
        $calendar = $this->loadCalendarData($providers, $window['from'], $window['to']);

        // Per provider, not per day: the price does not change from one day to the next.
        $pricing = $providers->mapWithKeys(fn (User $provider) => [
            $provider->id => $this->servicePricing($service, $provider->pivot->custom_price),
        ]);

        $days = $this->eachMonthDay(
            $window,
            function (Carbon $date) use ($providers, $service, $calendar, $pricing) {
                $available = [];
                $reasons = [];

                foreach ($providers as $provider) {
                    $availability = $this->getProviderDayAvailability($provider, $service, $date, $calendar);
                    $reasons[] = $availability['reason_code'];

                    if (! $availability['is_available']) {
                        continue;
                    }

                    $available[] = [
                        'provider_id' => $provider->id,
                        'provider_name' => $provider->full_name,
                        'provider_avatar' => $provider->avatar,
                        'service_pricing' => $pricing[$provider->id],
                        'available_slots_count' => count($availability['available_slots']),
                        'first_available_slot' => $availability['available_slots'][0]['start_time'],
                    ];
                }

                // The per-provider reasons collapse into one day-level code. Only
                // "the salon is closed" is worth naming on its own: it is the one
                // reason that applies to everybody and that the customer can act on
                // ("come another day"). Anything else is simply no availability.
                $allClosed = $reasons !== [] && array_unique($reasons) === [self::REASON_SALON_CLOSED];

                return [
                    'is_available' => $available !== [],
                    'reason_code' => match (true) {
                        $available !== [] => self::REASON_AVAILABLE,
                        $allClosed => self::REASON_SALON_CLOSED,
                        default => self::REASON_NO_AVAILABILITY,
                    },
                    'providers_count' => count($available),
                    'providers' => $available,
                ];
            },
            ['providers_count' => 0, 'providers' => []],
        );

        return [
            'service' => $this->formatService($service),
            'branch_id' => $branchId,
            ...$this->monthSummary($window, $days),
        ];
    }

    /**
     * Get available slots for multiple dates (for calendar view)
     *
     * @param int $serviceId
     * @param int|null $providerId
     * @param string $startDate
     * @param string $endDate
     * @param int|null $branchId
     * @return array
     */
    public function getAvailabilityCalendar(
        int $serviceId,
        ?int $providerId = null,
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): array {
        $service = Service::findOrFail($serviceId);
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Limit to 31 days max
        if ($start->diffInDays($end) > 31) {
            throw new \InvalidArgumentException('Date range cannot exceed 31 days');
        }

        $calendar = [];
        $current = $start->copy();
        $lastBookableDate = $this->lastBookableDate();

        while ($current->lte($end)) {
            if ($current->gte(Carbon::today())) {
                $dateStr = $current->format('Y-m-d');

                if ($providerId) {
                    $dayData = $this->getProviderAvailableSlotsByDate($serviceId, $providerId, $dateStr);
                    $availableSlots = $dayData['total_slots'];
                    $reasonCode = $dayData['reason_code'];
                } else {
                    $dayData = $this->getAvailableSlotsByDate($serviceId, $dateStr, $branchId);
                    $availableSlots = $dayData['providers']->sum(function ($provider) {
                        return count($provider['available_slots']);
                    });
                    // Without a provider the per-provider reasons collapse into one
                    // day-level code. "Beyond the booking window" has to win over
                    // the generic empty-day code, otherwise a date nobody is even
                    // allowed to book reads as `fully_booked` in the app.
                    $reasonCode = match (true) {
                        $availableSlots > 0 => self::REASON_AVAILABLE,
                        $current->gt($lastBookableDate) => self::REASON_OUTSIDE_BOOKING_WINDOW,
                        default => self::REASON_FULLY_BOOKED,
                    };
                }

                $calendar[] = [
                    'date' => $dateStr,
                    'day_name' => $current->format('D'),
                    'day_number' => $current->day,
                    'is_today' => $current->isToday(),
                    'is_available' => $availableSlots > 0,
                    'reason_code' => $reasonCode,
                    'available_slots_count' => $availableSlots,
                ];
            }

            $current->addDay();
        }

        return [
            'service_id' => $serviceId,
            'provider_id' => $providerId,
            'period' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'month_name' => $start->format('F Y'),
            ],
            'calendar' => $calendar,
        ];
    }

    /**
     * Resolve a provider's availability for a single day, including WHY the
     * provider is unavailable so the app can hide providers that are on leave
     * instead of treating an empty slot list as a bookable provider.
     *
     * $calendar is what loadCalendarData() returns. The single-day endpoints load
     * one day for one provider; the month views load the whole month for every
     * provider at once and call this for each day — both run the identical rules.
     *
     * @param User $provider
     * @param Service $service
     * @param Carbon $date
     * @param array|null $calendar
     * @return array{is_available: bool, reason_code: string, reason: string|null, available_slots: array}
     */
    private function getProviderDayAvailability(User $provider, Service $service, Carbon $date, ?array $calendar = null): array
    {
        $calendar ??= $this->loadCalendarData(collect([$provider]), $date, $date);

        // Checked before the work schedule: a date past the booking window can
        // never be booked no matter how free the provider looks on it.
        $lastBookableDate = $calendar['last_bookable_date'];

        if ($date->gt($lastBookableDate)) {
            return $this->unavailable(
                self::REASON_OUTSIDE_BOOKING_WINDOW,
                'Cannot book more than ' . (int) get_setting('max_booking_days', 10) . ' days in advance',
                ['last_bookable_date' => $lastBookableDate->format('Y-m-d')]
            );
        }

        $dayOfWeek = $date->dayOfWeek;

        // Same rule, same order as BookingValidationService::validateProviderScheduleWindow().
        if (SalonSchedule::isClosedFor($provider->branch_id, $dayOfWeek, $calendar['salon_schedules'])) {
            return $this->unavailable(
                self::REASON_SALON_CLOSED,
                'The salon is closed on ' . $date->format('l')
            );
        }

        $workSchedule = $calendar['schedules']->get($provider->id, collect())
            ->firstWhere('day_of_week', $dayOfWeek);

        if (!$workSchedule) {
            return $this->unavailable(
                self::REASON_NOT_WORKING_DAY,
                "Provider '{$provider->full_name}' does not work on " . $date->format('l')
            );
        }

        // blockedWindowOn() is the per-day half of scopeCoveringDate(): the rows
        // were loaded for the whole range, this keeps the ones touching $date.
        $leaves = $calendar['time_offs']->get($provider->id, collect())
            ->filter(fn (ProviderTimeOff $timeOff) => $timeOff->blockedWindowOn($date) !== null);

        $fullDayTimeOff = $leaves->first(fn (ProviderTimeOff $timeOff) => $timeOff->isFullDay());

        if ($fullDayTimeOff) {
            return $this->unavailable(
                self::REASON_ON_LEAVE,
                "Provider '{$provider->full_name}' is on leave on " . $date->format('Y-m-d'),
                [
                    'leave_start_date' => Carbon::parse($fullDayTimeOff->start_date)->format('Y-m-d'),
                    'leave_end_date' => Carbon::parse($fullDayTimeOff->end_date ?? $fullDayTimeOff->start_date)->format('Y-m-d'),
                ]
            );
        }

        // Get service duration
        $serviceDuration = $this->getEffectiveDuration($provider, $service);

        // Generate time slots
        $slots = $this->generateTimeSlots(
            $date,
            $workSchedule,
            $serviceDuration,
            $calendar['appointments']->get($provider->id . '|' . $date->format('Y-m-d'), collect()),
            $leaves->filter(fn (ProviderTimeOff $timeOff) => $timeOff->isHourly()),
            $calendar['earliest_start']
        );

        if (empty($slots)) {
            return $this->unavailable(
                self::REASON_FULLY_BOOKED,
                "Provider '{$provider->full_name}' has no free slots on " . $date->format('Y-m-d')
            );
        }

        return [
            'is_available' => true,
            'reason_code' => self::REASON_AVAILABLE,
            'reason' => null,
            'available_slots' => $slots,
        ];
    }

    /**
     * Everything getProviderDayAvailability() reads, for $providers over
     * [$from, $to], in a fixed handful of queries.
     *
     * Asking day by day used to cost ~4 queries per provider per day — about a
     * thousand for one month of a busy service. Loading the range once and
     * deciding per day in memory keeps the rules in one place AND the cost flat.
     *
     * The two settings are snapshotted here too: get_setting() is a query, and a
     * month of days would otherwise re-read them for every provider and day.
     */
    private function loadCalendarData(Collection $providers, Carbon $from, Carbon $to): array
    {
        $providerIds = $providers->pluck('id')->all();

        return [
            'last_bookable_date' => $this->lastBookableDate(),
            'earliest_start' => $this->earliestBookableTime(),
            // Ordered by id so a duplicate (provider, weekday) row resolves the
            // way the single-row `->first()` lookup in the booking layer does.
            'schedules' => ProviderScheduledWork::whereIn('user_id', $providerIds)
                ->where('is_work_day', true)
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->groupBy('user_id'),
            'time_offs' => ProviderTimeOff::whereIn('user_id', $providerIds)
                ->touchingRange($from->format('Y-m-d'), $to->format('Y-m-d'))
                ->get()
                ->groupBy('user_id'),
            // Delegates to Appointment::scopeBlocksProviderTime() — the same scope
            // the booking layer enforces — so a slot is never shown as free and
            // then rejected on submit, and never hidden with nothing real behind it.
            'appointments' => Appointment::whereIn('provider_id', $providerIds)
                ->whereDate('appointment_date', '>=', $from)
                ->whereDate('appointment_date', '<=', $to)
                ->blocksProviderTime()
                ->select('provider_id', 'appointment_date', 'start_time', 'end_time')
                ->get()
                ->groupBy(fn (Appointment $appointment) => $appointment->provider_id . '|' . $appointment->appointment_date->format('Y-m-d')),
            'salon_schedules' => SalonSchedule::all(),
        ];
    }

    /**
     * Providers the booking call would accept for this service: an active user
     * with the provider role, an active link to the service, an active service.
     *
     * The same conditions as BookingValidationService::validateProviderOffersService().
     * The role and the service's own flag used to be missing here, so availability
     * could offer a provider or service that booking then refused.
     *
     * `activeProviders()` joins `provider_service`, and BOTH that pivot and the
     * `users` table carry an `is_active` column — so every column added here has
     * to be table-qualified or MySQL rejects the query as ambiguous.
     */
    private function bookableProviders(Service $service, ?int $branchId = null, ?int $providerId = null): Collection
    {
        if (! $service->is_active) {
            return collect();
        }

        return $service->bookableProviders()
            ->when($branchId, fn ($query) => $query->where('users.branch_id', $branchId))
            ->when($providerId, fn ($query) => $query->where('users.id', $providerId))
            ->with('branch')
            ->get();
    }

    /**
     * The month asked for, clipped to what can actually be booked.
     *
     *  - `start` / `end`  the calendar month, every day of which is returned;
     *  - `from` / `to`    the bookable part of it (today .. last bookable date),
     *                     the only days whose data is loaded and evaluated.
     *
     * A month entirely before today or entirely past the booking window is
     * refused rather than returned as thirty greyed-out days: the app gets
     * `previous_month` / `next_month` and never has a reason to ask for one.
     */
    private function monthWindow(?string $month): array
    {
        $today = Carbon::today();
        $lastBookableDate = $this->lastBookableDate();

        $start = $month === null
            ? $today->copy()->startOfMonth()
            : Carbon::createFromFormat('!Y-m', $month);

        if ($start->lt($today->copy()->startOfMonth()) || $start->gt($lastBookableDate->copy()->startOfMonth())) {
            throw new InvalidArgumentException(__('booking.month_outside_booking_window'));
        }

        $end = $start->copy()->endOfMonth()->startOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'from' => ($start->lt($today) ? $today : $start)->copy(),
            'to' => ($end->gt($lastBookableDate) ? $lastBookableDate : $end)->copy(),
            'today' => $today,
            'last_bookable_date' => $lastBookableDate,
        ];
    }

    /**
     * One entry per day of the month. Days outside the bookable part are filled
     * in without asking $resolve: before today → `past`, after the last bookable
     * date → `outside_booking_window`, each with $emptyDay's fields.
     */
    private function eachMonthDay(array $window, Closure $resolve, array $emptyDay): array
    {
        $days = [];

        for ($date = $window['start']->copy(); $date->lte($window['end']); $date->addDay()) {
            $day = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('D'),
                'day_number' => $date->day,
                'is_today' => $date->isToday(),
            ];

            $resolved = match (true) {
                $date->lt($window['from']) => ['is_available' => false, 'reason_code' => self::REASON_PAST] + $emptyDay,
                $date->gt($window['to']) => ['is_available' => false, 'reason_code' => self::REASON_OUTSIDE_BOOKING_WINDOW] + $emptyDay,
                default => $resolve($date->copy()),
            };

            $days[] = $day + $resolved;
        }

        return $days;
    }

    /**
     * Fields every month response shares: where the month sits in the booking
     * window, the compact list of open dates, and the full day-by-day list.
     */
    private function monthSummary(array $window, array $days): array
    {
        $start = $window['start'];

        return [
            'month' => $start->format('Y-m'),
            'month_name' => $start->format('F Y'),
            'previous_month' => $start->gt($window['today']->copy()->startOfMonth())
                ? $start->copy()->subMonth()->format('Y-m')
                : null,
            'next_month' => $start->lt($window['last_bookable_date']->copy()->startOfMonth())
                ? $start->copy()->addMonth()->format('Y-m')
                : null,
            'last_bookable_date' => $window['last_bookable_date']->format('Y-m-d'),
            'available_dates' => array_values(array_column(
                array_filter($days, fn (array $day) => $day['is_available']),
                'date'
            )),
            'days' => $days,
        ];
    }

    /**
     * Build an "unavailable" availability payload.
     */
    private function unavailable(string $reasonCode, string $reason, array $extra = []): array
    {
        return array_merge([
            'is_available' => false,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'available_slots' => [],
        ], $extra);
    }

    /**
     * Generate available time slots
     *
     * @param Carbon $date
     * @param ProviderScheduledWork $workSchedule
     * @param int $serviceDuration
     * @param Collection $existingAppointments appointments blocking the provider that day
     * @param Collection $hourlyTimeOffs hourly leaves touching that day
     * @param Carbon $earliestStart now + book_buffer
     * @return array
     */
    private function generateTimeSlots(
        Carbon $date,
        ProviderScheduledWork $workSchedule,
        int $serviceDuration,
        Collection $existingAppointments,
        Collection $hourlyTimeOffs,
        Carbon $earliestStart
    ): array {
        $slots = [];

        $startTime = $this->combineDateAndTime($date, $workSchedule->start_time);
        $endTime = $this->combineDateAndTime($date, $workSchedule->end_time);

        // $breakStart = $this->calculateBreakStart($startTime, $endTime, $workSchedule->break_minutes);
        // $breakEnd = $breakStart->copy()->addMinutes($workSchedule->break_minutes);

        // Slots always sit on the shift's own grid (shift_start + k × duration).
        //
        // Today used to be special-cased onto a grid measured from MIDNIGHT
        // instead, which silently produced different start times than every other
        // day whenever the duration does not divide the hour — a 50 minute service
        // on a 09:00 shift offered 09:00/09:50 tomorrow but 09:10/10:00 today, and
        // the 09:10 grid does not even align with the shift. Keeping one grid and
        // simply skipping the slots that start too soon fixes that and is what
        // applies `book_buffer` correctly.
        //
        // The cutoff is `now + book_buffer`, not `now`: BookingValidationService
        // rejects anything closer than that, so offering it here would hand the
        // customer a slot the booking call is guaranteed to refuse. A buffer large
        // enough to reach into tomorrow trims tomorrow too, which is why this is
        // not limited to `isToday()`.
        $currentTime = $startTime->copy();

        while ($currentTime->copy()->addMinutes($serviceDuration)->lte($endTime)) {
            $slotEnd = $currentTime->copy()->addMinutes($serviceDuration);

            // if ($this->overlapsWithPeriod($currentTime, $slotEnd, $breakStart, $breakEnd)) {
            //     $currentTime = $breakEnd->copy();
            //     continue;
            // }

            // Check if slot is available
            $isAvailable = $currentTime->gte($earliestStart) && !$this->hasConflict(
                $currentTime,
                $slotEnd,
                $existingAppointments,
                $hourlyTimeOffs
            );

            if ($isAvailable) {
                $slots[] = [
                    'start_time' => $currentTime->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_time_formatted' => $currentTime->format('h:i A'),
                    'end_time_formatted' => $slotEnd->format('h:i A'),
                    'display_time' => $currentTime->format('h:i A'),
                    'duration_minutes' => $serviceDuration,
                ];
            }

            $currentTime->addMinutes($serviceDuration + self::SLOT_BUFFER);
        }

        return $slots;
    }

    /**
     * Check if time slot has conflict with appointments or time offs
     */
    private function hasConflict(
        Carbon $slotStart,
        Carbon $slotEnd,
        Collection $appointments,
        Collection $timeOffs
    ): bool {
        // Check appointments
        foreach ($appointments as $appointment) {
            $appointmentStart = Carbon::parse($appointment->start_time);
            $appointmentEnd = Carbon::parse($appointment->end_time);

            if ($this->overlapsWithPeriod($slotStart, $slotEnd, $appointmentStart, $appointmentEnd)) {
                return true;
            }
        }

        // Check hourly time offs.
        //
        // Delegated to the model so the booking layer runs the identical rule
        // (BOOK-04). This also carries the multi-day meaning: an hourly leave
        // spanning several days is ONE continuous absence, so its middle days are
        // fully blocked rather than only losing the same window each morning.
        foreach ($timeOffs as $timeOff) {
            if ($timeOff->blocksWindow($slotStart, $slotEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Combine a date with a time value.
     *
     * `provider_time_offs.start_time` / `end_time` are cast to Carbon on the
     * model, so concatenating them into a date string produced a "double date
     * specification" parse error. Always normalise the time part first.
     *
     * @param Carbon $date
     * @param \DateTimeInterface|string $time
     * @return Carbon
     */
    private function combineDateAndTime(Carbon $date, $time): Carbon
    {
        $timeString = $time instanceof \DateTimeInterface
            ? $time->format('H:i:s')
            : (string) $time;

        return Carbon::parse($date->format('Y-m-d') . ' ' . $timeString);
    }

    /**
     * Check if two time periods overlap
     */
    private function overlapsWithPeriod(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        return $start1->lt($end2) && $end1->gt($start2);
    }

    /**
     * Calculate break start time (middle of work day)
     */
    private function calculateBreakStart(Carbon $start, Carbon $end, int $breakMinutes): Carbon
    {
        if ($breakMinutes == 0) {
            return $end->copy(); // No break
        }

        $totalMinutes = $start->diffInMinutes($end);
        $halfPoint = $totalMinutes / 2;

        return $start->copy()->addMinutes($halfPoint);
    }

    /**
     * Get effective duration (custom or default)
     */
    private function getEffectiveDuration(User $provider, Service $service): int
    {
        return $service->duration_minutes;
        $pivot = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->first();

        return $pivot->custom_duration ?? $service->duration_minutes;
    }

    /**
     * The price the booking will actually charge, quoted up front.
     *
     * $customPrice comes from the provider's pivot row as loaded by
     * bookableProviders(), which only joins ACTIVE rows — matching
     * BookingService::getEffectivePrice() exactly. An inactive pivot row used to
     * set the QUOTED price while the booking layer ignored that row and CHARGED
     * the base price, so the customer saw one number in the app and was asked for
     * another at the counter. `(provider_id, service_id)` is UNIQUE (MON-03 /
     * DB-01), so there is exactly one row to take it from.
     */
    private function servicePricing(Service $service, $customPrice): array
    {
        // `$service->effective_price` does not exist — neither a column nor an
        // accessor — so this used to resolve to null for every provider without a
        // `custom_price`, reporting the service as free with a 100% discount. The
        // resolution order mirrors BookingService::resolveServicePrice() so the
        // quoted price matches what the booking will actually charge.
        $effectivePrice = (float) ($customPrice ?? $service->price);

        if ($service->discount_price && $service->discount_price < $effectivePrice) {
            $effectivePrice = (float) $service->discount_price;
        }

        $originalPrice = (float) $service->price;
        $hasDiscount = $effectivePrice < $originalPrice;

        return [
            'original_price' => (float) $originalPrice,
            'effective_price' => (float) $effectivePrice,
            'has_discount' => $hasDiscount,
            'discount_amount' => $hasDiscount ? ($originalPrice - $effectivePrice) : 0,
            'discount_percentage' => ($hasDiscount && $originalPrice > 0)
                ? round((($originalPrice - $effectivePrice) / $originalPrice) * 100, 2)
                : 0,
            'currency' => 'EUR',
            'formatted_price' => number_format($effectivePrice, 2) . ' EUR',
        ];
    }

    private function formatService(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'formatted_duration' => $this->formatDuration($service->duration_minutes),
        ];
    }

    /**
     * Format branch data
     */
    private function formatBranchData($branch): ?array
    {
        if (!$branch) {
            return null;
        }

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'address' => $branch->adress,
            'phone' => $branch->phone,
            'coordinates' => [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
            ],
        ];
    }

    /**
     * Format duration
     */
    private function formatDuration(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$mins}m";
        }
    }

    /**
     * Clear availability cache for a service
     */
    public function clearServiceCache(int $serviceId): void
    {
        Cache::tags(["service_{$serviceId}"])->flush();
    }

    /**
     * Clear availability cache for a provider
     */
    public function clearProviderCache(int $providerId): void
    {
        Cache::tags(["provider_{$providerId}"])->flush();
    }
}
