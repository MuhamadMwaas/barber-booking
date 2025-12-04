<?php

namespace App\Filament\Resources\ReasonLeaves\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class ReasonLeaveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make([ 'default' => 3,
    'sm' => 2,
    'md' =>2,
    'lg' => 3,
    'xl' => 3,
    '2xl' => 3,])
                    ->schema([
                        // العمود الأول: Basic Information + Metadata
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('resources.reason_leave.basic_info'))
                                    ->description(__('resources.reason_leave.basic_info_desc'))
                                    ->icon('heroicon-o-information-circle')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(__('resources.reason_leave.name'))
                                            ->size('lg')
                                            ->weight(FontWeight::Bold)
                                            ->icon('heroicon-m-document-text')
                                            ->color('primary'),

                                        TextEntry::make('description')
                                            ->label(__('resources.reason_leave.description'))
                                            ->icon('heroicon-m-information-circle')
                                            ->color('gray')
                                            ->placeholder('')
                                            ->markdown(),
                                    ]),

                                Section::make(__('resources.reason_leave.metadata'))
                                    ->icon('heroicon-o-clock')
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label(__('resources.reason_leave.created_at'))
                                            ->dateTime()
                                            ->icon('heroicon-m-calendar-days')
                                            ->since()
                                            ->color('gray'),

                                        TextEntry::make('updated_at')
                                            ->label(__('resources.reason_leave.updated_at'))
                                            ->dateTime()
                                            ->icon('heroicon-m-calendar-days')
                                            ->since()
                                            ->color('gray'),
                                    ]),
                            ]),

                        // العمود الثاني والثالث: Translations + Usage Statistics
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                Section::make(__('resources.reason_leave.translations_section'))
                                    ->description(__('resources.reason_leave.translations_section_desc'))
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        TextEntry::make('translations_count')
                                            ->label(__('resources.reason_leave.translations'))
                                            ->state(fn ($record) => $record->translations->count())
                                            ->badge()
                                            ->color('info')
                                            ->icon('heroicon-m-language')
                                            ->visible(fn ($record) => $record->translations->isNotEmpty()),

                                        TextEntry::make('translations_list')
                                            ->label('')
                                            ->state(function ($record) {
                                                if ($record->translations->isEmpty()) {
                                                    return new HtmlString('
                                                        <div style="text-align: center; padding: 2rem; color: #94a3b8;">
                                                            <svg style="margin: 0 auto 0.75rem; width: 3rem; height: 3rem; stroke: currentColor;" fill="none" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                                                            </svg>
                                                            <p style="font-weight: 500; color: #64748b;">' . __('resources.reason_leave.no_translations') . '</p>
                                                        </div>
                                                    ');
                                                }

                                                $translationsHtml = $record->translations->map(function ($translation) {
                                                    $nativeName = $translation->language->native_name ?? $translation->language->name ?? 'Unknown';
                                                    $code = strtoupper($translation->language->code ?? '');

                                                    $badgeColor = match($translation->language->code ?? '') {
                                                        'ar' => 'background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);',
                                                        'en' => 'background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);',
                                                        'de' => 'background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);',
                                                        default => 'background: linear-gradient(135deg, #64748b 0%, #475569 100%);',
                                                    };

                                                    $nameValue = !empty($translation->name) ? htmlspecialchars($translation->name) : '<span style="color: #94a3b8;">—</span>';

                                                    $html = '
                                                        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 0.75rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);">
                                                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                                                                <span style="' . $badgeColor . ' color: white; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.05em;">' . $code . '</span>
                                                                <span style="font-weight: 600; color: #1f2937; font-size: 0.95rem;">' . htmlspecialchars($nativeName) . '</span>
                                                            </div>
                                                            <div style="display: grid; gap: 0.5rem;">
                                                                <div style="display: flex; gap: 0.5rem;">
                                                                    <svg style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: #6b7280; margin-top: 0.125rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                    </svg>
                                                                    <div style="flex: 1;">
                                                                        <div style="font-size: 0.75rem; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">' . __('resources.reason_leave.name') . '</div>
                                                                        <div style="color: #374151; margin-top: 0.125rem; font-size: 0.875rem;">' . $nameValue . '</div>
                                                                    </div>
                                                                </div>';

                                                    if (!empty($translation->description)) {
                                                        $html .= '
                                                                <div style="display: flex; gap: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #f3f4f6;">
                                                                    <svg style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: #6b7280; margin-top: 0.125rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    </svg>
                                                                    <div style="flex: 1;">
                                                                        <div style="font-size: 0.75rem; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">' . __('resources.reason_leave.description') . '</div>
                                                                        <div style="color: #374151; margin-top: 0.125rem; font-size: 0.875rem; line-height: 1.5;">' . htmlspecialchars($translation->description) . '</div>
                                                                    </div>
                                                                </div>';
                                                    }

                                                    $html .= '
                                                            </div>
                                                        </div>';

                                                    return $html;
                                                })->join('');

                                                return new HtmlString($translationsHtml);
                                            }),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false),

                                Section::make(__('resources.reason_leave.usage_statistics'))
                                    ->description(__('resources.reason_leave.usage_statistics_desc'))
                                    ->icon('heroicon-o-chart-bar')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('total_usage')
                                                    ->label(__('resources.reason_leave.total_usage'))
                                                    ->state(fn ($record) => $record->timeOffs()->count())
                                                    ->badge()
                                                    ->size('lg')
                                                    ->weight(FontWeight::Bold)
                                                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                                                    ->icon('heroicon-m-calendar-days'),

                                                TextEntry::make('single_day_leaves')
                                                    ->label(__('resources.reason_leave.single_day_leaves'))
                                                    ->state(fn ($record) => $record->timeOffs()->where('type', 0)->count())
                                                    ->badge()
                                                    ->size('lg')
                                                    ->weight(FontWeight::Bold)
                                                    ->color('info')
                                                    ->icon('heroicon-m-calendar'),

                                                TextEntry::make('multi_day_leaves')
                                                    ->label(__('resources.reason_leave.multi_day_leaves'))
                                                    ->state(fn ($record) => $record->timeOffs()->where('type', 1)->count())
                                                    ->badge()
                                                    ->size('lg')
                                                    ->weight(FontWeight::Bold)
                                                    ->color('warning')
                                                    ->icon('heroicon-m-calendar-days'),
                                            ]),
                                    ])
                                    ->collapsible()
                                    ->collapsed(true),
                            ]),
                    ]),
            ])->columns(1);
    }
}
