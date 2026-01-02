<?php

namespace App\Filament\Resources\PageResource\Schemas;

use App\Models\Language;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Grid;

class PageForm
{
    public static function make(): array
    {
        $defaultLocale = config('app.fallback_locale', 'ar');

        return [
            // Section 1: Page Information (Read-only)
            Section::make(__('resources.page_resource.page_information'))
                ->description(__('resources.page_resource.page_information_description'))
                ->schema([
                    Placeholder::make('page_key')
                        ->label(__('resources.page_resource.page_key'))
                        ->content(fn ($record) => $record?->page_key ?? '-'),

                    Placeholder::make('template')
                        ->label(__('resources.page_resource.template'))
                        ->content(fn ($record) => $record?->template ?? '-'),

                    Placeholder::make('version')
                        ->label(__('resources.page_resource.version'))
                        ->content(fn ($record) => $record?->version ?? '1'),

                    Toggle::make('is_published')
                        ->label(__('resources.page_resource.published'))
                        ->helperText(__('resources.page_resource.published_helper'))
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2)
                ->collapsible(),

            // Section 2: Default Language (Required)
            ...self::getDefaultLanguageSection($defaultLocale),

            // Section 3: Additional Languages (Dynamic)
            ...self::getAdditionalLanguageSections($defaultLocale),
        ];
    }

    protected static function getDefaultLanguageSection(string $defaultLocale): array
    {
        $defaultLanguage = Language::where('code', $defaultLocale)->first();
        $languageName = $defaultLanguage?->native_name ?? 'العربية';

        return [
            Section::make($languageName . ' ' . __('resources.page_resource.default_language_suffix'))
                ->description(__('resources.page_resource.default_language_description'))
                ->icon('heroicon-o-language')
                ->schema([
                    TextInput::make("translations.{$defaultLocale}.title")
                        ->label(__('resources.page_resource.title'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder(__('resources.page_resource.title_placeholder'))
                        ->columnSpanFull(),

                    RichEditor::make("translations.{$defaultLocale}.content")
                        ->label(__('resources.page_resource.content'))
                        ->required()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'strike',
                            'link',
                            'h2',
                            'h3',
                            'bulletList',
                            'orderedList',
                            'blockquote',
                            'codeBlock',
                            'undo',
                            'redo',
                        ])
                        ->columnSpanFull(),

                    Grid::make(2)
                        ->schema([
                            TextInput::make("translations.{$defaultLocale}.meta.title")
                                ->label(__('resources.page_resource.seo_title'))
                                ->maxLength(60)
                                ->helperText(__('resources.page_resource.seo_title_helper'))
                                ->placeholder(__('resources.page_resource.seo_title_placeholder')),

                            Textarea::make("translations.{$defaultLocale}.meta.description")
                                ->label(__('resources.page_resource.seo_description'))
                                ->rows(3)
                                ->maxLength(160)
                                ->helperText(__('resources.page_resource.seo_description_helper'))
                                ->placeholder(__('resources.page_resource.seo_description_placeholder')),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    protected static function getAdditionalLanguageSections(string $defaultLocale): array
    {
        $sections = [];

        $languages = Language::where('is_active', true)
            ->where('code', '!=', $defaultLocale)
            ->orderBy('order')
            ->get();

        foreach ($languages as $language) {
            $sections[] = Section::make($language->native_name)
                ->description(sprintf(__('resources.page_resource.translation_optional'), $language->native_name))
                ->icon('heroicon-o-globe-alt')
                ->schema([
                    TextInput::make("translations.{$language->code}.title")
                        ->label(__('resources.page_resource.title'))
                        ->maxLength(255)
                        ->placeholder(__('resources.page_resource.title_placeholder'))
                        ->columnSpanFull(),

                    RichEditor::make("translations.{$language->code}.content")
                        ->label(__('resources.page_resource.content'))
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'strike',
                            'link',
                            'h2',
                            'h3',
                            'bulletList',
                            'orderedList',
                            'blockquote',
                            'codeBlock',
                            'undo',
                            'redo',
                        ])
                        ->columnSpanFull(),

                    Grid::make(2)
                        ->schema([
                            TextInput::make("translations.{$language->code}.meta.title")
                                ->label(__('resources.page_resource.seo_title'))
                                ->maxLength(60)
                                ->helperText(__('resources.page_resource.seo_title_helper'))
                                ->placeholder(__('resources.page_resource.seo_title_placeholder')),

                            Textarea::make("translations.{$language->code}.meta.description")
                                ->label(__('resources.page_resource.seo_description'))
                                ->rows(3)
                                ->maxLength(160)
                                ->helperText(__('resources.page_resource.seo_description_helper'))
                                ->placeholder(__('resources.page_resource.seo_description_placeholder')),
                        ]),
                ])
                ->columnSpanFull()
                ->collapsed();
        }

        return $sections;
    }
}
