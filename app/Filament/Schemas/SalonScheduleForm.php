<?php

namespace App\Filament\Schemas;

use App\Models\Branch;
use App\Models\SalonSchedule;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Carbon\Carbon;

class SalonScheduleForm
{
    /**
     * Configure the weekly salon schedule form.
     */
    public static function configure(Schema $schema, ?int $branchId = null): Schema
    {
        $days = self::getLocalizedDays();

        return $schema
            ->columns(1)
            ->components([
                // معلومات الفرع
                Section::make(__('salon_schedule.branch_info'))
                    ->description(__('salon_schedule.managing_schedule'))
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        Select::make('branch_id')
                            ->label(__('salon_schedule.branch'))
                            ->options(Branch::all()->pluck('name', 'id'))
                            ->default($branchId)
                            ->required()
                            ->live()
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                // جدول الأسبوع
                Section::make(__('salon_schedule.weekly_schedule'))
                    ->description(__('salon_schedule.weekly_schedule_description'))
                    ->icon('heroicon-o-calendar-days')
                    ->schema(
                        collect($days)->map(function ($dayName, $dayNumber) {
                            return self::createDaySection($dayNumber, $dayName);
                        })->toArray()
                    ),

                // ملخص
                self::createSummarySection(),
            ]);
    }

    /**
     * Create a section for each day
     */
    protected static function createDaySection(int $dayNumber, string $dayName): Section
    {
        return Section::make($dayName)
            ->icon(self::getDayIcon($dayNumber))
            ->columns(4)
            ->compact()
            ->schema([
                // هل الصالون مفتوح؟
                Toggle::make("days.{$dayNumber}.is_open")
                    ->label(__('salon_schedule.is_open'))
                    ->default(true)
                    ->inline(false)
                    ->live()
                    ->columnSpan(1),

                // وقت الفتح
                TimePicker::make("days.{$dayNumber}.open_time")
                    ->label(__('salon_schedule.open_time'))
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('H:i')
                    ->format('H:i:s')
                    ->default('09:00:00')
                    ->disabled(fn ($get) => !$get("days.{$dayNumber}.is_open"))
                    ->required(fn ($get) => $get("days.{$dayNumber}.is_open"))
                    ->columnSpan(1),

                // وقت الإغلاق
                TimePicker::make("days.{$dayNumber}.close_time")
                    ->label(__('salon_schedule.close_time'))
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('H:i')
                    ->format('H:i:s')
                    ->default('21:00:00')
                    ->disabled(fn ($get) => !$get("days.{$dayNumber}.is_open"))
                    ->required(fn ($get) => $get("days.{$dayNumber}.is_open"))
                    ->columnSpan(1),

                // عدد ساعات العمل (محسوبة تلقائياً)
                Placeholder::make("days.{$dayNumber}.working_hours")
                    ->label(__('salon_schedule.working_hours'))
                    ->content(function (Get $get) use ($dayNumber) {
                        $isOpen = $get("days.{$dayNumber}.is_open");

                        if (!$isOpen) {
                            return __('salon_schedule.closed');
                        }

                        $openTime = $get("days.{$dayNumber}.open_time");
                        $closeTime = $get("days.{$dayNumber}.close_time");

                        if (!$openTime || !$closeTime) {
                            return '-';
                        }

                        $minutes = self::calculateDuration($openTime, $closeTime);
                        return self::formatMinutes($minutes);
                    })
                    ->columnSpan(1),
            ]);
    }

    /**
     * Get icon for each day
     */
    protected static function getDayIcon(int $dayNumber): string
    {
        return match($dayNumber) {
            0 => 'heroicon-o-sun',           // الأحد
            1 => 'heroicon-o-moon',          // الإثنين
            2 => 'heroicon-o-star',          // الثلاثاء
            3 => 'heroicon-o-sparkles',      // الأربعاء
            4 => 'heroicon-o-fire',          // الخميس
            5 => 'heroicon-o-heart',         // الجمعة
            6 => 'heroicon-o-home',          // السبت
            default => 'heroicon-o-calendar',
        };
    }

    /**
     * Create summary section
     */
    protected static function createSummarySection(): Section
    {
        return Section::make(__('salon_schedule.summary'))
            ->description(__('salon_schedule.summary_description'))
            ->icon('heroicon-o-presentation-chart-line')
            ->columns(3)
            ->schema([
                Placeholder::make('total_weekly_hours')
                    ->label(__('salon_schedule.total_weekly_hours'))
                    ->content(function (Get $get) {
                        $minutes = self::calculateWeeklyWorkingMinutes($get('days') ?? []);
                        return self::formatMinutes($minutes);
                    }),

                Placeholder::make('open_days_count')
                    ->label(__('salon_schedule.open_days_count'))
                    ->content(function (Get $get) {
                        return self::countOpenDays($get('days') ?? []);
                    }),

                Placeholder::make('average_daily_hours')
                    ->label(__('salon_schedule.average_daily_hours'))
                    ->content(function (Get $get) {
                        $days = $get('days') ?? [];
                        $openDays = self::countOpenDays($days);

                        if ($openDays === 0) {
                            return '0h';
                        }

                        $totalMinutes = self::calculateWeeklyWorkingMinutes($days);
                        $avgMinutes = (int) ($totalMinutes / $openDays);

                        return self::formatMinutes($avgMinutes);
                    }),
            ]);
    }

    /**
     * Load existing schedule data
     */
    public static function loadScheduleData(int $branchId): array
    {
        $schedules = SalonSchedule::where('branch_id', $branchId)->get();

        $data = [
            'branch_id' => $branchId,
            'days' => []
        ];

        // Initialize all days with defaults
        for ($i = 0; $i < 7; $i++) {
            $data['days'][$i] = [
                'is_open' => false,
                'open_time' => '09:00:00',
                'close_time' => '21:00:00',
            ];
        }

        // Fill with existing data
        foreach ($schedules as $schedule) {
            $data['days'][$schedule->day_of_week] = [
                'is_open' => $schedule->is_open,
                'open_time' => $schedule->open_time,
                'close_time' => $schedule->close_time,
                'id' => $schedule->id,
            ];
        }

        return $data;
    }

    /**
     * Save schedule data
     */
    public static function saveScheduleData(int $branchId, array $data): void
    {
        // Get existing schedules
        $existingSchedules = SalonSchedule::where('branch_id', $branchId)
            ->get()
            ->keyBy('day_of_week');

        // Process each day
        foreach ($data['days'] as $dayNumber => $dayData) {
            $scheduleData = [
                'branch_id' => $branchId,
                'day_of_week' => $dayNumber,
                'is_open' => $dayData['is_open'] ?? false,
            ];

            // Add time fields only if salon is open
            if ($dayData['is_open']) {
                $scheduleData['open_time'] = $dayData['open_time'];
                $scheduleData['close_time'] = $dayData['close_time'];
            } else {
                $scheduleData['open_time'] = null;
                $scheduleData['close_time'] = null;
            }

            // Update or create
            if (isset($existingSchedules[$dayNumber])) {
                $existingSchedules[$dayNumber]->update($scheduleData);
            } else {
                SalonSchedule::create($scheduleData);
            }
        }
    }

    /**
     * Validate schedule data
     */
    public static function validateSchedule(array $data): array
    {
        $errors = [];

        foreach ($data['days'] as $dayNumber => $dayData) {
            // Skip validation for closed days
            if (!($dayData['is_open'] ?? false)) {
                continue;
            }

            $dayName = self::getLocalizedDays()[$dayNumber];

            // Validate time fields
            if (empty($dayData['open_time'])) {
                $errors["days.{$dayNumber}.open_time"] = __('salon_schedule.open_time_required', ['day' => $dayName]);
            }

            if (empty($dayData['close_time'])) {
                $errors["days.{$dayNumber}.close_time"] = __('salon_schedule.close_time_required', ['day' => $dayName]);
            }

            // Validate that close time is different from open time
            if (!empty($dayData['open_time']) && !empty($dayData['close_time'])) {
                if ($dayData['open_time'] === $dayData['close_time']) {
                    $errors["days.{$dayNumber}.close_time"] = __('salon_schedule.validation.close_time_must_differ');
                }
            }
        }

        return $errors;
    }

    /**
     * Calculate duration between two times
     */
    protected static function calculateDuration(string $start, string $end): int
    {
        $tz = config('app.timezone') ?: 'UTC';

        $startTime = Carbon::parse($start, $tz);
        $endTime = Carbon::parse($end, $tz);

        if ($endTime->lessThan($startTime)) {
            $endTime->addDay();
        }

        return (int) $startTime->diffInMinutes($endTime, true);
    }

    /**
     * Calculate weekly working minutes
     */
    protected static function calculateWeeklyWorkingMinutes(array $days): int
    {
        $minutes = 0;

        foreach ($days as $day) {
            if (!($day['is_open'] ?? false)) {
                continue;
            }

            $open = $day['open_time'] ?? null;
            $close = $day['close_time'] ?? null;

            if (!$open || !$close) {
                continue;
            }

            $duration = self::calculateDuration($open, $close);
            $minutes += $duration;
        }

        return $minutes;
    }

    /**
     * Count open days
     */
    protected static function countOpenDays(array $days): int
    {
        $count = 0;

        foreach ($days as $day) {
            if ($day['is_open'] ?? false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Format minutes to human readable format
     */
    public static function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours > 0 && $remaining > 0) {
            return "{$hours}h {$remaining}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$remaining}m";
    }

    /**
     * Get localized days
     */
    public static function getLocalizedDays(): array
    {
        return [
            0 => __('salon_schedule.days.sunday'),
            1 => __('salon_schedule.days.monday'),
            2 => __('salon_schedule.days.tuesday'),
            3 => __('salon_schedule.days.wednesday'),
            4 => __('salon_schedule.days.thursday'),
            5 => __('salon_schedule.days.friday'),
            6 => __('salon_schedule.days.saturday'),
        ];
    }
}
