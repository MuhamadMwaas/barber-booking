<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Schemas;

use App\Models\ProviderScheduledWork;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Carbon\Carbon;

class ProviderScheduledWorkForm
{
    /**
     * Configure the weekly schedule form for editing.
     */
    public static function configure(Schema $schema, ?int $userId = null): Schema
    {
        $days = ProviderScheduledWork::getLocalizedDays();
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('resources.provider_scheduled_work.provider_info'))
                    ->description(__('resources.provider_scheduled_work.managing_schedule'))
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Placeholder::make('provider_name')
                            ->label(__('resources.provider_scheduled_work.provider'))
                            ->content(function () use ($userId) {
                                if (!$userId) {
                                    return '-';
                                }

                                $user = User::find($userId);
                                return $user ? $user->full_name . ' (' . $user->email . ')' : '-';
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Section::make(__('resources.provider_scheduled_work.weekly_schedule'))
                    ->description(__('resources.provider_scheduled_work.weekly_schedule_description'))
                    ->icon('heroicon-o-calendar-days')
                    ->schema(
                        collect($days)->map(function ($dayName, $dayNumber) {
                            return self::createDaySection($dayNumber, $dayName);
                        })->toArray()
                    )
                    ->columns(1),

                self::createSummarySection(),
            ]);
    }

    /**
     * Create a section for each day with its shift repeater.
     */
    protected static function createDaySection(int $dayNumber, string $dayName): Section
    {
        return Section::make($dayName)
            ->icon(self::getDayIcon($dayNumber))
            ->compact()
            ->schema([
                Repeater::make("days.{$dayNumber}.shifts")
                    ->label(__('resources.provider_scheduled_work.shifts'))
                    ->addActionLabel(__('resources.provider_scheduled_work.add_shift'))
                    ->default([])
                    ->grid(4)
                    ->itemLabel(function (?array $state): ?string {
                        if (!$state) {
                            return null;
                        }

                        $start = $state['start_time'] ?? null;
                        $end = $state['end_time'] ?? null;

                        if (!$start || !$end) {
                            return __('resources.provider_scheduled_work.shift');
                        }

                        return "{$start} -> {$end}";
                    })
                    ->schema([
                        Toggle::make('is_work_day')
                            ->label(__('resources.provider_scheduled_work.work_day'))
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),

                        TimePicker::make('start_time')
                            ->label(__('resources.provider_scheduled_work.start_time'))
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->format('H:i:s')
                            ->default('09:00:00')
                            ->disabled(fn (Get $get) => !$get('is_work_day'))
                            ->required(fn (Get $get) => $get('is_work_day'))
                            ->columnSpan(1),

                        TimePicker::make('end_time')
                            ->label(__('resources.provider_scheduled_work.end_time'))
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('H:i')
                            ->format('H:i:s')
                            ->default('17:00:00')
                            ->disabled(fn (Get $get) => !$get('is_work_day'))
                            ->required(fn (Get $get) => $get('is_work_day'))
                            ->columnSpan(1),

                        TextInput::make('break_minutes')
                            ->label(__('resources.provider_scheduled_work.break_minutes'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(480)
                            ->default(0)
                            ->suffix(__('resources.provider_scheduled_work.minutes'))
                            ->disabled(fn (Get $get) => !$get('is_work_day'))
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label(__('resources.provider_scheduled_work.active'))
                            ->default(true)
                            ->inline(false)
                            ->disabled(fn (Get $get) => !$get('is_work_day'))
                            ->columnSpan(1),
                    ]),
            ]);
    }

    /**
     * Icon per day of week.
     */
    protected static function getDayIcon(int $dayNumber): string
    {
        return match ($dayNumber) {
            0 => 'heroicon-o-sun',
            1 => 'heroicon-o-moon',
            2 => 'heroicon-o-star',
            3 => 'heroicon-o-sparkles',
            4 => 'heroicon-o-fire',
            5 => 'heroicon-o-heart',
            6 => 'heroicon-o-home',
            default => 'heroicon-o-calendar',
        };
    }

    protected static function createSummarySection(): Section
    {
        return Section::make(__('resources.provider_scheduled_work.summary'))
            ->description(__('resources.provider_scheduled_work.summary_description'))
            ->icon('heroicon-o-presentation-chart-line')
            ->columns(3)
            ->schema([
                Placeholder::make('total_working_minutes')
                    ->label(__('resources.provider_scheduled_work.total_working_minutes'))
                    ->content(function (Get $get) {
                        $minutes = self::calculateWeeklyWorkingMinutes($get('days') ?? []);
                     $totalMinutes = 0;

foreach ($get('days')as $day) {
    foreach ($day['shifts'] as $shiftId => $shift) {
        // تجاهل إذا ليس يوم عمل
        if (empty($shift['is_work_day'])) {
            continue;
        }

        $minutes = self::getShiftDurationInMinutesAttribute(
            $shift['start_time'],
            $shift['end_time']
        );
        // خصم الاستراحة إذا موجود
        if (!empty($shift['break_minutes'])) {
            $minutes -= $shift['break_minutes'];
        }

        $totalMinutes += $minutes;
    }
};
                        return self::formatMinutes($totalMinutes);
                    }),
                Placeholder::make('active_days_count')
                    ->label(__('resources.provider_scheduled_work.active_days_count'))
                    ->content(function (Get $get) {
                        return self::countActiveDays($get('days') ?? []);
                    }),
                Placeholder::make('total_shifts_count')
                    ->label(__('resources.provider_scheduled_work.total_shifts_count'))
                    ->content(function (Get $get) {
                        return self::countTotalShifts($get('days') ?? []);
                    }),
            ]);
    }

    /**
     * Load existing schedule data into the repeater friendly structure.
     */
    public static function loadScheduleData(int $userId): array
    {
        $schedules = ProviderScheduledWork::where('user_id', $userId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $data = ['days' => []];

        for ($i = 0; $i < 7; $i++) {
            $data['days'][$i] = [
                'shifts' => [],
            ];
        }

        foreach ($schedules as $schedule) {
            $data['days'][$schedule->day_of_week]['shifts'][] = [
                'is_work_day' => $schedule->is_work_day,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'break_minutes' => $schedule->break_minutes ?? 0,
                'is_active' => $schedule->is_active,
            ];
        }

        return $data;
    }

    /**
     * Save the weekly schedule using the repeater structure.
     */
    public static function saveScheduleData(int $userId, array $data): void
    {
        ProviderScheduledWork::where('user_id', $userId)->delete();

        $days = $data['days'] ?? [];

        foreach ($days as $dayNumber => $dayData) {
            foreach ($dayData['shifts'] ?? [] as $shift) {
                $isWorkDay = (bool) ($shift['is_work_day'] ?? false);

                if (!$isWorkDay) {
                    continue;
                }

                ProviderScheduledWork::create([
                    'user_id' => $userId,
                    'day_of_week' => $dayNumber,
                    'is_work_day' => $isWorkDay,
                    'start_time' => $shift['start_time'] ?? null,
                    'end_time' => $shift['end_time'] ?? null,
                    'break_minutes' => $shift['break_minutes'] ?? 0,
                    'is_active' => $shift['is_active'] ?? true,
                ]);
            }
        }
    }

    /**
     * Validate weekly schedule data including overlap checks.
     */
    public static function validateSchedule(array $data): array
    {
        $errors = [];

        $localizedDays = ProviderScheduledWork::getLocalizedDays();
        $days = $data['days'] ?? [];
        foreach ($days as $dayNumber => $dayData) {
            $dayName = $localizedDays[$dayNumber] ?? $dayNumber;
            $shifts = $dayData['shifts'] ?? [];
            $workableShifts = [];

            foreach ($shifts as $index => $shift) {
                $pathPrefix = "days.{$dayNumber}.shifts.{$index}";
                $isWorkDay = (bool) ($shift['is_work_day'] ?? false);

                if (!$isWorkDay) {
                    continue;
                }

                $startTime = $shift['start_time'] ?? null;
                $endTime = $shift['end_time'] ?? null;
                $breakMinutes = (int) ($shift['break_minutes'] ?? 0);
                $isActive = (bool) ($shift['is_active'] ?? false);

                if (!$startTime) {
                    $errors["{$pathPrefix}.start_time"] = __('resources.provider_scheduled_work.start_time_required', ['day' => $dayName]);
                }

                if (!$endTime) {
                    $errors["{$pathPrefix}.end_time"] = __('resources.provider_scheduled_work.end_time_required', ['day' => $dayName]);
                }

                if ($startTime && $endTime && $startTime === $endTime) {
                    $errors["{$pathPrefix}.end_time"] = __('resources.provider_scheduled_work.validation.end_time_must_differ');
                }

                if ($breakMinutes < 0) {
                    $errors["{$pathPrefix}.break_minutes"] = __('resources.provider_scheduled_work.break_minutes_min');
                }

                if ($breakMinutes > 480) {
                    $errors["{$pathPrefix}.break_minutes"] = __('resources.provider_scheduled_work.break_minutes_max');
                }

                if ($startTime && $endTime) {
                    $durationMinutes = self::getShiftDurationInMinutesAttribute($startTime, $endTime);

                    if ($breakMinutes >= $durationMinutes) {
                        $errors["{$pathPrefix}.break_minutes"] = __('resources.provider_scheduled_work.validation.break_exceeds_duration');
                    }
                }

                if ($startTime && $endTime && $isActive) {
                    $workableShifts[] = [
                        'index' => $index,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ];
                }
            }

            $count = count($workableShifts);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $first = $workableShifts[$i];
                    $second = $workableShifts[$j];

                    if (ProviderScheduledWork::shiftsOverlap(
                        $first['start_time'],
                        $first['end_time'],
                        $second['start_time'],
                        $second['end_time']
                    )) {
                        $message = __('resources.provider_scheduled_work.validation.shift_overlap', [
                            'day' => $dayName,
                            'start' => $first['start_time'],
                            'end' => $first['end_time'],
                        ]);

                        $errors["days.{$dayNumber}.shifts.{$first['index']}.start_time"] = $message;
                        $errors["days.{$dayNumber}.shifts.{$second['index']}.start_time"] = $message;
                    }
                }
            }
        }

        return $errors;
    }

    protected static function calculateDuration(string $start, string $end): int
    {
        $startMinutes = ProviderScheduledWork::timeToMinutes($start);
        $endMinutes = ProviderScheduledWork::timeToMinutes($end);

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return $endMinutes - $startMinutes;
    }

    protected static function calculateWeeklyWorkingMinutes(array $days): int
    {
        $minutes = 0;

        foreach ($days as $day) {
            foreach ($day['shifts'] ?? [] as $shift) {
                if (!($shift['is_work_day'] ?? false)) {
                    continue;
                }

                if (!($shift['is_active'] ?? false)) {
                    continue;
                }

                $start = $shift['start_time'] ?? null;
                $end = $shift['end_time'] ?? null;

                if (!$start || !$end) {
                    continue;
                }

                $duration = self::calculateDuration($start, $end);
                $minutes += $duration;
            }
        }

        return $minutes;
    }

    protected static function countActiveDays(array $days): int
    {
        $activeDays = 0;

        foreach ($days as $day) {
            $hasActiveShift = collect($day['shifts'] ?? [])
                ->contains(function ($shift) {
                    return ($shift['is_work_day'] ?? false) && ($shift['is_active'] ?? false);
                });

            if ($hasActiveShift) {
                $activeDays++;
            }
        }

        return $activeDays;
    }

    protected static function countTotalShifts(array $days): int
    {
        $count = 0;

        foreach ($days as $day) {
            foreach ($day['shifts'] ?? [] as $shift) {
                if ($shift['is_work_day'] ?? false) {
                    $count++;
                }
            }
        }

        return $count;
    }

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

public static function getShiftDurationInMinutesAttribute(string|Carbon $start, string|Carbon $end): int
{
    // parse with app timezone to avoid implicit UTC/local issues
    $tz = config('app.timezone') ?: 'UTC';

    $start = $start instanceof Carbon
        ? $start->copy()->setTimezone($tz)
        : Carbon::parse($start, $tz);

    $end = $end instanceof Carbon
        ? $end->copy()->setTimezone($tz)
        : Carbon::parse($end, $tz);

    // إذا النهاية أصغر من البداية => انتهت بعد منتصف الليل
    if ($end->lessThan($start)) {
        $end->addDay();
    }

    // نستخدم start->diffInMinutes(end) مع absolute = true لضمان عدد موجب
    return (int) $start->diffInMinutes($end, /* $absolute = */ true);
}
}
