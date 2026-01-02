<?php

namespace App\Filament\Resources\Providers\Widgets;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProviderStatsOverviewWidget extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $userId = $this->record->id;

        // Total completed appointments
        $completedAppointments = $this->record->appointmentsAsProvider()->where('appointments.status',AppointmentStatus::COMPLETED)->count();

        // Total appointments (all statuses)
        $totalAppointments = $this->record->appointmentsAsProvider()->count();

        // Pending appointments
        $pendingAppointments = $this->record->appointmentsAsProvider()
            ->where('status', AppointmentStatus::PENDING)
            ->count();

        // Upcoming appointments
        $upcomingAppointments = $this->record->appointmentsAsProvider()
            ->where('start_time', '>', now())
            ->count();

        // Total earnings (all time)
        $totalEarnings = DB::table('payments')
            ->join('appointments', 'payments.paymentable_id', '=', 'appointments.id')
            ->where('payments.paymentable_type', 'App\\Models\\Appointment')
            ->where('appointments.provider_id', $userId)
            ->whereIn('payments.status', [
                PaymentStatus::PAID_ONLINE,
                PaymentStatus::PAID_ONSTIE_CASH,
                PaymentStatus::PAID_ONSTIE_CARD,
            ])
            ->sum('payments.amount');

        // Current month earnings
        $currentMonthEarnings = DB::table('payments')
            ->join('appointments', 'payments.paymentable_id', '=', 'appointments.id')
            ->where('payments.paymentable_type', 'App\\Models\\Appointment')
            ->where('appointments.provider_id', $userId)
            ->whereIn('payments.status', [
                PaymentStatus::PAID_ONLINE,
                PaymentStatus::PAID_ONSTIE_CASH,
                PaymentStatus::PAID_ONSTIE_CARD,
            ])
            ->whereMonth('payments.created_at', now()->month)
            ->whereYear('payments.created_at', now()->year)
            ->sum('payments.amount');

        // Last month earnings
        $lastMonth = now()->subMonth();
        $lastMonthEarnings = DB::table('payments')
            ->join('appointments', 'payments.paymentable_id', '=', 'appointments.id')
            ->where('payments.paymentable_type', 'App\\Models\\Appointment')
            ->where('appointments.provider_id', $userId)
            ->whereIn('payments.status', [
                PaymentStatus::PAID_ONLINE,
                PaymentStatus::PAID_ONSTIE_CASH,
                PaymentStatus::PAID_ONSTIE_CARD,
            ])
            ->whereMonth('payments.created_at', $lastMonth->month)
            ->whereYear('payments.created_at', $lastMonth->year)
            ->sum('payments.amount');

        // Average rating
        $averageRating = $this->record->serviceReviews()->avg('rating');
        $totalReviews = $this->record->serviceReviews()->count();

        // Services offered count
        $servicesCount = $this->record->services()->count();

        // Calculate trend for current vs last month
        $earningsTrend = $lastMonthEarnings > 0
            ? (($currentMonthEarnings - $lastMonthEarnings) / $lastMonthEarnings) * 100
            : 0;

        // Get monthly earnings for chart (last 12 months)
        $monthlyEarnings = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $earnings = DB::table('payments')
                ->join('appointments', 'payments.paymentable_id', '=', 'appointments.id')
                ->where('payments.paymentable_type', 'App\\Models\\Appointment')
                ->where('appointments.provider_id', $userId)
                ->whereIn('payments.status', [
                    PaymentStatus::PAID_ONLINE,
                    PaymentStatus::PAID_ONSTIE_CASH,
                    PaymentStatus::PAID_ONSTIE_CARD,
                ])
                ->whereMonth('payments.created_at', $month->month)
                ->whereYear('payments.created_at', $month->year)
                ->sum('payments.amount');
            $monthlyEarnings[] = (float) $earnings;
        }

        // Get monthly appointments for chart (last 12 months)
        $monthlyAppointments = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = $this->record->appointmentsAsProvider()
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();
            $monthlyAppointments[] = $count;
        }

        return [
            // Total Earnings
            Stat::make(__('resources.provider_resource.total_earnings'), number_format($totalEarnings, 2) . ' ' . __('resources.provider_resource.sar_currency'))
                ->description(__('resources.provider_resource.all_time'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart($monthlyEarnings),

            // Current Month Earnings
            Stat::make(__('resources.provider_resource.current_month_earnings'), number_format($currentMonthEarnings, 2) . ' ' . __('resources.provider_resource.sar_currency'))
                ->description(
                    $earningsTrend > 0
                        ? '+' . number_format($earningsTrend, 1) . '% ' . __('resources.provider_resource.vs_last_month')
                        : number_format($earningsTrend, 1) . '% ' . __('resources.provider_resource.vs_last_month')
                )
                ->descriptionIcon($earningsTrend > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($earningsTrend > 0 ? 'success' : 'danger')
                ->chart($monthlyEarnings),

            // Completed Appointments
            Stat::make(__('resources.provider_resource.completed_bookings'), $completedAppointments)
                ->description(__('resources.provider_resource.total_completed'))
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->chart($monthlyAppointments),

            // Total Appointments
            Stat::make(__('resources.provider_resource.total_appointments'), $totalAppointments)
                ->description(__('resources.provider_resource.all_statuses'))
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('info')
                ->chart($monthlyAppointments),

            // Upcoming Appointments
            Stat::make(__('resources.provider_resource.upcoming_appointments'), $upcomingAppointments)
                ->description(__('resources.provider_resource.scheduled_future'))
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            // Pending Appointments
            Stat::make(__('resources.provider_resource.pending_appointments'), $pendingAppointments)
                ->description(__('resources.provider_resource.awaiting_completion'))
                ->descriptionIcon('heroicon-o-exclamation-circle')
                ->color($pendingAppointments > 0 ? 'warning' : 'gray'),

            // Average Rating
            Stat::make(__('resources.provider_resource.average_rating'),
                $averageRating
                    ? number_format($averageRating, 1) . ' ★'
                    : __('resources.provider_resource.no_reviews_yet')
            )
                ->description($totalReviews . ' ' . __('resources.provider_resource.total_reviews'))
                ->descriptionIcon('heroicon-o-star')
                ->color('warning'),

            // Services Count
            Stat::make(__('resources.provider_resource.services_offered'), $servicesCount)
                ->description(__('resources.provider_resource.active_services'))
                ->descriptionIcon('heroicon-o-wrench-screwdriver')
                ->color('primary'),
        ];
    }
}
