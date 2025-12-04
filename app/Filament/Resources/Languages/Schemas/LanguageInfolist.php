<?php

namespace App\Filament\Resources\Languages\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class LanguageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Section - Language Overview
                Section::make(__('resources.language.language_details'))
                    ->description(__('resources.language.language_details_desc'))
                    ->icon('heroicon-o-language')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('resources.language.name'))
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-m-identification'),

                                TextEntry::make('native_name')
                                    ->label(__('resources.language.native_name'))
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-m-globe-alt'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('code')
                                    ->label(__('resources.language.code'))
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-m-code-bracket'),

                                TextEntry::make('order')
                                    ->label(__('resources.language.order'))
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-m-numbered-list'),
                            ]),
                    ]),

                // Language Settings Section
                Section::make(__('resources.language.language_settings'))
                    ->description(__('resources.language.language_settings_desc'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label(__('resources.language.active'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                IconEntry::make('is_default')
                                    ->label(__('resources.language.default_language'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-star')
                                    ->falseIcon('heroicon-o-minus-circle')
                                    ->trueColor('warning')
                                    ->falseColor('gray'),
                            ]),
                    ]),

                // Timestamps Section
                Section::make(__('resources.language.basic_info'))
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.language.created_at'))
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar-days'),

                                TextEntry::make('updated_at')
                                    ->label(__('resources.language.updated_at'))
                                    ->dateTime()
                                    ->icon('heroicon-m-calendar-days'),

                                TextEntry::make('deleted_at')
                                    ->label(__('resources.language.deleted_at'))
                                    ->dateTime()
                                    ->icon('heroicon-m-trash')
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
