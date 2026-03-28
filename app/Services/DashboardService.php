<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\SalonSchedule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService {
    public function getProviders(): Collection {
        return User::whereHas('roles', function ($q) {
            $q->where('name', 'provider');
        })
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'avatar_url', 'branch_id']);
    }

    public function getProvidersWithStatus(string $date): Collection {
        $providers = $this->getProviders();
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek;
        $providerIds = $providers->pluck('id')->toArray();

        $schedules = ProviderScheduledWork::whereIn('user_id', $providerIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get()
            ->keyBy('user_id');

        $fullDayOffIds = ProviderTimeOff::whereIn('user_id', $providerIds)
            ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        $bookingCounts = Appointment::whereIn('provider_id', $providerIds)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->selectRaw('provider_id, COUNT(*) as count')
            ->groupBy('provider_id')
            ->pluck('count', 'provider_id')
            ->toArray();

        return $providers->map(function ($provider) use ($schedules, $fullDayOffIds, $bookingCounts) {
            $schedule = $schedules->get($provider->id);
            $isWorkDay = $schedule && $schedule->is_work_day;

            return [
                'id' => $provider->id,
                'name' => $provider->full_name,
                'avatar' => $provider->avatar_url,
                'is_work_day' => $isWorkDay,
                'has_day_off' => in_array($provider->id, $fullDayOffIds),
                'schedule' => $schedule ? [
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ] : null,
                'booking_count' => $bookingCounts[$provider->id] ?? 0,
            ];
        });
    }

    public function getAppointmentsForDate(string $date, array $providerIds = []): Collection {
        $query = Appointment::with(['services', 'services_record', 'customer', 'provider', 'invoice'])
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->orderBy('start_time');

        if (!empty($providerIds)) {
            $query->whereIn('provider_id', $providerIds);
        }

        return $query->get();
    }

    public function getTimeOffsForDate(string $date, array $providerIds = []): Collection {
        $query = ProviderTimeOff::with('provider', 'reason')
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->where('type', ProviderTimeOff::TYPE_FULL_DAY)
                        ->where('start_date', '<=', $date)
                        ->where('end_date', '>=', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->where('type', ProviderTimeOff::TYPE_HOURLY)
                        ->whereDate('start_date', $date);
                });
            });

        if (!empty($providerIds)) {
            $query->whereIn('user_id', $providerIds);
        }

        return $query->get();
    }

    public function getSalonScheduleForDate(string $date): ?SalonSchedule {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        $branch = Branch::where('is_active', true)->first();

        if (!$branch) {
            return null;
        }

        return SalonSchedule::where('branch_id', $branch->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();
    }

    public function getBookingCountsForMonth(int $year, int $month): array {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $counts = Appointment::where('created_status', 1)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->whereBetween('appointment_date', [$start, $end])
            ->selectRaw('DATE(appointment_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return $counts;
    }

    public function getCategories(): Collection {
        return ServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);
    }

    public function getAllServicesGrouped(): array {
        $categories = ServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $services = Service::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price', 'discount_price', 'duration_minutes', 'category_id']);

        return [
            'categories' => $categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->translated_name,
            ])->toArray(),
            'services' => $services->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->translated_name,
                'price' => (float) $s->price,
                'discount_price' => $s->discount_price ? (float) $s->discount_price : null,
                'duration_minutes' => $s->duration_minutes,
                'category_id' => $s->category_id,
            ])->toArray(),
        ];
    }

    public function getAllCustomers(): array {
        return User::whereHas('roles', function ($q) {
            $q->where('name', 'customer');
        })->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'phone', 'email'])
            ->map(fn($c) => [
                'id' => $c->id,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'name' => $c->full_name,
                'phone' => $c->phone,
                'email' => $c->email,
            ])->toArray();
    }

    public function getServicesByCategory(int $categoryId): Collection {
        return Service::where('category_id', $categoryId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price', 'discount_price', 'duration_minutes']);
    }

    public function getProvidersForService(int $serviceId): Collection {
        $service = Service::find($serviceId);
        if (!$service) return collect();

        return $service->activeProviders()->get(['users.id', 'first_name', 'last_name']);
    }

    public function getAvailableSlotsForProvider(int $serviceId, int $providerId, string $date): array {
        $availabilityService = app(ServiceAvailabilityService::class);

        try {
            $result = $availabilityService->getProviderAvailableSlotsByDate($serviceId, $providerId, $date);
            return $result['available_slots'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAvailableProvidersForServiceAtTime(int $serviceId, string $date, string $startTime, int $duration): array {
        $service = Service::find($serviceId);
        if (!$service) return [];

        $providers = $service->activeProviders()->get();
        $carbonDate = Carbon::parse($date);
        $slotStart = Carbon::parse($date . ' ' . $startTime);
        $slotEnd = $slotStart->copy()->addMinutes($duration);
        $availableProviders = [];

        foreach ($providers as $provider) {
            $dayOfWeek = $carbonDate->dayOfWeek;
            $schedule = ProviderScheduledWork::where('user_id', $provider->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_work_day', true)
                ->where('is_active', true)
                ->first();

            if (!$schedule) continue;

            $scheduleStart = Carbon::parse($date . ' ' . $schedule->start_time);
            $scheduleEnd = Carbon::parse($date . ' ' . $schedule->end_time);
            if ($slotStart->lt($scheduleStart) || $slotEnd->gt($scheduleEnd)) continue;

            $hasFullDayOff = ProviderTimeOff::where('user_id', $provider->id)
                ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->exists();
            if ($hasFullDayOff) continue;

            $hasHourlyConflict = ProviderTimeOff::where('user_id', $provider->id)
                ->where('type', ProviderTimeOff::TYPE_HOURLY)
                ->whereDate('start_date', $date)
                ->where('start_time', '<', $slotEnd->format('H:i:s'))
                ->where('end_time', '>', $slotStart->format('H:i:s'))
                ->exists();
            if ($hasHourlyConflict) continue;

            $hasAppointmentConflict = Appointment::where('provider_id', $provider->id)
                ->whereDate('appointment_date', $date)
                ->where('created_status', 1)
                ->whereNotIn('status', [
                    AppointmentStatus::USER_CANCELLED->value,
                    AppointmentStatus::ADMIN_CANCELLED->value,
                ])
                ->where(function ($q) use ($slotStart, $slotEnd) {
                    $q->where('start_time', '<', $slotEnd)
                        ->where('end_time', '>', $slotStart);
                })
                ->exists();
            if ($hasAppointmentConflict) continue;

            $availableProviders[] = [
                'id' => $provider->id,
                'first_name' => $provider->first_name,
                'last_name' => $provider->last_name,
                'name' => $provider->full_name,
            ];
        }

        return $availableProviders;
    }

    public function getCustomers(string $search = ''): Collection {
        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'customer');
        })->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->limit(20)->get(['id', 'first_name', 'last_name', 'phone', 'email']);
    }

    public function getReasonLeaves(): Collection {
        return ReasonLeave::orderBy('id')->get();
    }

    public function getAppointmentDetails(int $appointmentId): ?Appointment {
        return Appointment::with([
            'services',
            'services_record',
            'customer',
            'provider',
            'invoice',
            'invoice.items',
        ])->find($appointmentId);
    }
}
