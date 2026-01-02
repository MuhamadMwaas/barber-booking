<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Providers\Widgets\ProviderLeaveStatsWidget;
use App\Filament\Resources\Providers\Widgets\ProviderStatsOverviewWidget;
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
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('add_hourly_leave')
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

                            TimePicker::make('end_time')
                                ->label(__('resources.provider_resource.end_time'))
                                ->required()
                                ->native(false)
                                ->seconds(false)
                                ->displayFormat('H:i'),

                            Select::make('reason_id')
                                ->label(__('resources.provider_resource.reason'))
                                ->relationship('timeOffs.reason', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(2),
                        ]),
                ])
                ->action(function (array $data) {
                    $startTime = \Carbon\Carbon::parse($data['start_time']);
                    $endTime = \Carbon\Carbon::parse($data['end_time']);

                    if ($endTime->lessThanOrEqualTo($startTime)) {
                        $endTime->addDay();
                    }

                    $durationHours = $startTime->diffInHours($endTime, false);

                    ProviderTimeOff::create([
                        'user_id' => $this->record->id,
                        'type' => ProviderTimeOff::TYPE_HOURLY,
                        'start_date' => $data['start_date'],
                        'end_date' => $data['start_date'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'duration_hours' => $durationHours,
                        'reason_id' => $data['reason_id'],
                    ]);

                    Notification::make()
                        ->title(__('resources.provider_resource.leave_added_successfully'))
                        ->success()
                        ->send();
                }),

            Action::make('add_daily_leave')
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
                                ->required()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(2),
                        ]),
                ])
                ->action(function (array $data) {
                    $startDate = \Carbon\Carbon::parse($data['start_date']);
                    $endDate = \Carbon\Carbon::parse($data['end_date']);
                    $durationDays = $startDate->diffInDays($endDate) + 1;

                    ProviderTimeOff::create([
                        'user_id' => $this->record->id,
                        'type' => ProviderTimeOff::TYPE_FULL_DAY,
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'duration_days' => $durationDays,
                        'reason_id' => $data['reason_id'],
                    ]);

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
