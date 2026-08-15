<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Models\Service;
use App\Models\User;
use App\Models\Appointment;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
            $providers = $this->getServiceProviders($service, $branchId);

            // get free times for each providers
            $providersWithSlots = $providers->map(function ($provider) use ($service, $carbonDate) {
                $availability = $this->getProviderDayAvailability($provider, $service, $carbonDate);

                return [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->full_name,
                    'provider_avatar' => $provider->avatar,
                    'branch' => $this->formatBranchData($provider->branch),
                    'service_pricing' => $this->getProviderServicePricing($provider, $service),
                    'is_available' => $availability['is_available'],
                    'reason_code' => $availability['reason_code'],
                    'available_slots' => $availability['available_slots'],
                ];
            })->filter(function ($provider) {
                // Providers on leave / off duty / fully booked are never listed as bookable.
                return $provider['is_available'] && count($provider['available_slots']) > 0;
            })->values();

            return [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'formatted_duration' => $this->formatDuration($service->duration_minutes),
                ],
                'date' => $carbonDate->format('Y-m-d'),
                'day_name' => $carbonDate->format('l'),
                'formatted_date' => $carbonDate->format('l, F d, Y'),
                'is_today' => $carbonDate->isToday(),
                'is_tomorrow' => $carbonDate->isTomorrow(),
                // Surfaced so the app can grey out dates the booking call would
                // refuse instead of discovering the limit only on submit.
                'last_bookable_date' => $this->lastBookableDate()->format('Y-m-d'),
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
            $provider = User::findOrFail($providerId);
            $carbonDate = Carbon::parse($date);

            // Validate date
            if ($carbonDate->lt(Carbon::today())) {
                throw new \InvalidArgumentException('Cannot get availability for past dates');
            }

            // Validate provider offers this service
            if (!$this->providerOffersService($provider, $service)) {
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
                'pricing' => $this->getProviderServicePricing($provider, $service),
                'date' => $carbonDate->format('Y-m-d'),
                'day_name' => $carbonDate->format('l'),
                'formatted_date' => $carbonDate->format('l, F d, Y'),
                'total_slots' => count($slots),
                'available_slots' => $slots,
            ];
        });
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
     * @param User $provider
     * @param Service $service
     * @param Carbon $date
     * @return array{is_available: bool, reason_code: string, reason: string|null, available_slots: array}
     */
    private function getProviderDayAvailability(User $provider, Service $service, Carbon $date): array
    {
        // Checked before the work schedule: a date past the booking window can
        // never be booked no matter how free the provider looks on it.
        $lastBookableDate = $this->lastBookableDate();

        if ($date->gt($lastBookableDate)) {
            return $this->unavailable(
                self::REASON_OUTSIDE_BOOKING_WINDOW,
                'Cannot book more than ' . (int) get_setting('max_booking_days', 10) . ' days in advance',
                ['last_bookable_date' => $lastBookableDate->format('Y-m-d')]
            );
        }

        $dayOfWeek = $date->dayOfWeek;

        $workSchedule = ProviderScheduledWork::where('user_id', $provider->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->first();

        if (!$workSchedule) {
            return $this->unavailable(
                self::REASON_NOT_WORKING_DAY,
                "Provider '{$provider->full_name}' does not work on " . $date->format('l')
            );
        }

        $fullDayTimeOff = $this->getFullDayTimeOff($provider, $date);

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
            $provider,
            $date,
            $workSchedule,
            $serviceDuration
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
     * @param User $provider
     * @param Carbon $date
     * @param ProviderScheduledWork $workSchedule
     * @param int $serviceDuration
     * @return array
     */
    private function generateTimeSlots(
        User $provider,
        Carbon $date,
        ProviderScheduledWork $workSchedule,
        int $serviceDuration
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
        $earliestStart = $this->earliestBookableTime();

        $currentTime = $startTime->copy();

        // Get existing appointments
        $existingAppointments = $this->getProviderAppointments($provider, $date);

        // Get hourly time offs
        $hourlyTimeOffs = $this->getProviderHourlyTimeOffs($provider, $date);

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
     * Get the full day time off covering this date, if any.
     * A missing end_date is treated as a single day leave.
     */
    private function getFullDayTimeOff(User $provider, Carbon $date): ?ProviderTimeOff
    {
        return ProviderTimeOff::where('user_id', $provider->id)
            ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
            ->whereDate('start_date', '<=', $date->format('Y-m-d'))
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date->format('Y-m-d')])
            ->first();
    }

    /**
     * Get provider's appointments for a specific date
     */
    private function getProviderAppointments(User $provider, Carbon $date): Collection
    {
        return Appointment::where('provider_id', $provider->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', [
                AppointmentStatus::PENDING,
            ])
            ->select('start_time', 'end_time')
            ->get();
    }

    /**
     * Get provider's hourly time offs for a specific date
     */
    private function getProviderHourlyTimeOffs(User $provider, Carbon $date): Collection
    {
        return ProviderTimeOff::where('user_id', $provider->id)
            ->where('type', ProviderTimeOff::TYPE_HOURLY)
            ->whereDate('start_date', '<=', $date->format('Y-m-d'))
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date->format('Y-m-d')])
            ->select('start_time', 'end_time')
            ->get();
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

        // Check hourly time offs
        foreach ($timeOffs as $timeOff) {
            if ($timeOff->start_time === null || $timeOff->end_time === null) {
                continue;
            }

            $timeOffStart = $this->combineDateAndTime($slotStart, $timeOff->start_time);
            $timeOffEnd = $this->combineDateAndTime($slotStart, $timeOff->end_time);

            if ($this->overlapsWithPeriod($slotStart, $slotEnd, $timeOffStart, $timeOffEnd)) {
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
     * `activeProviders()` joins `provider_service`, and BOTH that pivot and the
     * `users` table carry an `is_active` column — so every column added here has
     * to be table-qualified or MySQL rejects the query as ambiguous. The active
     * filter itself is already applied by the relationship (pivot + user), which
     * is why only the branch filter remains.
     */
    private function getServiceProviders(Service $service, ?int $branchId = null): Collection
    {
        $query = $service->activeProviders()
            ->with('branch');

        if ($branchId) {
            $query->where('users.branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * Check if provider offers service
     */
    private function providerOffersService(User $provider, Service $service): bool
    {
        return DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->exists();
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


    private function getProviderServicePricing(User $provider, Service $service): array
    {
        $pivot = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->first();

        // `$service->effective_price` does not exist — neither a column nor an
        // accessor — so this used to resolve to null for every provider without a
        // `custom_price`, reporting the service as free with a 100% discount. The
        // resolution order mirrors BookingService::resolveServicePrice() so the
        // quoted price matches what the booking will actually charge.
        $effectivePrice = (float) ($pivot?->custom_price ?? $service->price);

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
