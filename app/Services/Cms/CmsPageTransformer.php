<?php

namespace App\Services\Cms;

use App\Models\CmsPage;

class CmsPageTransformer
{
    public function __construct(
        protected CmsBlockRegistry $blockRegistry,
    ) {}

    /**
     * Transforms a CmsPage into the API response array for the given language.
     */
    public function transform(CmsPage $page, string $language): array
    {
        $fallback = config('cms.default_language', 'ar');
        $blocks   = $page->blocks ?? [];

        $transformed = collect($blocks)
            ->filter(fn (array $block) => ($block['is_active'] ?? true) === true)
            ->map(function (array $block) use ($language, $fallback): ?array {
                $type = $block['type'] ?? null;

                if (! is_string($type)) {
                    return null;
                }

                $transformer = $this->blockRegistry->transformerFor($type);

                return $transformer?->transform($block, $language, $fallback);
            })
            ->filter()
            ->values()
            ->all();

        return [
            'slug'              => $page->slug,
            'language'          => $language,
            'fallback_language' => $fallback,
            'direction'         => config("cms.supported_languages.{$language}.direction", 'ltr'),
            'blocks'            => $transformed,
        ];
    }
}
