<?php

namespace App\Filament\Resources\SalonSettings\Schemas;

use App\Filament\Resources\SalonSettings\SalonSettingCatalog;
use App\Models\SalonSetting;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class SalonSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make(__('resources.salon_setting.setting_value'))
                    ->description(__('resources.salon_setting.current_value'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Callout::make(__('resources.salon_setting.not_in_use_title'))
                            ->description(__('resources.salon_setting.not_in_use_desc'))
                            ->warning()
                            ->visible(fn (SalonSetting $record): bool => ! SalonSettingCatalog::isInUse($record->key)),

                        TextEntry::make('value')
                            ->label(fn (SalonSetting $record): string => SalonSettingCatalog::label($record->key))
                            ->state(fn (SalonSetting $record): string => SalonSettingCatalog::display($record))
                            ->color(fn (SalonSetting $record): string => SalonSettingCatalog::displayColor($record))
                            ->icon(fn (SalonSetting $record): ?string => SalonSettingCatalog::displayIcon($record))
                            ->helperText(fn (SalonSetting $record): ?string => SalonSettingCatalog::description($record))
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                    ])
                    ->columnSpan(['default' => 1, 'lg' => 2]),

                Section::make(__('resources.salon_setting.setting_information'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        ...SalonSettingForm::detailEntries(),

                        TextEntry::make('updated_at')
                            ->label(__('resources.salon_setting.updated_at'))
                            ->since()
                            ->dateTimeTooltip(),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
