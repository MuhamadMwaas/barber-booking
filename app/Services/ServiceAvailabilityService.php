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


    private const CACHE_DURATION = 120;

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
                return [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->full_name,
                    'provider_avatar' => $provider->avatar,
                    'branch' => $this->formatBranchData($provider->branch),
                    'service_pricing' => $this->getProviderServicePricing($provider, $service),
                    'available_slots' => $this->getProviderAvailableSlots($provider, $service, $carbonDate),
                ];
            })->filter(function ($provider) {

                return count($provider['available_slots']) > 0;
            })->values();

            return [
                'date' => $carbonDate->format('Y-m-d'),
                'day_name' => $carbonDate->format('l'),
                'formatted_date' => $carbonDate->format('l, F d, Y'),
                'is_today' => $carbonDate->isToday(),
                'is_tomorrow' => $carbonDate->isTomorrow(),
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

            $slots = $this->getProviderAvailableSlots($provider, $service, $carbonDate);

            return [
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

        while ($current->lte($end)) {
            if ($current->gte(Carbon::today())) {
                $dateStr = $current->format('Y-m-d');

                if ($providerId) {
                    $dayData = $this->getProviderAvailableSlotsByDate($serviceId, $providerId, $dateStr);
                    $availableSlots = $dayData['total_slots'];
                } else {
                    $dayData = $this->getAvailableSlotsByDate($serviceId, $dateStr, $branchId);
                    $availableSlots = $dayData['providers']->sum(function ($provider) {
                        return count($provider['available_slots']);
                    });
                }

                $calendar[] = [
                    'date' => $dateStr,
                    'day_name' => $current->format('D'),
                    'day_number' => $current->day,
                    'is_today' => $current->isToday(),
                    'is_available' => $availableSlots > 0,
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
     * @param User $provider
     * @param Service $service
     * @param Carbon $date
     * @return array
     */
    private function getProviderAvailableSlots(User $provider, Service $service, Carbon $date): array
    {
        $dayOfWeek = $date->dayOfWeek;
        $dateStr = $date->format('Y-m-d');


        $workSchedule = ProviderScheduledWork::where('user_id', $provider->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->first();

        if (!$workSchedule) {
            return [];
        }

        if ($this->hasFullDayTimeOff($provider, $date)) {
            return [];
        }

        // Get service duration
        $serviceDuration = $this->getEffectiveDuration($provider, $service);

        // Generate time slots
        return $this->generateTimeSlots(
            $provider,
            $date,
            $workSchedule,
            $serviceDuration
        );
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
        $dateStr = $date->format('Y-m-d');

        $startTime = Carbon::parse($dateStr . ' ' . $workSchedule->start_time);
        $endTime = Carbon::parse($dateStr . ' ' . $workSchedule->end_time);

        // $breakStart = $this->calculateBreakStart($startTime, $endTime, $workSchedule->break_minutes);
        // $breakEnd = $breakStart->copy()->addMinutes($workSchedule->break_minutes);

        // Don't show past time slots for today
        $now = Carbon::now();
        if ($date->isToday() && $startTime->lt($now)) {
            $startTime = $now->copy()->addMinutes(0)->minute(0)->second(0);
        }

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
            $isAvailable = !$this->hasConflict(
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
     * Check if provider has full day time off
     */
    private function hasFullDayTimeOff(User $provider, Carbon $date): bool
    {
        return ProviderTimeOff::where('user_id', $provider->id)
            ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->exists();
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
            ->whereDate('start_date', $date)
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
            $timeOffStart = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $timeOff->start_time);
            $timeOffEnd = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $timeOff->end_time);

            if ($this->overlapsWithPeriod($slotStart, $slotEnd, $timeOffStart, $timeOffEnd)) {
                return true;
            }
        }

        return false;
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


    private function getServiceProviders(Service $service, ?int $branchId = null): Collection
    {
        $query = $service->activeProviders()
            ->with('branch')
            ->where('is_active', true);

        if ($branchId) {
            $query->where('branch_id', $branchId);
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

        $effectivePrice = $pivot->custom_price ?? $service->effective_price;
        $originalPrice = $service->price;
        $hasDiscount = $effectivePrice < $originalPrice;

        return [
            'original_price' => (float) $originalPrice,
            'effective_price' => (float) $effectivePrice,
            'has_discount' => $hasDiscount,
            'discount_amount' => $hasDiscount ? ($originalPrice - $effectivePrice) : 0,
            'discount_percentage' => $hasDiscount ? round((($originalPrice - $effectivePrice) / $originalPrice) * 100, 2) : 0,
            'currency' => 'AED',
            'formatted_price' => number_format($effectivePrice, 2) . ' AED',
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
