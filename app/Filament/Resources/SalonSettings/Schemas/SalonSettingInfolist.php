<?php

namespace App\Filament\Resources\SalonSettings\Schemas;

use App\Models\SalonSetting;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class SalonSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Section - Setting Overview
                Section::make(__('resources.salon_setting.setting_information'))
                    ->description(__('resources.salon_setting.setting_information_desc'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('key')
                                    ->label(__('resources.salon_setting.key'))
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-m-key')
                                    ->badge()
                                    ->color('primary'),

                                TextEntry::make('setting_group')
                                    ->label(__('resources.salon_setting.setting_group'))
                                    ->badge()
                                    ->color(fn ($state) => match($state) {
                                        'general' => 'gray',
                                        'booking' => 'info',
                                        'payment' => 'success',
                                        'notifications' => 'warning',
                                        'loyalty' => 'purple',
                                        'contact' => 'pink',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'general' => __('resources.salon_setting.group_general'),
                                        'booking' => __('resources.salon_setting.group_booking'),
                                        'payment' => __('resources.salon_setting.group_payment'),
                                        'notifications' => __('resources.salon_setting.group_notifications'),
                                        'loyalty' => __('resources.salon_setting.group_loyalty'),
                                        'contact' => __('resources.salon_setting.group_contact'),
                                        default => $state,
                                    })
                                    ->icon('heroicon-m-folder'),
                            ]),

                        TextEntry::make('description')
                            ->label(__('resources.salon_setting.description'))
                            ->columnSpanFull()
                            ->icon('heroicon-m-information-circle')
                            ->color('gray'),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('resources.salon_setting.type'))
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        SalonSetting::TYPE_STRING => __('resources.salon_setting.type_string'),
                                        SalonSetting::TYPE_INTEGER => __('resources.salon_setting.type_integer'),
                                        SalonSetting::TYPE_BOOLEAN => __('resources.salon_setting.type_boolean'),
                                        SalonSetting::TYPE_JSON => __('resources.salon_setting.type_json'),
                                        SalonSetting::TYPE_DECIMAL => __('resources.salon_setting.type_decimal'),
                                        default => $state,
                                    })
                                    ->icon('heroicon-m-variable'),

                                TextEntry::make('branch.name')
                                    ->label(__('resources.salon_setting.branch'))
                                    ->placeholder(__('resources.salon_setting.global_setting'))
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-m-building-storefront'),
                            ]),
                    ]),

                // Setting Value Section
                Section::make(__('resources.salon_setting.setting_value'))
                    ->description(__('resources.salon_setting.current_value'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        // String Value
                        TextEntry::make('value')
                            ->label(__('resources.salon_setting.value'))
                            ->visible(fn ($record) => $record->type === SalonSetting::TYPE_STRING)
                            ->size('lg')
                            ->weight(FontWeight::Bold)
                            ->copyable()
                            ->copyMessage(__('resources.salon_setting.value_copied'))
                            ->copyMessageDuration(1500)
                            ->icon('heroicon-m-pencil-square')
                            ->placeholder('—'),

                        // Integer Value
                        TextEntry::make('value')
                            ->label(__('resources.salon_setting.value'))
                            ->visible(fn ($record) => $record->type === SalonSetting::TYPE_INTEGER)
                            ->size('lg')
                            ->weight(FontWeight::Bold)
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-m-hashtag')
                            ->formatStateUsing(fn ($state) => number_format($state)),

                        // Decimal Value
                        TextEntry::make('value')
                            ->label(__('resources.salon_setting.value'))
                            ->visible(fn ($record) => $record->type === SalonSetting::TYPE_DECIMAL)
                            ->size('lg')
                            ->weight(FontWeight::Bold)
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-m-calculator')
                            ->formatStateUsing(fn ($state) => $state . '%')
                            ->suffix('%'),

                        // Boolean Value
                        IconEntry::make('value')
                            ->label(__('resources.salon_setting.value'))
                            ->visible(fn ($record) => $record->type === SalonSetting::TYPE_BOOLEAN)
                            ->boolean()
                            ->icon(function($record, $state) {
                                if($state=="true" || intval($state) ==1) {
                                    return 'heroicon-m-check-circle';
                                }
                                return 'heroicon-m-x-circle';

                            })
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        // JSON Value
                        TextEntry::make('value')
                            ->label(__('resources.salon_setting.value'))
                            ->visible(fn ($record) => $record->type === SalonSetting::TYPE_JSON)
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state) {
                                $decoded = json_decode($state, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                }
                                return $state;
                            })
                            ->copyable()
                            ->copyMessage(__('resources.salon_setting.value_copied'))
                            ->markdown()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->icon('heroicon-m-code-bracket'),
                    ]),

                // Metadata Section
                Section::make(__('resources.salon_setting.metadata'))
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.salon_setting.created_at'))
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar-days')
                                    ->since()
                                    ->color('gray'),

                                TextEntry::make('updated_at')
                                    ->label(__('resources.salon_setting.updated_at'))
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar-days')
                                    ->since()
                                    ->color('gray'),
                            ]),
                    ]),
            ]);
    }
}
