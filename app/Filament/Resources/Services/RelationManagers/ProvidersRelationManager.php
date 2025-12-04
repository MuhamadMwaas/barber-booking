<?php

namespace App\Filament\Resources\Services\RelationManagers;

use App\Enum\AppointmentStatus;
use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProvidersRelationManager extends RelationManager
{
    protected static string $relationship = 'providers';

    protected static ?string $relatedResource = ServiceResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // صورة مقدم الخدمة
                ImageColumn::make('profile_image_url')
                    ->label(__('resources.avatar'))
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->full_name) . '&color=7F9CF5&background=EBF4FF')
                    ->size(50),

                // الاسم الكامل
                TextColumn::make('full_name')
                    ->label(__('resources.full_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record) => $record->email),

                // السعر المخصص
                TextColumn::make('pivot.custom_price')
                    ->label(__('resources.service.custom_price'))
                    ->money('SAR')
                    ->badge()
                    ->color('success')
                    ->default(fn ($record) => $this->getOwnerRecord()->price)
                    ->description(__('resources.service.default_if_empty')),

                // المدة المخصصة
                TextColumn::make('pivot.custom_duration')
                    ->label(__('resources.service.custom_duration'))
                    ->formatStateUsing(function ($state, $record) {
                        $duration = $state ?? $this->getOwnerRecord()->duration_minutes;
                        return floor($duration / 60) > 0
                            ? floor($duration / 60) . 'h ' . ($duration % 60) . 'm'
                            : $duration . 'm';
                    })
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-clock')
                    ->description(__('resources.service.default_if_empty')),

                // الحالة النشطة
                IconColumn::make('pivot.is_active')
                    ->label(__('resources.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                // عدد الحجوزات المكتملة من هذه الخدمة
                TextColumn::make('completed_bookings_count')
                    ->label(__('resources.service.completed_bookings'))
                    ->state(function ($record) {
                        return $record->appointmentsAsProvider()
                            ->where('status', AppointmentStatus::COMPLETED)
                            ->whereHas('services', fn ($q) => $q->where('service_id', $this->getOwnerRecord()->id))
                            ->count();
                    })
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount([
                            'appointmentsAsProvider as completed_bookings_count' => function ($q) {
                                $q->where('status', AppointmentStatus::COMPLETED)
                                    ->whereHas('services', fn ($query) => $query->where('service_id', $this->getOwnerRecord()->id));
                            }
                        ])->orderBy('completed_bookings_count', $direction);
                    }),

                // عدد الحجوزات المعلقة من هذه الخدمة
                TextColumn::make('pending_bookings_count')
                    ->label(__('resources.user.pending_bookings'))
                    ->state(function ($record) {
                        return $record->appointmentsAsProvider()
                            ->whereNotIn('status', [
                                AppointmentStatus::COMPLETED,
                                AppointmentStatus::USER_CANCELLED
                            ])
                            ->whereHas('services', fn ($q) => $q->where('service_id', $this->getOwnerRecord()->id))
                            ->count();
                    })
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-clock')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount([
                            'appointmentsAsProvider as pending_bookings_count' => function ($q) {
                                $q->whereNotIn('status', [
                                    AppointmentStatus::COMPLETED,
                                    AppointmentStatus::USER_CANCELLED
                                ])
                                ->whereHas('services', fn ($query) => $query->where('service_id', $this->getOwnerRecord()->id));
                            }
                        ])->orderBy('pending_bookings_count', $direction);
                    }),

                // إجمالي الحجوزات من هذه الخدمة
                TextColumn::make('total_bookings_count')
                    ->label(__('resources.service.total_bookings'))
                    ->state(function ($record) {
                        return $record->appointmentsAsProvider()
                            ->whereHas('services', fn ($q) => $q->where('service_id', $this->getOwnerRecord()->id))
                            ->count();
                    })
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-shopping-bag')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount([
                            'appointmentsAsProvider as total_bookings_count' => function ($q) {
                                $q->whereHas('services', fn ($query) => $query->where('service_id', $this->getOwnerRecord()->id));
                            }
                        ])->orderBy('total_bookings_count', $direction);
                    }),

                // إجمالي الأرباح من هذه الخدمة
                TextColumn::make('total_revenue')
                    ->label(__('resources.service.total_revenue'))
                    ->state(function ($record) {
                        $serviceId = $this->getOwnerRecord()->id;
                        $totalRevenue = $record->appointmentsAsProvider()
                            ->where('status', AppointmentStatus::COMPLETED)
                            ->with(['services' => fn ($q) => $q->where('service_id', $serviceId)])
                            ->get()
                            ->flatMap->services
                            ->where('id', $serviceId)
                            ->sum('pivot.price');

                        return 'SAR ' . number_format($totalRevenue, 2);
                    })
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-currency-dollar'),

                // رقم الهاتف
                TextColumn::make('phone')
                    ->label(__('resources.phone'))
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage(__('resources.phone_copied'))
                    ->placeholder('—')
                    ->toggleable(),

                // الفرع
                TextColumn::make('branch.name')
                    ->label(__('resources.branch'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('resources.no_branch'))
                    ->toggleable(),

                // ملاحظات
                TextColumn::make('pivot.notes')
                    ->label(__('resources.notes'))
                    ->limit(50)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('—')
                    ->toggleable(),

                // تاريخ الربط
                TextColumn::make('pivot.created_at')
                    ->label(__('resources.service.assigned_at'))
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('completed_bookings_count', 'desc')
            ->filters([
                // فلتر حسب الحالة النشطة
                TernaryFilter::make('is_active')
                    ->label(__('resources.status'))
                    ->placeholder(__('resources.all'))
                    ->trueLabel(__('resources.active_only'))
                    ->falseLabel(__('resources.inactive_only'))
                    ->queries(
                        true: fn (Builder $query) => $query->wherePivot('is_active', true),
                        false: fn (Builder $query) => $query->wherePivot('is_active', false),
                    ),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('resources.service.assign_provider'))
                    ->form([
                        Checkbox::make('is_active')
                            ->label(__('resources.service.active_status'))
                            ->default(true)
                            ->helperText(__('resources.service.provider_active_desc')),

                        TextInput::make('custom_price')
                            ->label(__('resources.service.custom_price'))
                            ->numeric()
                            ->prefix('SAR')
                            ->helperText(__('resources.service.custom_price_desc')),

                        TextInput::make('custom_duration')
                            ->label(__('resources.service.custom_duration'))
                            ->numeric()
                            ->suffix(__('resources.minutes'))
                            ->helperText(__('resources.service.custom_duration_desc')),

                        Textarea::make('notes')
                            ->label(__('resources.notes'))
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        Checkbox::make('is_active')
                            ->label(__('resources.service.active_status'))
                            ->helperText(__('resources.service.provider_active_desc')),

                        TextInput::make('custom_price')
                            ->label(__('resources.service.custom_price'))
                            ->numeric()
                            ->prefix('SAR')
                            ->helperText(__('resources.service.custom_price_desc')),

                        TextInput::make('custom_duration')
                            ->label(__('resources.service.custom_duration'))
                            ->numeric()
                            ->suffix(__('resources.minutes'))
                            ->helperText(__('resources.service.custom_duration_desc')),

                        Textarea::make('notes')
                            ->label(__('resources.notes'))
                            ->rows(3)
                            ->maxLength(500),
                    ]),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ])

            ->emptyStateHeading(__('resources.service.no_providers_assigned'))
            ->emptyStateDescription(__('resources.service.assign_providers_desc'))
            ->emptyStateIcon('heroicon-o-users');
    }
}
