<?php

namespace App\Filament\Resources\ProviderAttendances\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProviderAttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider.full_name')
                    ->label(__('resources.provider_attendance.provider'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-user')
                    ->color('primary'),

                TextColumn::make('work_date')
                    ->label(__('resources.provider_attendance.work_date'))
                    ->date('Y-m-d (D)')
                    ->icon('heroicon-o-calendar')
                    ->sortable(),

                TextColumn::make('check_in_at')
                    ->label(__('resources.provider_attendance.check_in_at'))
                    ->dateTime('H:i')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('check_out_at')
                    ->label(__('resources.provider_attendance.check_out_at'))
                    ->dateTime('H:i')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('danger')
                    ->placeholder(__('resources.provider_attendance.still_open'))
                    ->sortable(),

                TextColumn::make('duration_minutes')
                    ->label(__('resources.provider_attendance.duration'))
                    ->badge()
                    ->color('info')
                    ->state(fn ($record) => $record->duration_minutes === null
                        ? '—'
                        : floor($record->duration_minutes / 60) . 'h ' . ($record->duration_minutes % 60) . 'm'),

                TextColumn::make('branch.name')
                    ->label(__('resources.provider_attendance.branch'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('source')
                    ->label(__('resources.provider_attendance.source'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('resources.provider_attendance.provider'))
                    ->options(fn () => User::query()
                        ->whereHas('roles', fn ($q) => $q->where('name', 'provider'))
                        ->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name]))
                    ->searchable(),

                Filter::make('work_date')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('resources.provider_attendance.from'))
                            ->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('resources.provider_attendance.until'))
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('work_date', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('work_date', '<=', $d));
                    }),

                TernaryFilter::make('open_sessions')
                    ->label(__('resources.provider_attendance.open_sessions'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('resources.provider_attendance.open_only'))
                    ->falseLabel(__('resources.provider_attendance.closed_only'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('check_out_at'),
                        false: fn (Builder $q) => $q->whereNotNull('check_out_at'),
                    ),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('work_date', 'desc');
    }
}
