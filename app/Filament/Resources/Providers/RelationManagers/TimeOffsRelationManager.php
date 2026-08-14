<?php

namespace App\Filament\Resources\Providers\RelationManagers;

use App\Models\ProviderTimeOff;
use App\Traits\ChecksPermissions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TimeOffsRelationManager extends RelationManager
{
    use ChecksPermissions;

    protected static string $relationship = 'timeOffs';

    protected static ?string $recordTitleAttribute = 'id';

    /**
     * Leave management has no Resource class of its own — it only exists as this
     * relation manager — so the abilities are registered under an explicit
     * `ProviderTimeOff` prefix by {@see \Database\Seeders\PermissionsSeeder}.
     * Only the `admin` role (and SuperAdmin, which bypasses) is granted them.
     */
    protected static function permissionPrefix(): string
    {
        return 'ProviderTimeOff';
    }

    /**
     * Hide the whole "Leave management" tab from anyone without the view ability.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::allowed('view');
    }

    // Filament's own relation-manager authorization hooks. They are wired to the
    // same abilities so nothing can slip through a path that bypasses the
    // per-action `visible()` checks below.

    protected function canCreate(): bool
    {
        return static::allowed('create');
    }

    protected function canEdit(Model $record): bool
    {
        return static::allowed('edit');
    }

    protected function canView(Model $record): bool
    {
        return static::allowed('view');
    }

    protected function canDelete(Model $record): bool
    {
        return static::allowed('delete');
    }

    protected function canDeleteAny(): bool
    {
        return static::allowed('delete');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources.provider_resource.leave_management');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('resources.provider_resource.leave_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === ProviderTimeOff::TYPE_HOURLY
                        ? __('resources.provider_resource.hourly_leave')
                        : __('resources.provider_resource.daily_leave'))
                    ->color(fn ($state) => $state === ProviderTimeOff::TYPE_HOURLY ? 'info' : 'warning')
                    ->icon(fn ($state) => $state === ProviderTimeOff::TYPE_HOURLY
                        ? 'heroicon-o-clock'
                        : 'heroicon-o-calendar-days')
                    ->weight(FontWeight::Bold),

                TextColumn::make('start_date')
                    ->label(__('resources.provider_resource.start_date'))
                    ->date('Y-m-d')
                    ->icon('heroicon-o-calendar')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label(__('resources.provider_resource.end_date'))
                    ->date('Y-m-d')
                    ->icon('heroicon-o-calendar')
                    ->sortable()
                    ->visible(fn ($record) => $record && $record->type === ProviderTimeOff::TYPE_FULL_DAY),

                TextColumn::make('start_time')
                    ->label(__('resources.provider_resource.start_time'))
                    ->time('H:i')
                    ->icon('heroicon-o-clock')
                    ->visible(fn ($record) => $record && $record->type === ProviderTimeOff::TYPE_HOURLY),

                TextColumn::make('end_time')
                    ->label(__('resources.provider_resource.end_time'))
                    ->time('H:i')
                    ->icon('heroicon-o-clock')
                    ->visible(fn ($record) => $record && $record->type === ProviderTimeOff::TYPE_HOURLY),

                TextColumn::make('duration_hours')
                    ->label(__('resources.provider_resource.duration_hours'))
                    ->suffix(' ' . __('resources.provider_resource.hours'))
                    ->badge()
                    ->color('info')
                    ->visible(fn ($record) => $record && $record->type === ProviderTimeOff::TYPE_HOURLY),

                TextColumn::make('duration_days')
                    ->label(__('resources.provider_resource.duration_days'))
                    ->suffix(' ' . __('resources.provider_resource.days'))
                    ->badge()
                    ->color('warning')
                    ->visible(fn ($record) => $record && $record->type === ProviderTimeOff::TYPE_FULL_DAY),

                TextColumn::make('reason.name')
                    ->label(__('resources.provider_resource.reason'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

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
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => static::allowed('create'))
                    ->modalHeading(__('resources.provider_resource.add_leave'))
                    ->modalWidth('xl')
                    ->form(self::getLeaveForm())
                    // Relation managers never run the page-level mutateFormData*
                    // hooks, so the derived columns are computed here instead.
                    ->mutateDataUsing(fn (array $data): array => self::mutateLeaveData($data)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (): bool => static::allowed('view')),
                EditAction::make()
                    ->visible(fn (): bool => static::allowed('edit'))
                    ->modalHeading(__('resources.provider_resource.edit_leave'))
                    ->modalWidth('xl')
                    ->form(self::getLeaveForm())
                    ->mutateDataUsing(fn (array $data): array => self::mutateLeaveData($data)),
                DeleteAction::make()
                    ->visible(fn (): bool => static::allowed('delete')),
            ])
            ->emptyStateHeading(__('resources.provider_resource.no_leaves_yet'))
            ->emptyStateDescription(__('resources.provider_resource.add_first_leave'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    protected static function getLeaveForm(): array
    {
        return [
            Radio::make('type')
                ->label(__('resources.provider_resource.leave_type'))
                ->options([
                    ProviderTimeOff::TYPE_HOURLY => __('resources.provider_resource.hourly_leave'),
                    ProviderTimeOff::TYPE_FULL_DAY => __('resources.provider_resource.daily_leave'),
                ])
                ->required()
                ->default(ProviderTimeOff::TYPE_FULL_DAY)
                ->live()
                ->columnSpanFull(),

            // Hourly Leave Fields
            Grid::make(2)
                ->schema([
                    DatePicker::make('start_date')
                        ->label(__('resources.provider_resource.leave_date'))
                        ->required(fn (callable $get) => self::isHourly($get('type')))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->default(now())
                        ->minDate(now()),

                    Select::make('reason_id')
                        ->label(__('resources.provider_resource.reason'))
                        ->relationship('reason', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),
                ])
                ->visible(fn (callable $get) => self::isHourly($get('type'))),

            Grid::make(2)
                ->schema([
                    TimePicker::make('start_time')
                        ->label(__('resources.provider_resource.start_time'))
                        ->required(fn (callable $get) => self::isHourly($get('type')))
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('H:i'),

                    TimePicker::make('end_time')
                        ->label(__('resources.provider_resource.end_time'))
                        ->required(fn (callable $get) => self::isHourly($get('type')))
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('H:i')
                        ->after('start_time'),
                ])
                ->visible(fn (callable $get) => self::isHourly($get('type'))),

            // Daily Leave Fields
            Grid::make(2)
                ->schema([
                    DatePicker::make('start_date')
                        ->label(__('resources.provider_resource.start_date'))
                        ->required(fn (callable $get) => ! self::isHourly($get('type')))
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
                        ->required(fn (callable $get) => ! self::isHourly($get('type')))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->default(now())
                        ->minDate(fn (callable $get) => $get('start_date') ?? now()),
                ])
                ->visible(fn (callable $get) => ! self::isHourly($get('type'))),

            Select::make('reason_id')
                ->label(__('resources.provider_resource.reason'))
                ->relationship('reason', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->columnSpanFull()
                ->visible(fn (callable $get) => ! self::isHourly($get('type'))),
        ];
    }

    /**
     * The radio state arrives from the browser as a string ("0" / "1") while the
     * model casts `type` to an int, so every comparison has to be normalised —
     * a strict comparison silently hid the whole form after the first click.
     */
    protected static function isHourly($type): bool
    {
        return (int) $type === ProviderTimeOff::TYPE_HOURLY;
    }

    /**
     * Fill the derived columns before the record is written.
     *
     * Wired onto the Create/Edit actions because Filament only runs the
     * `mutateFormDataBefore*` page hooks on resource pages, never on a relation
     * manager — leaving them here meant `duration_hours`, `duration_days` and the
     * hourly `end_date` were never persisted.
     */
    protected static function mutateLeaveData(array $data): array
    {
        if (self::isHourly($data['type'] ?? null)) {
            $data['type'] = ProviderTimeOff::TYPE_HOURLY;

            $startTime = \Carbon\Carbon::parse($data['start_time']);
            $endTime = \Carbon\Carbon::parse($data['end_time']);

            if ($endTime->lessThanOrEqualTo($startTime)) {
                $endTime->addDay();
            }

            $data['duration_hours'] = $startTime->diffInHours($endTime, false);
            // An hourly leave always lives on a single day.
            $data['end_date'] = $data['start_date'];
            $data['duration_days'] = null;
        } else {
            $data['type'] = ProviderTimeOff::TYPE_FULL_DAY;

            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date'] ?? $data['start_date']);

            $data['end_date'] = $endDate->format('Y-m-d');
            $data['duration_days'] = $startDate->diffInDays($endDate) + 1;
            // Clear the hourly-only columns when a leave is switched to full day.
            $data['start_time'] = null;
            $data['end_time'] = null;
            $data['duration_hours'] = null;
        }

        return $data;
    }
}
