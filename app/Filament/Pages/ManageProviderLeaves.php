<?php

namespace App\Filament\Pages;

use App\Models\ProviderTimeOff;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ManageProviderLeaves extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;


    protected  string $view = 'filament.pages.manage-provider-leaves';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('resources.provider_resource.all_provider_leaves');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources.provider_resource.provider_management');
    }

    public function getTitle(): string
    {
        return __('resources.provider_resource.all_provider_leaves');
    }

    public function getHeading(): string
    {
        return __('resources.provider_resource.all_provider_leaves');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProviderTimeOff::query()->with(['provider', 'reason']))
            ->columns([
                TextColumn::make('provider.full_name')
                    ->label(__('resources.provider_resource.provider_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-user'),

                TextColumn::make('type')
                    ->label(__('resources.provider_resource.leave_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === ProviderTimeOff::TYPE_HOURLY
                        ? __('resources.provider_resource.hourly_leave')
                        : __('resources.provider_resource.daily_leave'))
                    ->color(fn ($state) => $state === ProviderTimeOff::TYPE_HOURLY ? 'info' : 'warning')
                    ->icon(fn ($state) => $state === ProviderTimeOff::TYPE_HOURLY
                        ? 'heroicon-o-clock'
                        : 'heroicon-o-calendar-days'),

                TextColumn::make('start_date')
                    ->label(__('resources.provider_resource.start_date'))
                    ->date('Y-m-d')
                    ->icon('heroicon-o-calendar')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label(__('resources.provider_resource.end_date'))
                    ->date('Y-m-d')
                    ->icon('heroicon-o-calendar')
                    ->sortable(),

                TextColumn::make('duration')
                    ->label(__('resources.provider_resource.duration'))
                    ->getStateUsing(function ($record) {
                        if ($record->type === ProviderTimeOff::TYPE_HOURLY) {
                            return $record->duration_hours . ' ' . __('resources.provider_resource.hours');
                        }
                        return $record->duration_days . ' ' . __('resources.provider_resource.days');
                    })
                    ->badge()
                    ->color(fn ($record) => $record->type === ProviderTimeOff::TYPE_HOURLY ? 'info' : 'warning'),

                TextColumn::make('reason.name')
                    ->label(__('resources.provider_resource.reason'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('provider.branch.name')
                    ->label(__('resources.provider_resource.branch'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('resources.provider_resource.leave_status'))
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $now = now()->toDateString();
                        if ($record->start_date > $now) {
                            return 'upcoming';
                        } elseif ($record->end_date < $now) {
                            return 'past';
                        } else {
                            return 'active';
                        }
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        'upcoming' => __('resources.provider_resource.upcoming'),
                        'active' => __('resources.provider_resource.active'),
                        'past' => __('resources.provider_resource.past'),
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        'upcoming' => 'info',
                        'active' => 'success',
                        'past' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match($state) {
                        'upcoming' => 'heroicon-o-clock',
                        'active' => 'heroicon-o-check-circle',
                        'past' => 'heroicon-o-archive-box',
                        default => 'heroicon-o-question-mark-circle',
                    }),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('resources.provider_resource.provider_name'))
                    ->relationship('provider', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->label(__('resources.provider_resource.leave_type'))
                    ->options([
                        ProviderTimeOff::TYPE_HOURLY => __('resources.provider_resource.hourly_leave'),
                        ProviderTimeOff::TYPE_FULL_DAY => __('resources.provider_resource.daily_leave'),
                    ]),

                SelectFilter::make('reason_id')
                    ->label(__('resources.provider_resource.reason'))
                    ->relationship('reason', 'name')
                    ->preload(),

                Filter::make('upcoming')
                    ->label(__('resources.provider_resource.upcoming'))
                    ->query(fn (Builder $query) => $query->where('start_date', '>=', now()->toDateString())),

                Filter::make('past')
                    ->label(__('resources.provider_resource.past'))
                    ->query(fn (Builder $query) => $query->where('end_date', '<', now()->toDateString())),

                Filter::make('active')
                    ->label(__('resources.provider_resource.active'))
                    ->query(fn (Builder $query) => $query
                        ->where('start_date', '<=', now()->toDateString())
                        ->where('end_date', '>=', now()->toDateString())),

                Filter::make('this_month')
                    ->label(__('resources.provider_resource.this_month'))
                    ->query(fn (Builder $query) => $query
                        ->whereYear('start_date', now()->year)
                        ->whereMonth('start_date', now()->month)),

                Filter::make('this_year')
                    ->label(__('resources.provider_resource.this_year'))
                    ->query(fn (Builder $query) => $query->whereYear('start_date', now()->year)),
            ])
            ->emptyStateHeading(__('resources.provider_resource.no_leaves_yet'))
            ->emptyStateDescription(__('resources.provider_resource.no_leaves_description'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
