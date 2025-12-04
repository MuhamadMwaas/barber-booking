<?php

namespace App\Filament\Resources\ReasonLeaves\Schemas;

use App\Models\Language;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReasonLeaveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Tabs::make('Reason Leave Details')
                    ->columnSpanFull()
                    ->tabs([
                        // Basic Information Tab
                        Tabs\Tab::make(__('resources.reason_leave.basic_info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make(__('resources.reason_leave.basic_info'))
                                    ->description(__('resources.reason_leave.basic_info_desc'))
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('resources.reason_leave.name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder(__('resources.reason_leave.name_placeholder'))
                                            ->helperText(__('resources.reason_leave.name_helper'))
                                            ->prefixIcon('heroicon-m-document-text')
                                            ->columnSpanFull(),

                                        Textarea::make('description')
                                            ->label(__('resources.reason_leave.description'))
                                            ->maxLength(1000)
                                            ->rows(4)
                                            ->placeholder(__('resources.reason_leave.description_placeholder'))
                                            ->helperText(__('resources.reason_leave.description_helper'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // Translations Tab
                        Tabs\Tab::make(__('resources.reason_leave.translations_section'))
                            ->icon('heroicon-o-language')
                            ->schema(function () {
                                $languages = Language::where('is_active', true)->get();
                                $sections = [];

                                foreach ($languages as $language) {
                                    $sections[] = Section::make($language->native_name . ' (' . strtoupper($language->code) . ')')
                                        ->description(__('resources.reason_leave.translation_for_language', ['language' => $language->native_name]))
                                        ->icon('heroicon-m-language')
                                        ->schema([
                                            Hidden::make("translations.{$language->id}.language_id")
                                                ->default($language->id)
                                                ->dehydrated(),


                                            TextInput::make("translations.{$language->id}.name")
                                                ->label(__('resources.reason_leave.name'))
                                                ->maxLength(255)
                                                ->placeholder(__('resources.reason_leave.name_placeholder'))
                                                ->prefixIcon('heroicon-m-document-text'),

                                            Textarea::make("translations.{$language->id}.description")
                                                ->label(__('resources.reason_leave.description'))
                                                ->maxLength(1000)
                                                ->rows(3)
                                                ->placeholder(__('resources.reason_leave.description_placeholder')),
                                        ])
                                        ->columns(1)
                                        ->collapsible()
                                        ->collapsed(false);
                                }

                                return $sections;
                            }),
                    ]),
            ]);
    }
}
