<?php

namespace App\Cms\Blocks;

use App\Models\Language;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Collection;

abstract class AbstractCmsBlock implements CmsBlockContract
{
    /* ==========================
     | Translation helpers (instance — used at transform time)
     |========================== */

    protected function translation(array $block, string $language, string $fallbackLanguage): array
    {
        $translations = $block['translations'] ?? [];
        $current      = $translations[$language] ?? null;

        if ($this->hasValidContent($current)) {
            return $current;
        }

        $fallback = $translations[$fallbackLanguage] ?? [];

        return is_array($fallback) ? $fallback : [];
    }

    protected function hasValidContent(mixed $content): bool
    {
        if (! is_array($content)) {
            return false;
        }

        foreach ($content as $value) {
            if (is_string($value) && filled(trim($value))) {
                return true;
            }

            if (is_array($value) && count(array_filter($value, fn ($item) => filled($item))) > 0) {
                return true;
            }
        }

        return false;
    }

    /* ==========================
     | Props helper (instance — used at transform time)
     |========================== */

    protected function props(array $block, string $language): array
    {
        $props     = $block['props'] ?? [];
        $alignment = $props['alignment'] ?? 'auto';

        if ($alignment === 'auto') {
            $alignment = config("cms.supported_languages.{$language}.default_alignment", 'left');
        }

        return array_merge(
            [
                'alignment'        => $alignment,
                'color'            => 'default',
                'background_color' => 'default',
                'size'             => 'default',
                'style'            => 'default',
            ],
            $props,
            ['alignment' => $alignment],
        );
    }

    protected function baseResponse(array $block, string $language): array
    {
        return [
            'id'    => $block['id'] ?? null,
            'type'  => static::type(),
            'props' => $this->props($block, $language),
        ];
    }

    /* ==========================
     | Language helpers (static — used at Filament form-build time)
     |========================== */

    /** Per-request cache so the DB is only queried once across all blocks. */
    private static ?Collection $langCache = null;

    /**
     * Returns all active languages, ordered by `order`.
     * Results are cached for the lifetime of the PHP request.
     */
    protected static function activeLanguages(): Collection
    {
        return self::$langCache ??= Language::where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /** Number of active languages (used as Grid column count). */
    protected static function langCount(): int
    {
        return max(1, static::activeLanguages()->count());
    }

    /**
     * Grid of TextInput — one per active language.
     * Best for single short-text translation fields (e.g. heading text).
     */
    protected static function transTextGrid(string $field): Grid
    {
        $inputs = static::activeLanguages()
            ->map(fn (Language $lang) =>
                TextInput::make("translations.{$lang->code}.{$field}")
                    ->label($lang->native_name)
                    ->required()
            )
            ->all();

        return Grid::make(static::langCount())->schema($inputs);
    }

    /**
     * Grid of Textarea — one per active language.
     * Best for longer text fields (e.g. paragraph body).
     */
    protected static function transTextareaGrid(string $field, int $rows = 4): Grid
    {
        $inputs = static::activeLanguages()
            ->map(fn (Language $lang) =>
                Textarea::make("translations.{$lang->code}.{$field}")
                    ->label($lang->native_name)
                    ->rows($rows)
                    ->required()
            )
            ->all();

        return Grid::make(static::langCount())->schema($inputs);
    }

    /**
     * Tabs — one Tab per active language.
     * Best when each language needs multiple fields (e.g. title + body).
     *
     * @param  callable(Language): array  $fieldsBuilder
     */
    protected static function langTabs(callable $fieldsBuilder): Tabs
    {
        $tabs = static::activeLanguages()
            ->map(fn (Language $lang) =>
                Tab::make($lang->native_name)
                    ->schema($fieldsBuilder($lang))
            )
            ->all();

        return Tabs::make('translations')
            ->tabs($tabs)
            ->columnSpanFull();
    }

    /**
     * Tabs with a Repeater per language — for ordered/unordered list items.
     */
    protected static function transRepeaterTabs(string $field): Tabs
    {
        return static::langTabs(fn (Language $lang) => [
            Repeater::make("translations.{$lang->code}.{$field}")
                ->hiddenLabel()
                ->schema([
                    TextInput::make('value')
                        ->label(__('cms.fields.item'))
                        ->required(),
                ])
                ->defaultItems(1)
                ->required()
                ->columnSpanFull(),
        ]);
    }

    /**
     * Collapsed "Display Options" section — wraps visual props that most users
     * will rarely change (alignment, color, etc.).
     */
    protected static function propsSection(array $schema): Section
    {
        return Section::make(__('cms.block_sections.display_options'))
            ->icon('heroicon-m-paint-brush')
            ->schema($schema)
            ->collapsible()
            ->collapsed()
            ->compact()
            ->columnSpanFull();
    }
}
