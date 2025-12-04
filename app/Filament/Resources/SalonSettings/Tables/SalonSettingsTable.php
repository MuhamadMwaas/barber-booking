<?php

namespace App\Filament\Resources\SalonSettings\Tables;

use App\Models\SalonSetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalonSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Setting Key with Icon
                TextColumn::make('key')
                    ->label(__('resources.salon_setting.key'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-key')
                    ->color('primary')
                    ->copyable()
                    ->copyMessage(__('resources.salon_setting.value_copied')),

                // Setting Group Badge
                TextColumn::make('setting_group')
                    ->label(__('resources.salon_setting.setting_group'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'general' => 'gray',
                        'booking' => 'info',
                        'payment' => 'success',
                        'notifications' => 'warning',
                        'loyalty' => 'danger',
                        'contact' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'general' => __('resources.salon_setting.group_general'),
                        'booking' => __('resources.salon_setting.group_booking'),
                        'payment' => __('resources.salon_setting.group_payment'),
                        'notifications' => __('resources.salon_setting.group_notifications'),
                        'loyalty' => __('resources.salon_setting.group_loyalty'),
                        'contact' => __('resources.salon_setting.group_contact'),
                        default => $state,
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'general' => 'heroicon-o-cog-6-tooth',
                        'booking' => 'heroicon-o-calendar-days',
                        'payment' => 'heroicon-o-banknotes',
                        'notifications' => 'heroicon-o-bell',
                        'loyalty' => 'heroicon-o-gift',
                        'contact' => 'heroicon-o-envelope',
                        default => 'heroicon-o-folder',
                    }),

                // Type Badge
                TextColumn::make('type')
                    ->label(__('resources.salon_setting.type'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SalonSetting::TYPE_STRING => 'info',
                        SalonSetting::TYPE_INTEGER => 'success',
                        SalonSetting::TYPE_BOOLEAN => 'warning',
                        SalonSetting::TYPE_JSON => 'danger',
                        SalonSetting::TYPE_DECIMAL => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SalonSetting::TYPE_STRING => __('resources.salon_setting.type_string'),
                        SalonSetting::TYPE_INTEGER => __('resources.salon_setting.type_integer'),
                        SalonSetting::TYPE_BOOLEAN => __('resources.salon_setting.type_boolean'),
                        SalonSetting::TYPE_JSON => __('resources.salon_setting.type_json'),
                        SalonSetting::TYPE_DECIMAL => __('resources.salon_setting.type_decimal'),
                        default => $state,
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        SalonSetting::TYPE_STRING => 'heroicon-o-pencil-square',
                        SalonSetting::TYPE_INTEGER => 'heroicon-o-hashtag',
                        SalonSetting::TYPE_BOOLEAN => 'heroicon-o-check-circle',
                        SalonSetting::TYPE_JSON => 'heroicon-o-code-bracket',
                        SalonSetting::TYPE_DECIMAL => 'heroicon-o-calculator',
                        default => 'heroicon-o-variable',
                    }),

                // Current Value with Special Formatting
                TextColumn::make('value')
                    ->label(__('resources.salon_setting.value'))
                    ->searchable()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn ($state, $record) => match ($record->type) {
                        SalonSetting::TYPE_BOOLEAN => $record->value == 'true' || $state === '1' ? '✓ ' . __('resources.user.active') : '✗ ' . __('resources.user.inactive'),
                        SalonSetting::TYPE_JSON => \is_array($state) ? \count($state) . ' ' . __('resources.service.name') : json_encode($state),
                        SalonSetting::TYPE_DECIMAL => "{$state}%",
                        default => $state,
                    })
                    ->color(fn ($record) => match ($record->type) {
                        SalonSetting::TYPE_BOOLEAN => $record->value === 'true' || $record->value === '1' ? 'success' : 'danger',
                        default => 'gray',
                    })
                    ->icon(fn ($record) => match ($record->type) {
                        SalonSetting::TYPE_BOOLEAN => $record->value === 'true' || $record->value === '1' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle',
                        SalonSetting::TYPE_JSON => 'heroicon-o-queue-list',
                        SalonSetting::TYPE_DECIMAL => 'heroicon-o-percent-badge',
                        default => null,
                    })
                    ->copyable()
                    ->copyMessage(__('resources.salon_setting.value_copied'))
                    ->limit(50),

                // Description
                TextColumn::make('description')
                    ->label(__('resources.salon_setting.description'))
                    ->searchable()
                    ->wrap()
                    ->limit(60)
                    ->toggleable()
                    ->icon('heroicon-o-information-circle')
                    ->color('gray'),

                // Branch Relationship
                TextColumn::make('branch.name')
                    ->label(__('resources.salon_setting.branch'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-building-storefront')
                    ->default(__('resources.salon_setting.global_setting'))
                    ->toggleable(),

                // Timestamps
                TextColumn::make('created_at')
                    ->label(__('resources.salon_setting.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('resources.salon_setting.updated_at'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->sortable()
                    ->icon('heroicon-o-clock')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // فلتر حسب المجموعة
                SelectFilter::make('setting_group')
                    ->label(__('resources.salon_setting.filter_by_group'))
                    ->options([
                        'general' => __('resources.salon_setting.group_general'),
                        'booking' => __('resources.salon_setting.group_booking'),
                        'payment' => __('resources.salon_setting.group_payment'),
                        'notifications' => __('resources.salon_setting.group_notifications'),
                        'loyalty' => __('resources.salon_setting.group_loyalty'),
                        'contact' => __('resources.salon_setting.group_contact'),
                    ])
                    ->multiple(),

                // فلتر حسب نوع البيانات
                SelectFilter::make('type')
                    ->label(__('resources.salon_setting.type'))
                    ->options([
                        SalonSetting::TYPE_STRING => __('resources.salon_setting.type_string'),
                        SalonSetting::TYPE_INTEGER => __('resources.salon_setting.type_integer'),
                        SalonSetting::TYPE_BOOLEAN => __('resources.salon_setting.type_boolean'),
                        SalonSetting::TYPE_JSON => __('resources.salon_setting.type_json'),
                        SalonSetting::TYPE_DECIMAL => __('resources.salon_setting.type_decimal'),
                    ])
                    ->multiple(),

                // فلتر حسب الفرع
                SelectFilter::make('branch_id')
                    ->label(__('resources.salon_setting.filter_by_branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->multiple()
                    ->placeholder(__('resources.salon_setting.all_settings')),
            ])
            ->defaultSort('setting_group', 'asc')
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
