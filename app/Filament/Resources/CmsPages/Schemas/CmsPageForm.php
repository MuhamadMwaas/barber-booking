<?php

namespace App\Filament\Resources\CmsPages\Schemas;

use App\Models\CmsPage;
use App\Services\Cms\CmsBlockRegistry;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CmsPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([

            /* ════════════════════════════════════════
             │  1. Page Identity  (full width)
             ════════════════════════════════════════ */
            Section::make(__('cms.resource.section_page_info'))
                ->description(__('cms.resource.section_page_info_desc'))
                ->icon('heroicon-o-document-text')
                ->schema([

                    Grid::make(2)->schema([

                        TextInput::make('name')
                            ->label(__('cms.resource.field_name'))
                            ->placeholder(__('cms.resource.field_name_placeholder'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, ?CmsPage $record): void {
                                if (! $record) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->prefix('/api/pages/')
                            ->required()
                            ->unique(table: 'cms_pages', column: 'slug', ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash()
                            ->live(onBlur: true)
                            ->helperText(__('cms.resource.field_slug_hint')),
                    ]),

                    // Live API URL preview — updates as slug changes
                    Placeholder::make('_api_preview')
                        ->label(__('cms.resource.api_preview_label'))
                        ->content(fn ($get) => new HtmlString(
                            self::renderApiPreview($get('slug'))
                        ))
                        ->live()
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label(__('cms.resource.field_is_active'))
                        ->helperText(__('cms.resource.field_is_active_hint'))
                        ->default(true)
                        ->inline(false)
                        ->onColor('success')
                        ->offColor('danger'),

                ])
                ->columns(1),

            /* ════════════════════════════════════════
             │  2. Content Blocks  (full width)
             ════════════════════════════════════════ */
            Section::make(__('cms.resource.section_blocks'))
                ->description(__('cms.resource.section_blocks_desc'))
                ->icon('heroicon-o-squares-2x2')
                ->schema([

                    // How-to hint
                    Placeholder::make('_blocks_hint')
                        ->hiddenLabel()
                        ->content(new HtmlString(self::renderBlocksHint()))
                        ->columnSpanFull(),

                    Builder::make('blocks')
                        ->hiddenLabel()
                        ->blocks(app(CmsBlockRegistry::class)->filamentBlocks())
                        ->reorderable()
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->cloneable()
                        ->addActionLabel(__('cms.resource.add_block'))
                        ->default([])
                        ->columnSpanFull(),

                ])
                ->columns(1),

        ]);
    }

    /* ──────────────────────────────────────────
     │  Private rendering helpers
     ────────────────────────────────────────── */

    private static function renderApiPreview(?string $slug): string
    {
        $base    = rtrim(config('app.url'), '/');
        $slugHtml = filled($slug)
            ? '<span class="font-semibold text-primary-600">' . htmlspecialchars($slug) . '</span>'
            : '<span class="italic text-gray-400">' . __('cms.resource.api_slug_placeholder') . '</span>';

        $langs = collect(config('cms.supported_languages'))
            ->map(fn ($cfg, $code) => "<code class='px-1 rounded bg-gray-100 text-xs'>?lang={$code}</code>")
            ->implode(' ');

        return '
<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm space-y-1.5">
  <div class="flex items-center gap-2 font-mono">
    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold bg-emerald-100 text-emerald-700">GET</span>
    <span class="text-gray-500">' . $base . '/api/pages/</span>' . $slugHtml . '
  </div>
  <div class="text-gray-400 text-xs">' . __('cms.resource.api_lang_options') . ': ' . $langs . '</div>
</div>';
    }

    private static function renderBlocksHint(): string
    {
        return '
<div class="rounded-lg bg-info-50 border border-info-200 px-4 py-2.5 text-sm text-info-700 flex items-start gap-2">

  <span>' . __('cms.resource.blocks_hint') . '</span>
</div>';
    }
}
