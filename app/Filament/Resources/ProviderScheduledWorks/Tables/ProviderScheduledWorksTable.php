<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Tables;

use App\Filament\Pages\ViewProviderScheduleTimeline;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Tables\Actions\Action as TableAction;

class ProviderScheduledWorksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereHas('roles', function ($q) {
                        $q->where('name', 'provider');
                    })
                    ->with(['scheduledWorks', 'timeOffs'])
            )
            ->columns([
                // Provider Name with Email
                TextColumn::make('full_name')
                    ->label(__('resources.provider_scheduled_work.provider'))
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-user')
                    ->color('primary')
                    ->description(fn($record) => $record->email),

                // Work Days Count
                TextColumn::make('work_days_count')
                    ->label(__('resources.provider_scheduled_work.work_days_count'))
                    ->getStateUsing(function ($record) {
                        return $record->scheduledWorks()
                            ->where('is_work_day', true)
                            ->where('is_active', true)
                            ->count();
                    })
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-calendar-days')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount([
                            'scheduledWorks as work_days_count' => function ($q) {
                                $q->where('is_work_day', true)->where('is_active', true);
                            }
                        ])->orderBy('work_days_count', $direction);
                    }),

                // Off Days Count
                TextColumn::make('off_days_count')
                    ->label(__('resources.provider_scheduled_work.off_days_count'))
                    ->getStateUsing(function ($record) {
                        $workDays = $record->scheduledWorks()
                            ->where('is_work_day', true)
                            ->where('is_active', true)
                            ->count();
                        return 7 - $workDays;
                    })
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-x-circle')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount([
                            'scheduledWorks as work_days_count' => function ($q) {
                                $q->where('is_work_day', true)->where('is_active', true);
                            }
                        ])->orderBy('work_days_count', $direction === 'asc' ? 'desc' : 'asc');
                    }),

                // Weekly Hours
                TextColumn::make('weekly_hours')
                    ->label(__('resources.provider_scheduled_work.weekly_hours'))
                    ->getStateUsing(function ($record) {
                        $totalMinutes = 0;
                        $workSchedules = $record->scheduledWorks()
                            ->where('is_work_day', true)
                            ->where('is_active', true)
                            ->get();

                        foreach ($workSchedules as $schedule) {
                            if ($schedule->start_time && $schedule->end_time) {
                                $start = Carbon::parse($schedule->start_time);
                                $end = Carbon::parse($schedule->end_time);
                                $dayMinutes = $start->diffInMinutes($end);

                                // Subtract break time
                                if ($schedule->break_minutes > 0) {
                                    // $dayMinutes -= $schedule->break_minutes;
                                }

                                $totalMinutes += $dayMinutes;
                            }
                        }

                        $hours = floor($totalMinutes / 60);
                        $minutes = $totalMinutes % 60;

                        return $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'm' : '');
                    })
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->weight(FontWeight::SemiBold)
                    ->description(__('resources.provider_scheduled_work.effective_hours')),

                // Time Offs Count
                TextColumn::make('time_offs_count')
                    ->label(__('resources.provider_scheduled_work.time_offs_count'))
                    ->getStateUsing(function ($record) {
                        return $record->timeOffs()->count();
                    })
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state === 0 => 'gray',
                        $state <= 3 => 'success',
                        $state <= 6 => 'warning',
                        default => 'danger',
                    })
                    ->icon('heroicon-o-calendar-days')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('timeOffs')->orderBy('time_offs_count', $direction);
                    })
                    ->description(__('resources.provider_scheduled_work.total_time_offs')),

                // Upcoming Time Offs
                TextColumn::make('upcoming_time_offs')
                    ->label(__('resources.provider_scheduled_work.upcoming_time_offs'))
                    ->getStateUsing(function ($record) {
                        return $record->timeOffs()
                            ->where('start_date', '>=', now()->toDateString())
                            ->count();
                    })
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-calendar')
                    ->toggleable(),

                // Active Schedule Status
                IconColumn::make('has_active_schedule')
                    ->label(__('resources.provider_scheduled_work.schedule_status'))
                    ->getStateUsing(function ($record) {
                        return $record->scheduledWorks()
                            ->where('is_work_day', true)
                            ->where('is_active', true)
                            ->exists();
                    })
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                // Timestamps
                TextColumn::make('created_at')
                    ->label(__('resources.provider_scheduled_work.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->icon('heroicon-o-calendar')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('resources.provider_scheduled_work.updated_at'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter by work days count
                SelectFilter::make('work_days_range')
                    ->label(__('resources.provider_scheduled_work.filter_by_work_days'))
                    ->options([
                        '0' => __('resources.provider_scheduled_work.no_work_days'),
                        '1-3' => '1-3 ' . __('resources.provider_scheduled_work.days'),
                        '4-5' => '4-5 ' . __('resources.provider_scheduled_work.days'),
                        '6-7' => '6-7 ' . __('resources.provider_scheduled_work.days'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value'])) {
                            return $query;
                        }

                        $value = $data['value'];

                        return $query->withCount([
                            'scheduledWorks as work_days_count' => function ($q) {
                                $q->where('is_work_day', true)->where('is_active', true);
                            }
                        ])->having('work_days_count', '>=', match ($value) {
                                    '0' => 0,
                                    '1-3' => 1,
                                    '4-5' => 4,
                                    '6-7' => 6,
                                    default => 0,
                                })->having('work_days_count', '<=', match ($value) {
                                    '0' => 0,
                                    '1-3' => 3,
                                    '4-5' => 5,
                                    '6-7' => 7,
                                    default => 7,
                                });
                    }),

                // Filter by active schedule
                TernaryFilter::make('has_active_schedule')
                    ->label(__('resources.provider_scheduled_work.has_schedule'))
                    ->placeholder(__('resources.salon_setting.all_settings'))
                    ->trueLabel(__('resources.provider_scheduled_work.with_schedule'))
                    ->falseLabel(__('resources.provider_scheduled_work.without_schedule'))
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('scheduledWorks', function ($q) {
                            $q->where('is_work_day', true)->where('is_active', true);
                        }),
                        false: fn(Builder $query) => $query->whereDoesntHave('scheduledWorks', function ($q) {
                            $q->where('is_work_day', true)->where('is_active', true);
                        }),
                    ),

                // Filter by time offs
                TernaryFilter::make('has_time_offs')
                    ->label(__('resources.provider_scheduled_work.has_time_offs'))
                    ->placeholder(__('resources.salon_setting.all_settings'))
                    ->trueLabel(__('resources.provider_scheduled_work.with_time_offs'))
                    ->falseLabel(__('resources.provider_scheduled_work.without_time_offs'))
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('timeOffs'),
                        false: fn(Builder $query) => $query->whereDoesntHave('timeOffs'),
                    ),
            ])
            ->defaultSort('first_name', 'asc')
            ->recordActions([
                EditAction::make()->url(fn($record) => route('filament.admin.resources.provider-scheduled-works.edit', [
                    'record' => $record->id,
                    'userId' => $record->user_id,
                ])),
                ViewAction::make(),
      ViewAction::make('timeline')
                    ->label(__('schedule.view_timeline') ?? 'View Timeline')
                    ->icon('heroicon-o-presentation-chart-bar')
                    ->color('info')
                    ->url(fn ($record) => ViewProviderScheduleTimeline::getUrl(['userId' => $record->id]))
                    ->openUrlInNewTab(),
                ActionsAction::make('manage_schedule')
                    ->label(__('resources.provider_scheduled_work.manage_schedule'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn($record) => ViewProviderScheduleTimeline::getUrl() . '?userId=' . $record->id)
                    ->tooltip(__('resources.provider_scheduled_work.manage_schedule_tooltip')),
            ])

            ->bulkActions([
                BulkActionGroup::make([
                    ActionsDeleteBulkAction::make(),
                ]),
            ]);
    }
}
