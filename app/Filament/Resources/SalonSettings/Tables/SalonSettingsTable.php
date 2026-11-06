<?php

namespace App\Filament\Resources\SalonSettings\Tables;

use App\Filament\Resources\SalonSettings\SalonSettingCatalog;
use App\Models\SalonSetting;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalonSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('resources.salon_setting.setting_name'))
                    ->formatStateUsing(fn (string $state): string => SalonSettingCatalog::label($state))
                    ->description(fn (SalonSetting $record): string => $record->key)
                    ->weight(FontWeight::SemiBold)
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('key', 'like', "%{$search}%")
                        ->orWhereIn('key', SalonSettingCatalog::keysMatching($search))),

                TextColumn::make('value')
                    ->label(__('resources.salon_setting.value'))
                    ->state(fn (SalonSetting $record): string => SalonSettingCatalog::display($record))
                    ->color(fn (SalonSetting $record): string => SalonSettingCatalog::displayColor($record))
                    ->icon(fn (SalonSetting $record): ?string => SalonSettingCatalog::displayIcon($record))
                    ->weight(FontWeight::Medium)
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('setting_group')
                    ->label(__('resources.salon_setting.setting_group'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SalonSettingCatalog::groupLabel($state))
                    ->color(fn (?string $state): string => SalonSettingCatalog::groupColor($state))
                    ->icon(fn (?string $state): string => SalonSettingCatalog::groupIcon($state))
                    ->sortable(),

                TextColumn::make('usage')
                    ->label(__('resources.salon_setting.usage'))
                    ->badge()
                    ->state(fn (SalonSetting $record): string => SalonSettingCatalog::isInUse($record->key)
                        ? __('resources.salon_setting.in_use')
                        : __('resources.salon_setting.not_in_use'))
                    ->color(fn (SalonSetting $record): string => SalonSettingCatalog::isInUse($record->key) ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('description')
                    ->label(__('resources.salon_setting.description'))
                    ->state(fn (SalonSetting $record): ?string => SalonSettingCatalog::description($record))
                    ->color('gray')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->label(__('resources.salon_setting.type'))
                    ->formatStateUsing(fn (?string $state): string => SalonSettingCatalog::typeLabel($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('branch.name')
                    ->label(__('resources.salon_setting.branch'))
                    ->default(__('resources.salon_setting.global_setting'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('resources.salon_setting.updated_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('resources.salon_setting.type'))
                    ->options(SalonSettingCatalog::typeOptions())
                    ->multiple(),

                SelectFilter::make('branch_id')
                    ->label(__('resources.salon_setting.filter_by_branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->multiple(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('setting_group')->orderBy('id'))
            ->defaultPaginationPageOption(25)
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ]);
    }
}
