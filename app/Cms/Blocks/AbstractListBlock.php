<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Toggle;

abstract class AbstractListBlock extends AbstractCmsBlock
{
    /**
     * Base schema shared by ordered and unordered list blocks.
     * Uses language Tabs so each language gets its own Repeater — no crowded grids.
     */
    protected static function commonSchema(): array
    {
        return [
            Toggle::make('is_active')
                ->label(__('cms.fields.is_active'))
                ->default(true)
                ->inline(),

            // One Repeater per language, displayed as Tabs
            static::transRepeaterTabs('items'),
        ];
    }

    /**
     * Flattens Repeater's array-of-objects into a plain array of strings for the API.
     * Filament Repeater saves items as [['value' => 'foo'], ...] — we return ['foo', ...].
     */
    protected function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => is_array($item) ? ($item['value'] ?? null) : $item)
            ->filter(fn ($item) => filled($item))
            ->values()
            ->all();
    }

    public function transform(array $block, string $language, string $fallbackLanguage): ?array
    {
        $content = $this->translation($block, $language, $fallbackLanguage);
        $items   = $this->normalizeItems($content['items'] ?? []);

        if (count($items) === 0) {
            return null;
        }

        return array_merge($this->baseResponse($block, $language), [
            'content' => ['items' => $items],
        ]);
    }
}
