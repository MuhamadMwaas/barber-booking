<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProviderReportService {
    private function paidStatuses(): array {
        return array_map(fn($s) => $s->value, PaymentStatus::getSuccessfulStatuses());
    }

    public function getProviderList(): array {
        return User::whereHas('roles', fn($q) => $q->where('name', 'provider'))
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->first_name . ' ' . $u->last_name,
            ])
            ->toArray();
    }

    public function getProviderInfo(int $providerId): array {
        $provider = User::with('services')->find($providerId);
        if (!$provider) return [];

        return [
            'name' => $provider->first_name . ' ' . $provider->last_name,
            'email' => $provider->email,
            'phone' => $provider->phone,
            'avatar' => $provider->avatar_url,
            'active_services' => $provider->services()->wherePivot('is_active', true)->count(),
            'joined' => $provider->created_at?->format('Y-m-d'),
        ];
    }

    public function getRevenueStats(int $providerId, string $from, string $to): array {
        $paidStatuses = $this->paidStatuses();
        $base = Appointment::where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to]);

        $totalRevenue = (clone $base)->whereIn('payment_status', $paidStatuses)->sum('total_amount');
        $avgRevenue = (clone $base)->whereIn('payment_status', $paidStatuses)->avg('total_amount');
        $cashRevenue = (clone $base)->where('payment_status', PaymentStatus::PAID_ONSTIE_CASH->value)->sum('total_amount');
        $cardRevenue = (clone $base)->where('payment_status', PaymentStatus::PAID_ONSTIE_CARD->value)->sum('total_amount');
        $onlineRevenue = (clone $base)->where('payment_status', PaymentStatus::PAID_ONLINE->value)->sum('total_amount');

        return [
            'total' => (float) $totalRevenue,
            'average' => (float) ($avgRevenue ?? 0),
            'cash' => (float) $cashRevenue,
            'card' => (float) $cardRevenue,
            'online' => (float) $onlineRevenue,
        ];
    }

    public function getBookingStats(int $providerId, string $from, string $to): array {
        $base = Appointment::where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to]);

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', AppointmentStatus::COMPLETED->value)->count();
        $cancelled = (clone $base)->whereIn('status', [
            AppointmentStatus::USER_CANCELLED->value,
            AppointmentStatus::ADMIN_CANCELLED->value,
        ])->count();
        $pending = (clone $base)->where('status', AppointmentStatus::PENDING->value)->count();
        $noShow = (clone $base)->where('status', AppointmentStatus::NO_SHOW->value)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'pending' => $pending,
            'no_show' => $noShow,
            'cancellation_rate' => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'no_show_rate' => $total > 0 ? round(($noShow / $total) * 100, 1) : 0,
        ];
    }

    public function getRevenueOverTime(int $providerId, string $from, string $to): array {
        $paidStatuses = $this->paidStatuses();
        $daysDiff = Carbon::parse($from)->diffInDays(Carbon::parse($to));

        $groupBy = $daysDiff <= 31
            ? 'DATE(appointment_date)'
            : "DATE_FORMAT(appointment_date, '%Y-%m')";

        return Appointment::query()
            ->select(DB::raw("$groupBy as period"), DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as count'))
            ->where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to])
            ->whereIn('payment_status', $paidStatuses)
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($r) => [
                'period' => $r->period,
                'revenue' => (float) $r->revenue,
                'count' => $r->count,
            ])
            ->toArray();
    }

    public function getBookingsOverTime(int $providerId, string $from, string $to): array {
        $daysDiff = Carbon::parse($from)->diffInDays(Carbon::parse($to));

        $groupBy = $daysDiff <= 31
            ? 'DATE(appointment_date)'
            : "DATE_FORMAT(appointment_date, '%Y-%m')";

        return Appointment::query()
            ->select(
                DB::raw("$groupBy as period"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = " . AppointmentStatus::COMPLETED->value . " THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status IN (" . AppointmentStatus::USER_CANCELLED->value . "," . AppointmentStatus::ADMIN_CANCELLED->value . ") THEN 1 ELSE 0 END) as cancelled")
            )
            ->where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($r) => [
                'period' => $r->period,
                'total' => (int) $r->total,
                'completed' => (int) $r->completed,
                'cancelled' => (int) $r->cancelled,
            ])
            ->toArray();
    }

    public function getPeakHours(int $providerId, string $from, string $to): array {
        return Appointment::query()
            ->select(DB::raw("EXTRACT(HOUR FROM start_time) as hour"), DB::raw('COUNT(*) as count'))
            ->where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to])
            ->where('status', '>=', 0)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn($r) => [
                'hour' => str_pad((int) $r->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'count' => (int) $r->count,
            ])
            ->toArray();
    }

    public function getPeakDays(int $providerId, string $from, string $to): array {
        $dayNames = [
            0 => __('reports.days.sunday'),
            1 => __('reports.days.monday'),
            2 => __('reports.days.tuesday'),
            3 => __('reports.days.wednesday'),
            4 => __('reports.days.thursday'),
            5 => __('reports.days.friday'),
            6 => __('reports.days.saturday'),
        ];

        return Appointment::query()
            ->select(DB::raw('DAYOFWEEK(appointment_date) - 1 as dow'), DB::raw('COUNT(*) as count'))
            ->where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to])
            ->where('status', '>=', 0)
            ->groupBy('dow')
            ->orderBy('dow')
            ->get()
            ->map(fn($r) => [
                'day' => $dayNames[(int) $r->dow] ?? (int) $r->dow,
                'count' => (int) $r->count,
            ])
            ->toArray();
    }

    public function getServiceBreakdown(int $providerId, string $from, string $to): array {
        return DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->select(
                'appointment_services.service_id',
                'appointment_services.service_name',
                DB::raw('COUNT(appointment_services.id) as count'),
                DB::raw('SUM(appointment_services.price) as revenue'),
                DB::raw('AVG(appointment_services.duration_minutes) as avg_duration')
            )
            ->where('appointments.provider_id', $providerId)
            ->whereBetween('appointments.appointment_date', [$from, $to])
            ->where('appointments.status', AppointmentStatus::COMPLETED->value)
            ->groupBy('appointment_services.service_id', 'appointment_services.service_name')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => [
                'name' => $r->service_name ?? 'N/A',
                'count' => (int) $r->count,
                'revenue' => (float) $r->revenue,
                'avg_duration' => round((float) $r->avg_duration),
            ])
            ->toArray();
    }

    public function getTopCustomers(int $providerId, string $from, string $to, int $limit = 10): array {
        return DB::table('appointments')
            ->leftJoin('users', 'appointments.customer_id', '=', 'users.id')
            ->select(
                'appointments.customer_id',
                DB::raw("COALESCE(CONCAT(users.first_name, ' ', users.last_name), MAX(appointments.customer_name), 'Guest') as cust_name"),
                DB::raw('COUNT(*) as booking_count'),
                DB::raw('SUM(appointments.total_amount) as total_spent'),
                DB::raw('MAX(appointments.appointment_date) as last_visit')
            )
            ->where('appointments.provider_id', $providerId)
            ->whereBetween('appointments.appointment_date', [$from, $to])
            ->where('appointments.status', '>=', 0)
            ->groupBy('appointments.customer_id', 'users.first_name', 'users.last_name')
            ->orderByDesc('booking_count')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'name' => $r->cust_name ?: 'Guest',
                'bookings' => (int) $r->booking_count,
                'spent' => (float) ($r->total_spent ?? 0),
                'last_visit' => $r->last_visit,
            ])
            ->toArray();
    }

    public function getRecentAppointments(int $providerId, string $from, string $to, int $limit = 15): array {
        return Appointment::query()
            ->where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to])
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->limit($limit)
            ->get()
            ->map(function ($a) {
                $statusLabels = [
                    AppointmentStatus::PENDING->value => ['pending', ''],
                    AppointmentStatus::COMPLETED->value => ['completed', 'green'],
                    AppointmentStatus::USER_CANCELLED->value => ['cancelled', 'red'],
                    AppointmentStatus::ADMIN_CANCELLED->value => ['cancelled', 'red'],
                    AppointmentStatus::NO_SHOW->value => ['no_show', 'amber'],
                ];

                $statusVal = $a->status instanceof \BackedEnum ? $a->status->value : (int) $a->status;
                $info = $statusLabels[$statusVal] ?? ['pending', ''];

                return [
                    'date' => $a->appointment_date,
                    'time' => $a->start_time ? Carbon::parse($a->start_time)->format('H:i') : '--',
                    'customer' => $a->customer_name ?: 'Guest',
                    'duration' => $a->duration_minutes ?? 0,
                    'amount' => (float) ($a->total_amount ?? 0),
                    'status_key' => $info[0],
                    'status_color' => $info[1],
                ];
            })
            ->toArray();
    }

    public function getAvgServiceDuration(int $providerId, string $from, string $to): float {
        return (float) (AppointmentService::query()
            ->whereHas('appointment', function ($q) use ($providerId, $from, $to) {
                $q->where('provider_id', $providerId)
                    ->whereBetween('appointment_date', [$from, $to])
                    ->where('status', AppointmentStatus::COMPLETED->value);
            })
            ->avg('duration_minutes') ?? 0);
    }

    public function getWorkScheduleSummary(int $providerId): array {
        $schedules = ProviderScheduledWork::where('user_id', $providerId)
            ->where('is_active', true)
            ->where('is_work_day', true)
            ->orderBy('day_of_week')
            ->get();

        $totalWeeklyMinutes = 0;
        $workDays = [];

        $dayNames = [
            0 => __('reports.days.sunday'),
            1 => __('reports.days.monday'),
            2 => __('reports.days.tuesday'),
            3 => __('reports.days.wednesday'),
            4 => __('reports.days.thursday'),
            5 => __('reports.days.friday'),
            6 => __('reports.days.saturday'),
        ];

        foreach ($schedules as $s) {
            $minutes = $s->working_minutes ?? 0;
            $totalWeeklyMinutes += $minutes;
            $workDays[] = [
                'day' => $dayNames[$s->day_of_week] ?? $s->day_of_week,
                'start' => $s->formatted_start_time ?? '--',
                'end' => $s->formatted_end_time ?? '--',
                'hours' => round($minutes / 60, 1),
            ];
        }

        return [
            'work_days_count' => count($workDays),
            'weekly_hours' => round($totalWeeklyMinutes / 60, 1),
            'schedule' => $workDays,
        ];
    }

    public function getTimeOffSummary(int $providerId, string $from, string $to): array {
        $timeOffs = ProviderTimeOff::where('user_id', $providerId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                    ->orWhereBetween('end_date', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('start_date', '<=', $from)
                            ->where('end_date', '>=', $to);
                    });
            })
            ->with('reason')
            ->orderByDesc('start_date')
            ->get();

        $totalDays = 0;
        $totalHours = 0;
        $records = [];

        foreach ($timeOffs as $t) {
            if ($t->isFullDay()) {
                $totalDays += $t->duration_days ?? 1;
            } else {
                $totalHours += $t->duration_hours ?? 0;
            }

            $records[] = [
                'start' => $t->start_date,
                'end' => $t->end_date,
                'type' => $t->isHourly() ? 'hourly' : 'full_day',
                'reason' => $t->reason?->name ?? '--',
                'duration' => $t->isHourly()
                    ? ($t->duration_hours ?? 0) . 'h'
                    : ($t->duration_days ?? 1) . 'd',
            ];
        }

        return [
            'total_days' => $totalDays,
            'total_hours' => $totalHours,
            'count' => count($records),
            'records' => array_slice($records, 0, 10),
        ];
    }

    public function getUtilization(int $providerId, string $from, string $to): array {
        $schedule = ProviderScheduledWork::where('user_id', $providerId)
            ->where('is_active', true)
            ->where('is_work_day', true)
            ->get();

        $weeklyMinutes = $schedule->sum(fn($s) => $s->working_minutes ?? 0);
        if ($weeklyMinutes <= 0) $weeklyMinutes = 5 * 8 * 60;

        $daysDiff = max(1, Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1);
        $weeks = max(1, $daysDiff / 7);
        $availableMinutes = $weeklyMinutes * $weeks;

        $workedMinutes = (int) Appointment::where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to])
            ->where('status', AppointmentStatus::COMPLETED->value)
            ->sum('duration_minutes');

        return [
            'worked_hours' => round($workedMinutes / 60, 1),
            'available_hours' => round($availableMinutes / 60, 1),
            'utilization' => $availableMinutes > 0 ? round(($workedMinutes / $availableMinutes) * 100, 1) : 0,
        ];
    }

    public function getServiceRevenuePie(int $providerId, string $from, string $to): array {
        $paidStatuses = $this->paidStatuses();

        return DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->select(
                'appointment_services.service_name',
                DB::raw('SUM(appointment_services.price) as revenue')
            )
            ->where('appointments.provider_id', $providerId)
            ->whereBetween('appointments.appointment_date', [$from, $to])
            ->whereIn('appointments.payment_status', $paidStatuses)
            ->groupBy('appointment_services.service_name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(fn($r) => [
                'name' => $r->service_name ?? 'N/A',
                'revenue' => (float) $r->revenue,
            ])
            ->toArray();
    }

    public function getDailyAvgRevenue(int $providerId, string $from, string $to): float {
        $paidStatuses = $this->paidStatuses();
        $daysDiff = max(1, Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1);

        $total = (float) Appointment::where('provider_id', $providerId)
            ->whereBetween('appointment_date', [$from, $to])
            ->whereIn('payment_status', $paidStatuses)
            ->sum('total_amount');

        return round($total / $daysDiff, 2);
    }
}
