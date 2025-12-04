<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class ServiceCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Section with Image and Main Info
                Section::make('')
                    ->schema([
                        ImageEntry::make('category_image')
                            ->label(__('resources.service_category.image'))
                            ->state(function ($record) {
                                if ($record->image && $record->image->path) {
                                    return asset('storage/' . $record->image->path);
                                }
                                return null;
                            })
                            ->height(200)
                            ->defaultImageUrl(url('/images/placeholder-category.png'))
                            ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
                            ->extraAttributes(['style' => 'width: 100%; object-fit: cover;']),

                        TextEntry::make('name')
                            ->label(__('resources.service_category.name'))
                            ->size('lg')
                            ->weight(FontWeight::Bold)
                            ->color('primary'),

                        TextEntry::make('description')
                            ->label(__('resources.service_category.description'))
                            ->columnSpanFull()
                            ->markdown()
                            ->default(__('resources.user.not_provided')),
                    ])
                    ->columns(1),

                // Category Details Section
                Section::make(__('resources.service_category.category_details'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label(__('resources.service_category.status'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                TextEntry::make('sort_order')
                                    ->label(__('resources.service_category.sort_order'))
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('services_count')
                                    ->label(__('resources.service_category.services_count'))
                                    ->state(fn ($record) => $record->services()->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('info')
                                    ->icon('heroicon-o-squares-2x2'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.service_category.created_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('updated_at')
                                    ->label(__('resources.service_category.updated_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar')
                                    ->since()
                                    ->color('gray'),
                            ]),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // Grid for Services and Translations side by side
                Grid::make(2)
                    ->schema([
                        // Services List Section
                        Section::make(__('resources.service.plural_label'))
                            ->description(__('resources.service_category.services_in_category'))
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                TextEntry::make('services_list')
                                    ->label('')
                                    ->state(function ($record) {
                                        if ($record->services->isEmpty()) {
                                            return new HtmlString('
                                                <div style="text-align: center; padding: 2rem; color: #64748b;">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                    </svg>
                                                    <p style="margin-top: 0.5rem; font-weight: 500;">' . __('resources.user.no_services_assigned_desc') . '</p>
                                                </div>
                                            ');
                                        }

                                        $servicesHtml = $record->services->map(function ($service, $index) {
                                            $isActive = $service->is_active;
                                            $statusDot = $isActive
                                                ? '<span style="display: inline-block; width: 8px; height: 8px; background: #22c55e; border-radius: 50%; margin-right: 6px;"></span>'
                                                : '<span style="display: inline-block; width: 8px; height: 8px; background: #94a3b8; border-radius: 50%; margin-right: 6px;"></span>';

                                            $colorDot = $service->color_code
                                                ? '<span style="display: inline-block; width: 20px; height: 20px; background: ' . htmlspecialchars($service->color_code) . '; border-radius: 4px; margin-left: 8px; border: 2px solid #e2e8f0;"></span>'
                                                : '';

                                            return '
                                                <div style="display: flex; align-items: center; padding: 0.75rem 1rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.2s;">
                                                    <div style="flex: 1;">
                                                        <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem; display: flex; align-items: center;">
                                                            ' . $statusDot . htmlspecialchars($service->name) . $colorDot . '
                                                        </div>
                                                        <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem;">
                                                            <span style="margin-right: 1rem;">⏱️ ' . $service->duration_minutes . ' ' . __('resources.service.minutes') . '</span>
                                                            <span>💰 ' . number_format($service->price, 2) . ' ' . __('resources.service.sar') . '</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ';
                                        })->join('');

                                        return new HtmlString('
                                            <div style="max-height: 400px; overflow-y: auto; padding: 0.5rem;">
                                                ' . $servicesHtml . '
                                            </div>
                                        ');
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(false)
                            ->columnSpan(1),

                        // Translations Section
                        Section::make(__('resources.service_category.translations'))
                            ->description(__('resources.service.translations_section_desc'))
                            ->icon('heroicon-o-language')
                            ->schema([
                                TextEntry::make('translations_count')
                                    ->label(__('resources.service_category.translations'))
                                    ->state(fn ($record) => $record->translations->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('info')
                                    ->icon('heroicon-o-globe-alt')
                                    ->visible(fn ($record) => $record->translations->isNotEmpty()),

                                TextEntry::make('translations_list')
                                    ->label('')
                                    ->state(function ($record) {
                                        if ($record->translations->isEmpty()) {
                                            return new HtmlString('
                                                <div style="text-align: center; padding: 2rem; color: #64748b;">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                                                    </svg>
                                                    <p style="margin-top: 0.5rem; font-weight: 500;">' . __('No translations available') . '</p>
                                                </div>
                                            ');
                                        }

                                        $translationsHtml = $record->translations->map(function ($translation) {
                                            $langName = $translation->language->name ?? $translation->language_code;
                                            $nativeName = $translation->language->native_name ?? $langName;
                                            $flag = match($translation->language_code) {
                                                'ar' => '🇸🇦',
                                                'en' => '🇬🇧',
                                                'de' => '🇩🇪',
                                                default => '🌐',
                                            };

                                            return '
                                                <div style="padding: 1rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.75rem;">
                                                    <div style="font-weight: 600; color: #1e293b; font-size: 1rem; margin-bottom: 0.5rem;">
                                                        ' . $flag . ' ' . htmlspecialchars($nativeName) . '
                                                    </div>
                                                    <div style="font-size: 0.9rem; color: #475569; margin-bottom: 0.25rem;">
                                                        📝 <strong>' . __('resources.service_category.name') . ':</strong> ' . htmlspecialchars($translation->name) . '
                                                    </div>
                                                    ' . ($translation->description
                                                        ? '<div style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9;">
                                                            ℹ️ ' . htmlspecialchars(substr($translation->description, 0, 150)) . ($translation->description && strlen($translation->description) > 150 ? '...' : '') . '
                                                        </div>'
                                                        : '') . '
                                                </div>
                                            ';
                                        })->join('');

                                        return new HtmlString('
                                            <div style="max-height: 400px; overflow-y: auto; padding: 0.5rem;">
                                                ' . $translationsHtml . '
                                            </div>
                                        ');
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(false)
                            ->columnSpan(1),
                    ]),

                // Statistics Section
                Section::make(__('resources.user.customer_stats'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_services')
                                    ->label(__('resources.service_category.total_services'))
                                    ->state(fn ($record) => $record->services()->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('info')
                                    ->icon('heroicon-o-squares-2x2'),

                                TextEntry::make('active_services')
                                    ->label(__('resources.service_category.active_services'))
                                    ->state(fn ($record) => $record->services()->where('is_active', true)->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle'),

                                TextEntry::make('featured_services')
                                    ->label(__('resources.service_category.featured_services'))
                                    ->state(fn ($record) => $record->services()->where('is_featured', true)->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('warning')
                                    ->icon('heroicon-o-star'),

                                TextEntry::make('average_price')
                                    ->label(__('resources.service.average_price'))
                                    ->state(fn ($record) => 'SAR ' . number_format(
                                        $record->services()->avg('price') ?? 0, 2
                                    ))
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->icon('heroicon-o-currency-dollar'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
