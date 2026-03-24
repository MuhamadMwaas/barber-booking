<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsService {
    public function getRevenueStats(string $from, string $to): array {
        $paidStatuses = [
            PaymentStatus::PAID_ONLINE->value,
            PaymentStatus::PAID_ONSTIE_CASH->value,
            PaymentStatus::PAID_ONSTIE_CARD->value,
        ];

        $totalRevenue = Appointment::whereBetween('appointment_date', [$from, $to])
            ->whereIn('payment_status', $paidStatuses)
            ->sum('total_amount');

        $avgRevenue = Appointment::whereBetween('appointment_date', [$from, $to])
            ->whereIn('payment_status', $paidStatuses)
            ->avg('total_amount');

        $cashRevenue = Appointment::whereBetween('appointment_date', [$from, $to])
            ->where('payment_status', PaymentStatus::PAID_ONSTIE_CASH->value)
            ->sum('total_amount');

        $cardRevenue = Appointment::whereBetween('appointment_date', [$from, $to])
            ->where('payment_status', PaymentStatus::PAID_ONSTIE_CARD->value)
            ->sum('total_amount');

        $onlineRevenue = Appointment::whereBetween('appointment_date', [$from, $to])
            ->where('payment_status', PaymentStatus::PAID_ONLINE->value)
            ->sum('total_amount');

        return [
            'total' => (float) $totalRevenue,
            'average' => (float) ($avgRevenue ?? 0),
            'cash' => (float) $cashRevenue,
            'card' => (float) $cardRevenue,
            'online' => (float) $onlineRevenue,
        ];
    }

    public function getBookingStats(string $from, string $to): array {
        $total = Appointment::whereBetween('appointment_date', [$from, $to])->count();

        $completed = Appointment::whereBetween('appointment_date', [$from, $to])
            ->where('status', AppointmentStatus::COMPLETED->value)
            ->count();

        $cancelled = Appointment::whereBetween('appointment_date', [$from, $to])
            ->whereIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->count();

        $pending = Appointment::whereBetween('appointment_date', [$from, $to])
            ->where('status', AppointmentStatus::PENDING->value)
            ->count();

        $noShow = Appointment::whereBetween('appointment_date', [$from, $to])
            ->where('status', AppointmentStatus::NO_SHOW->value)
            ->count();

        $cancellationRate = $total > 0 ? round(($cancelled / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'pending' => $pending,
            'no_show' => $noShow,
            'cancellation_rate' => $cancellationRate,
        ];
    }

    public function getTopProvidersByRevenue(string $from, string $to, int $limit = 10): array {
        $paidStatuses = array_map(fn($s) => $s->value, PaymentStatus::getSuccessfulStatuses());

        return Appointment::query()
            ->select('provider_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as count'))
            ->whereBetween('appointment_date', [$from, $to])
            ->whereIn('payment_status', $paidStatuses)
            ->whereNotNull('provider_id')
            ->groupBy('provider_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->with('provider:id,first_name,last_name')
            ->get()
            ->map(fn($r) => [
                'name' => $r->provider ? $r->provider->first_name . ' ' . $r->provider->last_name : 'N/A',
                'revenue' => (float) $r->revenue,
                'count' => $r->count,
            ])
            ->toArray();
    }

    public function getTopProvidersByServiceCount(string $from, string $to, int $limit = 10): array {
        return DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->select('appointments.provider_id', DB::raw('COUNT(appointment_services.id) as service_count'))
            ->whereBetween('appointments.appointment_date', [$from, $to])
            ->where('appointments.status', AppointmentStatus::COMPLETED->value)
            ->whereNotNull('appointments.provider_id')
            ->groupBy('appointments.provider_id')
            ->orderByDesc('service_count')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $provider = \App\Models\User::find($r->provider_id);
                return [
                    'name' => $provider ? $provider->first_name . ' ' . $provider->last_name : 'N/A',
                    'count' => (int) $r->service_count,
                ];
            })
            ->toArray();
    }

    public function getMostRequestedServices(string $from, string $to, int $limit = 10): array {
        return AppointmentService::query()
            ->select('service_id', 'service_name', DB::raw('COUNT(*) as request_count'))
            ->whereHas('appointment', function ($q) use ($from, $to) {
                $q->whereBetween('appointment_date', [$from, $to])
                    ->where('status', '>=', 0);
            })
            ->groupBy('service_id', 'service_name')
            ->orderByDesc('request_count')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'name' => $r->service_name ?? 'N/A',
                'count' => $r->request_count,
            ])
            ->toArray();
    }

    public function getTopRevenueServices(string $from, string $to, int $limit = 10): array {
        $paidStatuses = array_map(fn($s) => $s->value, PaymentStatus::getSuccessfulStatuses());

        return AppointmentService::query()
            ->select('service_id', 'service_name', DB::raw('SUM(price) as revenue'), DB::raw('COUNT(*) as count'))
            ->whereHas('appointment', function ($q) use ($from, $to, $paidStatuses) {
                $q->whereBetween('appointment_date', [$from, $to])
                    ->whereIn('payment_status', $paidStatuses);
            })
            ->groupBy('service_id', 'service_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'name' => $r->service_name ?? 'N/A',
                'revenue' => (float) $r->revenue,
                'count' => $r->count,
            ])
            ->toArray();
    }

    public function getTopCustomers(string $from, string $to, int $limit = 10): array {
        return DB::table('appointments')
            ->leftJoin('users', 'appointments.customer_id', '=', 'users.id')
            ->select(
                'appointments.customer_id',
                DB::raw("COALESCE(CONCAT(users.first_name, ' ', users.last_name), MAX(appointments.customer_name), 'Guest') as cust_name"),
                DB::raw('COUNT(*) as booking_count'),
                DB::raw('SUM(appointments.total_amount) as total_spent')
            )
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
            ])
            ->toArray();
    }

    public function getRevenueOverTime(string $from, string $to): array {
        $paidStatuses = array_map(fn($s) => $s->value, PaymentStatus::getSuccessfulStatuses());

        $daysDiff = Carbon::parse($from)->diffInDays(Carbon::parse($to));

        if ($daysDiff <= 31) {
            $groupBy = 'DATE(appointment_date)';
            $format = 'Y-m-d';
        } elseif ($daysDiff <= 365) {
            $groupBy = "DATE_FORMAT(appointment_date, '%Y-%m')";
            $format = 'Y-m';
        } else {
            $groupBy = "DATE_FORMAT(appointment_date, '%Y-%m')";
            $format = 'Y-m';
        }

        return Appointment::query()
            ->select(DB::raw("$groupBy as period"), DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as count'))
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

    public function getBookingsOverTime(string $from, string $to): array {
        $daysDiff = Carbon::parse($from)->diffInDays(Carbon::parse($to));

        if ($daysDiff <= 31) {
            $groupBy = 'DATE(appointment_date)';
        } else {
            $groupBy = "DATE_FORMAT(appointment_date, '%Y-%m')";
        }

        return Appointment::query()
            ->select(
                DB::raw("$groupBy as period"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = " . AppointmentStatus::COMPLETED->value . " THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status IN (" . AppointmentStatus::USER_CANCELLED->value . "," . AppointmentStatus::ADMIN_CANCELLED->value . ") THEN 1 ELSE 0 END) as cancelled")
            )
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

    public function getPeakHours(string $from, string $to): array {
        return Appointment::query()
            ->select(DB::raw("EXTRACT(HOUR FROM start_time) as hour"), DB::raw('COUNT(*) as count'))
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

    public function getPeakDays(string $from, string $to): array {
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

    public function getAvgServiceDuration(string $from, string $to): float {
        return (float) (AppointmentService::query()
            ->whereHas('appointment', function ($q) use ($from, $to) {
                $q->whereBetween('appointment_date', [$from, $to])
                    ->where('status', AppointmentStatus::COMPLETED->value);
            })
            ->avg('duration_minutes') ?? 0);
    }

    public function getProviderUtilization(string $from, string $to): array {
        $workdayHours = 8;

        $daysDiff = max(1, Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1);

        return Appointment::query()
            ->select(
                'provider_id',
                DB::raw('SUM(duration_minutes) as total_minutes'),
                DB::raw('COUNT(*) as appointment_count')
            )
            ->whereBetween('appointment_date', [$from, $to])
            ->where('status', AppointmentStatus::COMPLETED->value)
            ->whereNotNull('provider_id')
            ->groupBy('provider_id')
            ->with('provider:id,first_name,last_name')
            ->orderByDesc('total_minutes')
            ->get()
            ->map(function ($r) use ($daysDiff, $workdayHours) {
                $availableMinutes = $daysDiff * $workdayHours * 60;
                return [
                    'name' => $r->provider ? $r->provider->first_name . ' ' . $r->provider->last_name : 'N/A',
                    'hours' => round($r->total_minutes / 60, 1),
                    'appointments' => $r->appointment_count,
                    'utilization' => $availableMinutes > 0 ? round(($r->total_minutes / $availableMinutes) * 100, 1) : 0,
                ];
            })
            ->toArray();
    }
}
