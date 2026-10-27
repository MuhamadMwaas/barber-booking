<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Concerns\GrantsLeaveWithReassignment;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Providers\Widgets\ProviderLeaveStatsWidget;
use App\Filament\Resources\Providers\Widgets\ProviderStatsOverviewWidget;
use App\Models\ReasonLeave;
use App\Models\User;
use App\Models\ProviderTimeOff;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Notifications\Notification;

class ViewProvider extends ViewRecord
{
    use GrantsLeaveWithReassignment;

    protected static string $resource = ProviderResource::class;

    protected function leaveOwner(): User
    {
        return $this->record;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            // Leave management is admin-only (ProviderTimeOff:create); viewing
            // a provider is not enough to give them a leave.
            Action::make('add_hourly_leave')
                ->authorize(fn (): bool => $this->mayWriteLeave('create'))
                ->label(__('resources.provider_resource.add_hourly_leave'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->modalHeading(__('resources.provider_resource.add_hourly_leave'))
                ->modalDescription(__('resources.provider_resource.add_hourly_leave_description'))
                ->modalWidth('xl')
                ->form([
                    Grid::make(2)
                        ->schema([
                            DatePicker::make('start_date')
                                ->label(__('resources.provider_resource.leave_date'))
                                ->required()
                                ->native(false)
                                ->displayFormat('Y-m-d')
                                ->default(now())
                                ->minDate(now())
                                ->columnSpan(2),

                            TimePicker::make('start_time')
                                ->label(__('resources.provider_resource.start_time'))
                                ->required()
                                ->native(false)
                                ->seconds(false)
                                ->displayFormat('H:i'),

                            // Same rule as the dashboard and the leaves tab: a
                            // backwards pair is a typo, not a leave across midnight.
                            TimePicker::make('end_time')
                                ->label(__('resources.provider_resource.end_time'))
                                ->required()
                                ->native(false)
                                ->seconds(false)
                                ->displayFormat('H:i')
                                ->after('start_time'),

                            Select::make('reason_id')
                                ->label(__('resources.provider_resource.reason'))
                                ->relationship('timeOffs.reason', 'name')
                                ->getOptionLabelFromRecordUsing(fn (ReasonLeave $record): string => $record->getNameIn(app()->getLocale()))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(2),
                        ]),
                ])
                ->action(function (array $data, Action $action) {
                    $startTime = \Carbon\Carbon::parse($data['start_time']);
                    $endTime = \Carbon\Carbon::parse($data['end_time']);

                    $leave = $this->grantLeaveOrResolve([
                        'type' => ProviderTimeOff::TYPE_HOURLY,
                        'start_date' => $data['start_date'],
                        'end_date' => $data['start_date'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'duration_hours' => $startTime->diffInHours($endTime, false),
                        'reason_id' => $data['reason_id'],
                    ], null, $action);

                    if ($leave === null) {
                        return;
                    }

                    Notification::make()
                        ->title(__('resources.provider_resource.leave_added_successfully'))
                        ->success()
                        ->send();
                }),

            Action::make('add_daily_leave')
                ->authorize(fn (): bool => $this->mayWriteLeave('create'))
                ->label(__('resources.provider_resource.add_daily_leave'))
                ->icon('heroicon-o-calendar-days')
                ->color('danger')
                ->modalHeading(__('resources.provider_resource.add_daily_leave'))
                ->modalDescription(__('resources.provider_resource.add_daily_leave_description'))
                ->modalWidth('xl')
                ->form([
                    Grid::make(2)
                        ->schema([
                            DatePicker::make('start_date')
                                ->label(__('resources.provider_resource.start_date'))
                                ->required()
                                ->native(false)
                                ->displayFormat('Y-m-d')
                                ->default(now())
                                ->minDate(now())
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $endDate = $get('end_date');
                                    if ($endDate && $state && $endDate < $state) {
                                        $set('end_date', $state);
                                    }
                                }),

                            DatePicker::make('end_date')
                                ->label(__('resources.provider_resource.end_date'))
                                ->required()
                                ->native(false)
                                ->displayFormat('Y-m-d')
                                ->default(now())
                                ->minDate(fn (callable $get) => $get('start_date') ?? now()),

                            Select::make('reason_id')
                                ->label(__('resources.provider_resource.reason'))
                                ->relationship('timeOffs.reason', 'name')
                                ->getOptionLabelFromRecordUsing(fn (ReasonLeave $record): string => $record->getNameIn(app()->getLocale()))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(2),
                        ]),
                ])
                ->action(function (array $data, Action $action) {
                    $startDate = \Carbon\Carbon::parse($data['start_date']);
                    $endDate = \Carbon\Carbon::parse($data['end_date']);

                    $leave = $this->grantLeaveOrResolve([
                        'type' => ProviderTimeOff::TYPE_FULL_DAY,
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'duration_days' => (int) $startDate->diffInDays($endDate) + 1,
                        'reason_id' => $data['reason_id'],
                    ], null, $action);

                    if ($leave === null) {
                        return;
                    }

                    Notification::make()
                        ->title(__('resources.provider_resource.leave_added_successfully'))
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProviderStatsOverviewWidget::class,
            ProviderLeaveStatsWidget::class,
        ];
    }
}
