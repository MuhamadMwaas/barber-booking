<?php

namespace App\Filament\Resources\Providers\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attendance history shown directly on a provider's profile page so an admin
 * can review a single provider's work-time log in context.
 */
class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $recordTitleAttribute = 'work_date';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('resources.provider_attendance.plural_label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_date')
                    ->label(__('resources.provider_attendance.work_date'))
                    ->date('Y-m-d (D)')
                    ->icon('heroicon-o-calendar')
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('check_in_at')
                    ->label(__('resources.provider_attendance.check_in_at'))
                    ->dateTime('H:i')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('check_out_at')
                    ->label(__('resources.provider_attendance.check_out_at'))
                    ->dateTime('H:i')
                    ->color('danger')
                    ->placeholder(__('resources.provider_attendance.still_open')),

                TextColumn::make('duration_minutes')
                    ->label(__('resources.provider_attendance.duration'))
                    ->badge()
                    ->color('info')
                    ->state(fn ($record) => $record->duration_minutes === null
                        ? '—'
                        : floor($record->duration_minutes / 60) . 'h ' . ($record->duration_minutes % 60) . 'm'),
            ])
            ->filters([
                TernaryFilter::make('open_sessions')
                    ->label(__('resources.provider_attendance.open_sessions'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('resources.provider_attendance.open_only'))
                    ->falseLabel(__('resources.provider_attendance.closed_only'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('check_out_at'),
                        false: fn (Builder $q) => $q->whereNotNull('check_out_at'),
                    ),

                Filter::make('this_month')
                    ->label(__('resources.provider_attendance.this_month'))
                    ->query(fn (Builder $q) => $q->whereBetween('work_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        DatePicker::make('work_date')
                            ->label(__('resources.provider_attendance.work_date'))
                            ->native(false)
                            ->required(),
                        DateTimePicker::make('check_in_at')
                            ->label(__('resources.provider_attendance.check_in_at'))
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('check_out_at')
                            ->label(__('resources.provider_attendance.check_out_at'))
                            ->seconds(false)
                            ->after('check_in_at')
                            ->nullable(),
                    ]),
                DeleteAction::make(),
            ])
            ->defaultSort('work_date', 'desc')
            ->emptyStateHeading(__('resources.provider_attendance.no_records'))
            ->emptyStateIcon('heroicon-o-finger-print');
    }
}
