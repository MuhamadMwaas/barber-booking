<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AboutUsPageResource;
use App\Models\AboutUsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AboutUsPageController extends Controller
{
    /**
     * Return the active About Us page as a structured JSON response.
     *
     * Supports optional ?locale=de|ar|en query param — when provided, each
     * multilingual field is resolved to that single locale instead of returning
     * the full translations object.
     *
     * Cache: model-level 24-hour cache via AboutUsPage::getCached().
     * Cache-Control header: 1-hour browser/CDN cache, refreshed on each deploy.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $page = AboutUsPage::getCached();

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'About Us page is not available.',
                ], 404);
            }

            $resource = new AboutUsPageResource($page);

            $locale = $this->resolveLocale($request);
            $data   = $resource->toArray($request);

            if ($locale) {
                $data = $this->localizeFields($data, $locale);
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
                'message' => 'About Us page retrieved successfully.',
            ], 200, [
                'Cache-Control' => 'public, max-age=3600, stale-while-revalidate=86400',
                'Vary'          => 'Accept-Language',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve About Us page.',
                'error'   => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveLocale(Request $request): ?string
    {
        $requested = $request->query('locale');

        if (!$requested) {
            return null;
        }

        $supported = ['de', 'ar', 'en'];

        return in_array($requested, $supported, true) ? $requested : null;
    }

    /**
     * Recursively resolve multilingual arrays (keyed de/ar/en) to a single
     * locale value, falling back to 'de' → 'en' → first available value.
     */
    private function localizeFields(mixed $value, string $locale): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Detect a translations object: has at least one of the supported locales
        $localeKeys = ['de', 'ar', 'en'];
        $isTranslation = !empty(array_intersect($localeKeys, array_keys($value)));

        // Guard: only resolve if ALL keys are locale keys (avoid resolving
        // mixed arrays like {value: ..., label: ..., icon: ...})
        $nonLocaleKeys = array_diff(array_keys($value), $localeKeys);

        if ($isTranslation && empty($nonLocaleKeys)) {
            return $value[$locale]
                ?? $value['de']
                ?? $value['en']
                ?? reset($value);
        }

        // Recurse into every element
        return array_map(fn($item) => $this->localizeFields($item, $locale), $value);
    }
}
