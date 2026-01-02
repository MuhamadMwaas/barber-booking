<?php

namespace App\Filament\Resources\Providers\RelationManagers;

use App\Models\ProviderScheduledWork;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ScheduledWorksRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduledWorks';

    protected static ?string $recordTitleAttribute = 'day_of_week';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('resources.provider_resource.work_schedule');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_name')
                    ->label(__('resources.provider_resource.day'))
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(fn ($record) => $record->localized_day_name)
                    ->sortable('day_of_week'),

                TextColumn::make('start_time')
                    ->label(__('resources.provider_resource.start_time'))
                    ->time('H:i')
                    ->icon('heroicon-o-clock')
                    ->sortable(),

                TextColumn::make('end_time')
                    ->label(__('resources.provider_resource.end_time'))
                    ->time('H:i')
                    ->icon('heroicon-o-clock')
                    ->sortable(),

                TextColumn::make('break_minutes')
                    ->label(__('resources.provider_resource.break_time'))
                    ->suffix(' ' . __('resources.provider_resource.minutes'))
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('working_minutes')
                    ->label(__('resources.provider_resource.working_hours'))
                    ->getStateUsing(fn ($record) => $record->formatted_duration)
                    ->badge()
                    ->color('success'),

                IconColumn::make('is_work_day')
                    ->label(__('resources.provider_resource.work_day'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('is_active')
                    ->label(__('resources.provider_resource.active'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
            ])
            ->defaultSort('day_of_week', 'asc')
            ->filters([
                SelectFilter::make('day_of_week')
                    ->label(__('resources.provider_resource.day'))
                    ->options(ProviderScheduledWork::getLocalizedDays()),

                SelectFilter::make('is_work_day')
                    ->label(__('resources.provider_resource.work_day'))
                    ->options([
                        1 => __('resources.provider_resource.work_day'),
                        0 => __('resources.provider_resource.day_off'),
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalHeading(__('resources.provider_resource.add_schedule'))
                    ->modalWidth('xl')
                    ->form(self::getScheduleForm())
                    ->mutateFormDataUsing(function (array $data) {
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(__('resources.provider_resource.edit_schedule'))
                    ->modalWidth('xl')
                    ->form(self::getScheduleForm()),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('resources.provider_resource.no_schedule_yet'))
            ->emptyStateDescription(__('resources.provider_resource.add_first_schedule'))
            ->emptyStateIcon('heroicon-o-calendar');
    }

    protected static function getScheduleForm(): array
    {
        return [
            Select::make('day_of_week')
                ->label(__('resources.provider_resource.day'))
                ->options(ProviderScheduledWork::getLocalizedDays())
                ->required()
                ->native(false)
                ->columnSpanFull(),

            Toggle::make('is_work_day')
                ->label(__('resources.provider_resource.work_day'))
                ->default(true)
                ->live()
                ->columnSpanFull(),

            Grid::make(2)
                ->schema([
                    TimePicker::make('start_time')
                        ->label(__('resources.provider_resource.start_time'))
                        ->required(fn (callable $get) => $get('is_work_day'))
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('H:i')
                        ->default('09:00:00')
                        ->disabled(fn (callable $get) => !$get('is_work_day')),

                    TimePicker::make('end_time')
                        ->label(__('resources.provider_resource.end_time'))
                        ->required(fn (callable $get) => $get('is_work_day'))
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('H:i')
                        ->default('17:00:00')
                        ->disabled(fn (callable $get) => !$get('is_work_day')),
                ])
                ->visible(fn (callable $get) => $get('is_work_day')),

            Grid::make(2)
                ->schema([
                    TextInput::make('break_minutes')
                        ->label(__('resources.provider_resource.break_minutes'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(480)
                        ->default(0)
                        ->suffix(__('resources.provider_resource.minutes'))
                        ->disabled(fn (callable $get) => !$get('is_work_day')),

                    Toggle::make('is_active')
                        ->label(__('resources.provider_resource.active'))
                        ->default(true)
                        ->inline(false)
                        ->disabled(fn (callable $get) => !$get('is_work_day')),
                ])
                ->visible(fn (callable $get) => $get('is_work_day')),
        ];
    }
}
