<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkForm;
use App\Models\ProviderScheduledWork;
use App\Models\User;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ViewProviderScheduleTimeline extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected  string $view = 'filament.pages.view-provider-schedule-timeline';

    /**
     * Form data
     */
    public ?array $data = [];

    /**
     * Timeline data for rendering
     */
    public array $weeklySchedule = [];
    public array $timeSlots = [];
    public int $totalWorkingMinutes = 0;
    public int $activeDaysCount = 0;
    public int $totalShiftsCount = 0;
    public ?User $providerModel = null;

    /**
     * Show in navigation
     */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * Navigation sort order
     */
    protected static ?int $navigationSort = 30;

    /**
     * Current provider being viewed
     */
    public ?\App\Models\User $provider = null;

    /**
     * Mount lifecycle hook
     */
    public function mount(): void
    {
        // Get userId from query parameter if available
        $userId = request()->query('userId');

        // Load provider information
        if ($userId) {
            $this->provider = \App\Models\User::find($userId);
            $this->providerModel = $this->provider;
        }

        // Initialize form with userId if provided
        $this->data = [
            'user_id' => $userId,
            'selected_user_id' => $userId,
        ];

        // Build timeline data for view
        $this->timeSlots = $this->buildTimeSlots();
        if ($userId) {
            $this->buildWeeklyTimeline((int) $userId);
        }
    }

    /**
     * Configure the schema
     */
    public function schema(Schema $schema): Schema
    {
        return ProviderScheduledWorkForm::configure($schema)
            ->statePath('data');
    }

    /**
     * Navigation label
     */
    public static function getNavigationLabel(): string
    {
        return __('schedule.navigation_label');
    }

    /**
     * Navigation group
     */
    public static function getNavigationGroup(): ?string
    {
        return __('schedule.navigation_group');
    }

    /**
     * عنوان الصفحة
     */
    public function getTitle(): string|Htmlable
    {
        return __('schedule.page_title');
    }

    /**
     * تحديث عنوان الصفحة في المتصفح
     */
    public function getHeading(): string|Htmlable
    {
        return __('schedule.page_heading');
    }

    /**
     * الوصف الفرعي للصفحة
     */
    public function getSubheading(): string|Htmlable|null
    {
        if ($this->provider) {
            return __('schedule.managing_schedule_for', ['name' => $this->provider->full_name]);
        }

        return __('schedule.page_subheading');
    }

    /**
     * Check access permissions
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Admin يمكنه الوصول دائماً
        if ($user->hasRole('admin')) {
            return true;
        }

        // التحقق من صلاحية محددة
        if ($user->can('manage provider schedules')) {
            return true;
        }

        // Manager يمكنه الوصول
        if ($user->hasRole('manager')) {
            return true;
        }

        return false;
    }

    /**
     * Header Actions - removed, no longer needed
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Prepare 30-minute slot labels for the timeline.
     */
    protected function buildTimeSlots(): array
    {
        $slots = [];
        for ($h = 0; $h < 24; $h++) {
            $slots[] = sprintf('%02d:00', $h);
            $slots[] = sprintf('%02d:30', $h);
        }
        return $slots;
    }

    /**
     * Build weekly schedule and metrics for the given provider.
     */
    protected function buildWeeklyTimeline(int $userId): void
    {
        $days = ProviderScheduledWork::getLocalizedDays();
        $week = [];
        foreach ($days as $num => $name) {
            $week[$num] = [
                'label' => $name,
                'shifts' => [],
            ];
        }

        $shifts = ProviderScheduledWork::where('user_id', $userId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $totalMinutes = 0;
        $activeDays = collect();
        $totalShifts = 0;

        foreach ($shifts as $shift) {
            if (!($shift->is_work_day ?? false)) {
                continue;
            }

            $start = $shift->start_time;
            $end = $shift->end_time;
            if (!$start || !$end) {
                continue;
            }

            $isActive = (bool) ($shift->is_active ?? false);
            $duration = $this->calculateDuration($start, $end);

            if ($isActive) {
                $totalMinutes += $duration;
                $activeDays->push($shift->day_of_week);
            }

            $totalShifts++;

            // Add duration to day total
            if (!isset($week[$shift->day_of_week]['total_minutes'])) {
                $week[$shift->day_of_week]['total_minutes'] = 0;
            }
            $week[$shift->day_of_week]['total_minutes'] += $duration;

            $week[$shift->day_of_week]['shifts'][] = [
                'start' => $start,
                'end' => $end,
                'is_active' => $isActive,
                'duration_minutes' => $duration,
                'start_slot' => $this->timeToSlot($start),
                'end_slot' => $this->timeToSlot($end),
            ];
        }

        $this->weeklySchedule = $week;
        $this->totalWorkingMinutes = $totalMinutes;
        $this->activeDaysCount = $activeDays->unique()->count();
        $this->totalShiftsCount = $totalShifts;
    }

    protected function timeToSlot(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return ($h * 60 + $m) / 30;
    }

    protected function calculateDuration(string $start, string $end): int
    {
        return ProviderScheduledWorkForm::getShiftDurationInMinutesAttribute($start, $end);
        $startMinutes = ProviderScheduledWork::timeToMinutes($start);
        $endMinutes = ProviderScheduledWork::timeToMinutes($end);
        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }
        return $endMinutes - $startMinutes;
    }
}
