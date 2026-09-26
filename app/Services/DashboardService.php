<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Color;
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

        // Off for the whole day: a full-day leave, or the middle day of a
        // multi-day hourly leave. Same range rule as BookingValidationService —
        // the old `end_date >= $date` missed null-ended leaves (BOOK-04).
        $dayStart = $carbonDate->copy()->startOfDay();
        $fullDayOffIds = ProviderTimeOff::whereIn('user_id', $providerIds)
            ->coveringDate($date)
            ->get()
            ->filter(function (ProviderTimeOff $timeOff) use ($dayStart) {
                $window = $timeOff->blockedWindowOn($dayStart);

                return $window !== null
                    && $window['start']->lte($dayStart)
                    && $window['end']->gte($dayStart->copy()->addDay());
            })
            ->pluck('user_id')
            ->unique()
            ->values()
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
        // Eager-load parent + children for the linked-bookings UI (badge + connector line).
        $query = Appointment::with([
                'services',
                'services_record.service',
                'customer',
                'provider',
                'invoice',
                'parent',
                'children',
            ])
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
        // Callers draw each row with ProviderTimeOff::blockedWindowOn(), so a
        // multi-day leave shows the slice it really occupies on $date.
        $query = ProviderTimeOff::with('provider', 'reason')
            ->coveringDate($date);

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

    public function getAvailableProvidersForServiceAtTime(int $serviceId, string $date, string $startTime, int $duration, bool $bypassAvailability = false): array {
        return array_values(array_map(
            fn (array $provider) => array_diff_key($provider, ['available' => true, 'reason' => true, 'reason_label' => true]),
            array_filter(
                $this->getProviderAvailabilityForServiceAtTime($serviceId, $date, $startTime, $duration, $bypassAvailability),
                fn (array $provider) => $provider['available']
            )
        ));
    }

    /**
     * Every active provider linked to the service, each flagged available or not
     * with the reason, so the booking modal can show WHY someone is missing
     * instead of silently dropping them. Available providers come first.
     *
     * The rules mirror BookingValidationService, so whoever this marks available
     * is accepted on save: the provider_service link must be active, the
     * provider must work that day within hours, no leave may cover the slot
     * (coveringDate + blocksWindow) and no booking may block it
     * (blocksProviderTime + overlapping).
     *
     * Reasons: service_disabled, not_working, outside_hours, on_leave, busy.
     */
    public function getProviderAvailabilityForServiceAtTime(int $serviceId, string $date, string $startTime, int $duration, bool $bypassAvailability = false): array {
        $service = Service::find($serviceId);
        if (!$service) return [];

        $providers = $service->providers()
            ->where('users.is_active', true)
            ->orderBy('first_name')
            ->get();
        $slotStart = Carbon::parse($date . ' ' . $startTime);
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        $available = [];
        $unavailable = [];

        foreach ($providers as $provider) {
            $reason = $this->unavailabilityReason($provider, $date, $slotStart, $slotEnd, $bypassAvailability);

            $row = [
                'id' => $provider->id,
                'first_name' => $provider->first_name,
                'last_name' => $provider->last_name,
                'name' => $provider->full_name,
                'available' => $reason === null,
                'reason' => $reason,
                'reason_label' => $reason ? __('dashboard.booking_modal.unavailable_reason.' . $reason) : null,
            ];

            if ($reason === null) {
                $available[] = $row;
            } else {
                $unavailable[] = $row;
            }
        }

        return array_merge($available, $unavailable);
    }

    private function unavailabilityReason(User $provider, string $date, Carbon $slotStart, Carbon $slotEnd, bool $bypassAvailability): ?string {
        // A link switched off in provider_service: the provider is listed on the
        // service (grey dot) but does not offer it. Force booking never overrides this.
        if (! $provider->pivot->is_active) {
            return 'service_disabled';
        }

        // Provider availability window (working day, working hours, full-day &
        // hourly time-off). Trusted staff "force booking" bypasses this whole
        // window so on-leave / off-day providers still appear and can be picked
        // (e.g. Sophie is off today but is coming in for a VIP). The appointment
        // CONFLICT check below always runs — a busy provider is never offered.
        if (! $bypassAvailability) {
            $schedule = ProviderScheduledWork::where('user_id', $provider->id)
                ->where('day_of_week', Carbon::parse($date)->dayOfWeek)
                ->where('is_work_day', true)
                ->where('is_active', true)
                ->first();

            if (! $schedule) {
                return 'not_working';
            }

            $scheduleStart = Carbon::parse($date . ' ' . $schedule->start_time);
            $scheduleEnd = Carbon::parse($date . ' ' . $schedule->end_time);
            if ($slotStart->lt($scheduleStart) || $slotEnd->gt($scheduleEnd)) {
                return 'outside_hours';
            }

            $onLeave = ProviderTimeOff::where('user_id', $provider->id)
                ->coveringDate($date)
                ->get()
                ->contains(fn (ProviderTimeOff $timeOff) => $timeOff->blocksWindow($slotStart, $slotEnd));
            if ($onLeave) {
                return 'on_leave';
            }
        }

        $isBusy = Appointment::where('provider_id', $provider->id)
            ->blocksProviderTime()
            ->overlapping($slotStart, $slotEnd)
            ->exists();

        return $isBusy ? 'busy' : null;
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
            'parent',
            'parent.invoice',
            'children',
            'children.provider',
            'children.services_record',
            'colorRecords.color',   // ← load colors used in this appointment
        ])->find($appointmentId);
    }

    /**
     * All active colors for the dashboard preload (color picker in appointment modal).
     */
    public function getAllColors(): array {
        return Color::active()
            ->orderBy('name')
            ->get(['id', 'name', 'hex_code', 'brand', 'unit', 'stock_quantity'])
            ->map(fn ($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'display_name'   => $c->display_name,
                'hex_code'       => $c->hex_code,
                'brand'          => $c->brand,
                'unit'           => $c->unit,
                'stock_quantity' => $c->stock_quantity ? (float) $c->stock_quantity : null,
            ])
            ->toArray();
    }
}
