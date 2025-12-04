<?php

namespace App\Filament\Resources\Languages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LanguageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make(__('resources.language.language_details'))
                    ->description(__('resources.language.language_details_desc'))
                    ->icon('heroicon-o-language')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('resources.language.name'))
                            ->required()
                            ->maxLength(100)
                            ->placeholder(__('resources.language.name_placeholder'))
                            ->helperText(__('resources.language.name_helper')),

                        TextInput::make('native_name')
                            ->label(__('resources.language.native_name'))
                            ->maxLength(100)
                            ->placeholder(__('resources.language.native_name_placeholder'))
                            ->helperText(__('resources.language.native_name_helper')),

                        TextInput::make('code')
                            ->label(__('resources.language.code'))
                            ->required()
                            ->maxLength(10)
                            ->placeholder(__('resources.language.code_placeholder'))
                            ->helperText(__('resources.language.code_helper')),
                    ]),

                Section::make(__('resources.language.settings'))
                    ->description(__('resources.language.language_settings_desc'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('order')
                            ->label(__('resources.language.order'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->helperText(__('resources.language.order_helper')),

                        Toggle::make('is_active')
                            ->label(__('resources.language.active'))
                            ->required()
                            ->default(true)
                            ->helperText(__('resources.language.active_helper')),

                        Toggle::make('is_default')
                            ->label(__('resources.language.default_language'))
                            ->required()
                            ->default(false)
                            ->helperText(__('resources.language.default_language_helper')),
                    ]),

            ]);
    }
}
