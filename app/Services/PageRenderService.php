<?php

namespace App\Services;

use App\Exceptions\PageNotFoundException;
use App\Exceptions\PageTranslationMissingException;
use App\Models\SamplePage;
use Illuminate\Contracts\View\View;

class PageRenderService
{

    public function render(string $pageKey, ?string $lang = null): View
    {
        $lang ??= app()->getLocale();
        $fallback = (string) config('app.fallback_locale');

        // 1) جلب الصفحة + تحميل الترجمات eager loading
        $page = SamplePage::query()
            ->byKey($pageKey)
            ->with('translations')
            ->first();

        if (! $page) {
                throw new \Exception('Page not found: ' . $pageKey);
        }


        $previousLocale = app()->getLocale();
        app()->setLocale($lang);

        try {
            $title = $page->title;
            $content = $page->content;
            $meta = $page->meta;


            if ($title === null && $content === null && $meta === null) {
                throw new \Exception('Page translation missing: ' . $pageKey);

            }

            $metaPayload = [
                'title' => is_array($meta) ? ($meta['title'] ?? null) : null,
                'description' => is_array($meta) ? ($meta['description'] ?? null) : null,
            ];

            return view($page->template, [
                'page' => $page,
                'title' => $title,
                'content' => $content,
                'meta' => $metaPayload,
            ]);
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
