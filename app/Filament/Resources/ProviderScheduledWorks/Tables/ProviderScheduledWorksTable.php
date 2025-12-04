<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Tables;

use App\Models\ProviderScheduledWork;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProviderScheduledWorksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Provider Name with Image
                TextColumn::make('provider.full_name')
                    ->label(__('resources.provider_scheduled_work.provider'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-user')
                    ->color('primary')
                    ->description(fn ($record) => $record->provider?->email),

                // Day of Week Badge
                TextColumn::make('day_of_week')
                    ->label(__('resources.provider_scheduled_work.day_of_week'))
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        0, 6 => 'warning',  // Weekend
                        5 => 'success',      // Friday
                        default => 'info',   // Weekdays
                    })
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        0 => __('resources.provider_scheduled_work.sunday'),
                        1 => __('resources.provider_scheduled_work.monday'),
                        2 => __('resources.provider_scheduled_work.tuesday'),
                        3 => __('resources.provider_scheduled_work.wednesday'),
                        4 => __('resources.provider_scheduled_work.thursday'),
                        5 => __('resources.provider_scheduled_work.friday'),
                        6 => __('resources.provider_scheduled_work.saturday'),
                        default => $state,
                    })
                    ->icon(fn (int $state): string => match ($state) {
                        0, 6 => 'heroicon-o-sun',
                        5 => 'heroicon-o-moon',
                        default => 'heroicon-o-calendar-days',
                    }),

                // Work Day Status
                IconColumn::make('is_work_day')
                    ->label(__('resources.provider_scheduled_work.is_work_day'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->alignCenter(),

                // Working Hours Range
                TextColumn::make('working_hours')
                    ->label(__('resources.provider_scheduled_work.working_hours'))
                    ->getStateUsing(function ($record) {
                        if (!$record->is_work_day) {
                            return __('resources.provider_scheduled_work.day_off');
                        }
                        return $record->start_time . ' - ' . $record->end_time;
                    })
                    ->icon('heroicon-o-clock')
                    ->color(fn ($record) => $record->is_work_day ? 'success' : 'gray')
                    ->weight(FontWeight::SemiBold)
                    ->description(function ($record) {
                        if (!$record->is_work_day || !$record->start_time || !$record->end_time) {
                            return null;
                        }

                        $start = Carbon::parse($record->start_time);
                        $end = Carbon::parse($record->end_time);
                        $totalMinutes = $start->diffInMinutes($end);
                        $totalHours = floor($totalMinutes / 60);
                        $remainingMinutes = $totalMinutes % 60;

                        if ($record->break_minutes > 0) {
                            $effectiveMinutes = $totalMinutes - $record->break_minutes;
                            $effectiveHours = floor($effectiveMinutes / 60);
                            $effectiveRemaining = $effectiveMinutes % 60;

                            return __('resources.provider_scheduled_work.total_hours') . ': ' .
                                   $totalHours . 'h ' . $remainingMinutes . 'm | ' .
                                   __('resources.provider_scheduled_work.effective_hours') . ': ' .
                                   $effectiveHours . 'h ' . $effectiveRemaining . 'm';
                        }

                        return __('resources.provider_scheduled_work.total_hours') . ': ' .
                               $totalHours . 'h ' . $remainingMinutes . 'm';
                    }),

                // Start Time
                TextColumn::make('start_time')
                    ->label(__('resources.provider_scheduled_work.start_time'))
                    ->time('H:i')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('info')
                    ->sortable()
                    ->toggleable()
                    ->visible(fn ($record) => $record?->is_work_day ?? true),

                // End Time
                TextColumn::make('end_time')
                    ->label(__('resources.provider_scheduled_work.end_time'))
                    ->time('H:i')
                    ->icon('heroicon-o-arrow-left-end-on-rectangle')
                    ->color('warning')
                    ->sortable()
                    ->toggleable()
                    ->visible(fn ($record) => $record?->is_work_day ?? true),

                // Break Duration
                TextColumn::make('break_minutes')
                    ->label(__('resources.provider_scheduled_work.break_duration'))
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state <= 30 => 'info',
                        $state <= 60 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(function (int $state): string {
                        if ($state === 0) {
                            return __('resources.provider_scheduled_work.no_break');
                        }
                        if ($state < 60) {
                            return $state . ' ' . __('resources.provider_scheduled_work.minutes');
                        }
                        $hours = floor($state / 60);
                        $minutes = $state % 60;
                        return $hours . 'h ' . ($minutes > 0 ? $minutes . 'm' : '');
                    })
                    ->icon('heroicon-o-pause')
                    ->toggleable(),

                // Active Status
                IconColumn::make('is_active')
                    ->label(__('resources.provider_scheduled_work.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),

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
                // فلتر حسب المقدم
                SelectFilter::make('user_id')
                    ->label(__('resources.provider_scheduled_work.filter_by_provider'))
                    ->relationship('provider', 'first_name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // فلتر حسب اليوم
                SelectFilter::make('day_of_week')
                    ->label(__('resources.provider_scheduled_work.filter_by_day'))
                    ->options([
                        0 => __('resources.provider_scheduled_work.sunday'),
                        1 => __('resources.provider_scheduled_work.monday'),
                        2 => __('resources.provider_scheduled_work.tuesday'),
                        3 => __('resources.provider_scheduled_work.wednesday'),
                        4 => __('resources.provider_scheduled_work.thursday'),
                        5 => __('resources.provider_scheduled_work.friday'),
                        6 => __('resources.provider_scheduled_work.saturday'),
                    ])
                    ->multiple(),

                // فلتر أيام العمل
                TernaryFilter::make('is_work_day')
                    ->label(__('resources.provider_scheduled_work.is_work_day'))
                    ->placeholder(__('resources.salon_setting.all_settings'))
                    ->trueLabel(__('resources.provider_scheduled_work.work_days_only'))
                    ->falseLabel(__('resources.provider_scheduled_work.days_off_only')),

                // فلتر الحالة النشطة
                TernaryFilter::make('is_active')
                    ->label(__('resources.provider_scheduled_work.status'))
                    ->placeholder(__('resources.salon_setting.all_settings'))
                    ->trueLabel(__('resources.provider_scheduled_work.active_only'))
                    ->falseLabel(__('resources.provider_scheduled_work.inactive_only')),
            ])
            ->defaultSort('day_of_week', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
