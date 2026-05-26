<?php

namespace App\Services\Cms;

use Illuminate\Support\Facades\Cache;

class CmsPageCacheService
{
    public function remember(string $slug, string $language, callable $callback): array
    {
        $ttl = (int) config('cms.cache.ttl', 86400);

        return Cache::remember(
            $this->key($slug, $language),
            $ttl,
            $callback,
        );
    }

    public function key(string $slug, string $language): string
    {
        $prefix = config('cms.cache.prefix', 'cms_page');

        return "{$prefix}:{$slug}:{$language}";
    }

    public function forget(string $slug, string $language): void
    {
        Cache::forget($this->key($slug, $language));
    }

    /**
     * Clears cached data for all supported languages for the given page slug.
     */
    public function forgetPage(string $slug): void
    {
        foreach (array_keys(config('cms.supported_languages')) as $language) {
            $this->forget($slug, $language);
        }
    }
}
