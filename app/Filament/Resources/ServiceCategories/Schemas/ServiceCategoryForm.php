<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use App\Models\Language;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ServiceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Tabs::make('Service Category Details')
                    ->columnSpanFull()
                    ->tabs([
                        // Basic Information Tab
                        Tabs\Tab::make(__('resources.service_category.basic_info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make(__('resources.service_category.category_details'))
                                    ->description(__('resources.service_category.category_details_desc'))
                                    ->icon('heroicon-o-folder')
                                    ->columns(2)
                                    ->schema([
                                        // Category Name
                                        TextInput::make('name')
                                            ->label(__('resources.service_category.name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder(__('resources.service_category.name_placeholder'))
                                            ->helperText(__('resources.service_category.name_helper'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                                // Auto-generate sort order if not set
                                                if (empty($get('sort_order'))) {
                                                    $set('sort_order', \App\Models\ServiceCategory::max('sort_order') + 1 ?? 1);
                                                }
                                            }),

                                        // Sort Order
                                        TextInput::make('sort_order')
                                            ->label(__('resources.service_category.sort_order'))
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(fn () => \App\Models\ServiceCategory::max('sort_order') + 1 ?? 1)
                                            ->helperText(__('resources.service_category.sort_order_helper')),

                                        // Category Description
                                        Textarea::make('description')
                                            ->label(__('resources.service_category.description'))
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->placeholder(__('resources.service_category.description_placeholder'))
                                            ->helperText(__('resources.service_category.description_helper')),
                                    ]),

                                Section::make(__('resources.service_category.visual_settings'))
                                    ->description(__('resources.service_category.visual_settings_desc'))
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        // Category Image
                                        FileUpload::make('image_url')
                                            ->label(__('resources.service_category.image'))
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '1:1',
                                                '4:3',
                                            ])
                                            ->afterStateHydrated(function (FileUpload $component, $state, $record) {
                                                if ($record && $record->image && $record->image->path) {
                                                    $component->state([$record->image->path]);
                                                }
                                            })
                                            ->maxSize(2048)
                                            ->directory('temp/service-categories/uploads')
                                            ->visibility('public')
                                            ->disk('public')
                                            ->helperText(__('resources.service_category.image_helper'))
                                            ->saveRelationshipsUsing(null)
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // Translations Tab
                        Tabs\Tab::make(__('resources.service_category.translations'))
                            ->icon('heroicon-o-language')
                            ->badge(fn () => Language::where('is_active', true)->count())
                            ->schema(function () {
                                $languages = Language::where('is_active', true)->get();
                                $sections = [];

                                foreach ($languages as $language) {
                                    $sections[] = Section::make($language->native_name . ' (' . $language->name . ')')
                                        ->description(__('resources.service_category.translation_for_language', ['language' => $language->native_name]))
                                        ->icon('heroicon-o-globe-alt')
                                        ->columns(1)
                                        ->schema([
                                            Grid::make(1)
                                                ->schema([
                                                    TextInput::make("translations.{$language->id}.language_id")
                                                        ->label(__('resources.service_category.language_id'))
                                                        ->default($language->id)
                                                        ->hidden()
                                                        ->dehydrated(),

                                                    TextInput::make("translations.{$language->id}.language_code")
                                                        ->label(__('resources.service_category.language_code'))
                                                        ->default($language->code)
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->helperText(__('resources.service_category.language_code_helper')),

                                                    TextInput::make("translations.{$language->id}.name")
                                                        ->label(__('resources.service_category.translated_name'))
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->placeholder(__('resources.service_category.translated_name_placeholder'))
                                                        ->helperText(__('resources.service_category.translated_name_helper')),

                                                    Textarea::make("translations.{$language->id}.description")
                                                        ->label(__('resources.service_category.translated_description'))
                                                        ->rows(4)
                                                        ->placeholder(__('resources.service_category.translated_description_placeholder'))
                                                        ->helperText(__('resources.service_category.translated_description_helper')),
                                                ]),
                                        ]);
                                }

                                return $sections;
                            }),

                        // Settings Tab
                        Tabs\Tab::make(__('resources.service_category.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make(__('resources.service_category.visibility_settings'))
                                    ->description(__('resources.service_category.visibility_settings_desc'))
                                    ->icon('heroicon-o-eye')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label(__('resources.service_category.active'))
                                            ->helperText(__('resources.service_category.active_helper'))
                                            ->default(true)
                                            ->inline(false),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}