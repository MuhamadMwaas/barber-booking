<?php

namespace App\Filament\Resources\SalonSettings\Schemas;

use App\Models\SalonSetting;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalonSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('resources.salon_setting.setting_information'))
                    ->description(__('resources.salon_setting.setting_information_desc'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('resources.salon_setting.key'))
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText(__('resources.salon_setting.key_helper'))
                                    ->prefixIcon('heroicon-m-key'),

                                Select::make('setting_group')
                                    ->label(__('resources.salon_setting.setting_group'))
                                    ->options([
                                        'general' => __('resources.salon_setting.group_general'),
                                        'booking' => __('resources.salon_setting.group_booking'),
                                        'payment' => __('resources.salon_setting.group_payment'),
                                        'notifications' => __('resources.salon_setting.group_notifications'),
                                        'loyalty' => __('resources.salon_setting.group_loyalty'),
                                        'contact' => __('resources.salon_setting.group_contact'),
                                    ])
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefixIcon('heroicon-m-folder'),
                            ]),

                        Textarea::make('description')
                            ->label(__('resources.salon_setting.description'))
                            ->disabled()
                            ->dehydrated()
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText(__('resources.salon_setting.description_helper')),

                        Select::make('branch_id')
                            ->label(__('resources.salon_setting.branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated()
                            ->placeholder(__('resources.salon_setting.global_setting'))
                            ->helperText(__('resources.salon_setting.branch_helper'))
                            ->prefixIcon('heroicon-m-building-storefront'),
                    ])
                    ->columnSpan(3),

                Section::make(__('resources.salon_setting.setting_value'))
                    ->description(__('resources.salon_setting.setting_value_desc'))
                    ->icon('heroicon-o-pencil-square')
                    ->schema(function($record) {
                        $schema[]= Select::make('type')
                            ->label(__('resources.salon_setting.type'))
                            ->options([
                                SalonSetting::TYPE_STRING => __('resources.salon_setting.type_string'),
                                SalonSetting::TYPE_INTEGER => __('resources.salon_setting.type_integer'),
                                SalonSetting::TYPE_BOOLEAN => __('resources.salon_setting.type_boolean'),
                                SalonSetting::TYPE_JSON => __('resources.salon_setting.type_json'),
                                SalonSetting::TYPE_DECIMAL => __('resources.salon_setting.type_decimal'),
                            ])
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->live()
                            ->prefixIcon('heroicon-m-variable')
                            ->helperText(__('resources.salon_setting.type_helper'));
                        $schema[]= self::getValueInput($record->type);
                        return $schema;

                    })
                    ->columnSpan(3),
            ]);
    }


    public static function getValueInput($type)
    {
        if ($type === SalonSetting::TYPE_STRING) {

            // String Value Input
            return TextInput::make('value')
                ->label(__('resources.salon_setting.value'))
                ->required()
                ->visible(fn($get) => $get('type') === SalonSetting::TYPE_STRING)
                ->maxLength(255)
                ->placeholder(__('resources.salon_setting.value_placeholder'))
                ->helperText(__('resources.salon_setting.string_helper'))
                ->prefixIcon('heroicon-m-pencil-square');
        }

        if ($type === SalonSetting::TYPE_INTEGER) {
            // Integer Value Input
            return TextInput::make('value')
                ->label(__('resources.salon_setting.value'))
                ->required()
                ->visible(fn($get) => $get('type') === SalonSetting::TYPE_INTEGER)
                ->numeric()
                ->minValue(0)
                ->placeholder('0')
                ->helperText(__('resources.salon_setting.integer_helper'))
                ->prefixIcon('heroicon-m-hashtag');
        }

        if ($type === SalonSetting::TYPE_DECIMAL) {
            // Decimal Value Input
            return TextInput::make('value')
                ->label(__('resources.salon_setting.value'))
                ->required()
                ->visible(fn($get) => $get('type') === SalonSetting::TYPE_DECIMAL)
                ->numeric()
                ->step(0.01)
                ->minValue(0)
                ->placeholder('0.00')
                ->helperText(__('resources.salon_setting.decimal_helper'))
                ->prefixIcon('heroicon-m-calculator')
                ->suffix('%');
        }

        if ($type === SalonSetting::TYPE_BOOLEAN) {
            // Boolean Value Toggle
            return Toggle::make('value')
                ->label(__('resources.salon_setting.value'))
                ->visible(fn($get) => $get('type') === SalonSetting::TYPE_BOOLEAN)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-m-check')
                ->offIcon('heroicon-m-x-mark')
                ->formatStateUsing(function($record, $state) {
                    return $record->value == 'true' || intval($record->value) ==1;
                })
                ->dehydrateStateUsing(fn($state) => $state ? 'true' : 'false')
                ->helperText(__('resources.salon_setting.boolean_helper'))
                ->inline(false);
        }

        if ($type == SalonSetting::TYPE_JSON) {
            // JSON Value Textarea

            return KeyValue::make('value')
                ->label(__('resources.salon_setting.value'))
                ->required()
                ->dehydrated(true)
                ->helperText(__('resources.salon_setting.json_helper'))
                ->columnSpanFull()
                ->reactive();
        }


    }
}
