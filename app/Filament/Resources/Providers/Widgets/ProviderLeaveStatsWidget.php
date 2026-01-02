<?php

namespace App\Filament\Resources\Providers\Widgets;

use App\Models\ProviderTimeOff;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ProviderLeaveStatsWidget extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $userId = $this->record->id;

        // Total leaves this year
        $totalLeavesThisYear = ProviderTimeOff::where('user_id', $userId)
            ->whereYear('start_date', now()->year)
            ->count();

        // Total leave days used this year
        $totalDaysUsed = ProviderTimeOff::where('user_id', $userId)
            ->whereYear('start_date', now()->year)
            ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
            ->sum('duration_days');

        // Total leave hours used this year
        $totalHoursUsed = ProviderTimeOff::where('user_id', $userId)
            ->whereYear('start_date', now()->year)
            ->where('type', ProviderTimeOff::TYPE_HOURLY)
            ->sum('duration_hours');

        // Upcoming leaves
        $upcomingLeaves = ProviderTimeOff::where('user_id', $userId)
            ->where('start_date', '>=', now()->toDateString())
            ->count();

        // Current month leaves
        $currentMonthLeaves = ProviderTimeOff::where('user_id', $userId)
            ->whereYear('start_date', now()->year)
            ->whereMonth('start_date', now()->month)
            ->count();

        // Active leaves (currently on leave)
        $activeLeaves = ProviderTimeOff::where('user_id', $userId)
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->count();

        return [
            Stat::make(__('resources.provider_resource.total_leaves_this_year'), $totalLeavesThisYear)
                ->description(__('resources.provider_resource.all_leave_types'))
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('info')
                ->chart([7, 3, 4, 5, 6, 3, 5, 6, 8, 4, 6, $totalLeavesThisYear]),

            Stat::make(__('resources.provider_resource.total_days_used'), $totalDaysUsed)
                ->description(__('resources.provider_resource.full_day_leaves_only'))
                ->descriptionIcon('heroicon-o-sun')
                ->color('warning')
                ->chart([2, 1, 3, 2, 4, 1, 3, 2, 5, 3, 4, $totalDaysUsed]),

            Stat::make(__('resources.provider_resource.total_hours_used'), number_format($totalHoursUsed, 1))
                ->description(__('resources.provider_resource.hourly_leaves_only'))
                ->descriptionIcon('heroicon-o-clock')
                ->color('success')
                ->chart([3, 2, 4, 3, 2, 5, 3, 4, 2, 5, 3, $totalHoursUsed]),

            Stat::make(__('resources.provider_resource.upcoming_leaves'), $upcomingLeaves)
                ->description(__('resources.provider_resource.scheduled_for_future'))
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('primary')
                ->chart([0, 0, 1, 0, 2, 1, 0, 3, 1, 2, 1, $upcomingLeaves]),

            Stat::make(__('resources.provider_resource.current_month_leaves'), $currentMonthLeaves)
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info')
                ->chart([0, 1, 0, 2, 1, 3, 1, 2, 0, 1, 2, $currentMonthLeaves]),

            Stat::make(__('resources.provider_resource.active_leaves'), $activeLeaves)
                ->description($activeLeaves > 0 ? __('resources.provider_resource.currently_on_leave') : __('resources.provider_resource.not_on_leave'))
                ->descriptionIcon($activeLeaves > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->color($activeLeaves > 0 ? 'danger' : 'success'),
        ];
    }
}
