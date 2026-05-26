<?php

namespace App\Services\Cms;

use Illuminate\Support\Str;

class CmsBlockNormalizer
{
    /**
     * Ensures every block in the array has the required keys before storage.
     *
     * Handles two input formats:
     *  • Filament Builder format  → { type, data: { …all form fields… } }
     *  • Already-flat format      → { id, type, …all form fields… }
     *
     * All block-specific fields (url, image, is_active, props, translations, …)
     * are preserved so custom fields like LinkBlock.url and ImageBlock.image
     * are never lost.  Only the structural meta-keys (id, type) are separated
     * from the content fields.
     *
     * Output is always a clean flat structure that the API transformer reads
     * directly from top-level keys.
     */
    public function normalizeBlocks(array $blocks): array
    {
        $languages = array_keys(config('cms.supported_languages'));

        return collect($blocks)
            ->filter(fn (array $block) => filled($block['type'] ?? null))
            ->map(function (array $block) use ($languages): array {

                // Filament Builder wraps form-field values inside a 'data' key.
                // Fall back to the block itself when 'data' is absent (already flat).
                $fieldData = (isset($block['data']) && is_array($block['data']))
                    ? $block['data']
                    : $block;

                // Remove structural meta-keys that should not be treated as form fields.
                unset($fieldData['type'], $fieldData['id'], $fieldData['data']);

                // Merge: start with all form fields (url, image, etc.) then enforce
                // the required structural/default keys on top.
                $normalized = array_merge($fieldData, [
                    'id'           => $block['id'] ?? (string) Str::uuid(),
                    'type'         => $block['type'],
                    'is_active'    => $fieldData['is_active'] ?? true,
                    'props'        => $fieldData['props'] ?? [],
                    'translations' => $fieldData['translations'] ?? [],
                ]);

                // Guarantee every configured language has at least an empty array
                foreach ($languages as $lang) {
                    $normalized['translations'][$lang] ??= [];
                }

                return $normalized;
            })
            ->values()
            ->all();
    }
}
