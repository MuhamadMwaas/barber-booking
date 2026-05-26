<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    /**
     * GET /api/sliders/{key}
     *
     * إرجاع سلايدر بمفتاحه مع شرائحه النشطة والمرئية حالياً.
     *
     * Query Parameters:
     *   ?locale=ar|en|de  — تحديد اللغة (default: en)
     *
     * Caching: 5 دقائق على مستوى Model (Slider::getCachedByKey)
     * Cache-Control: 5 دقائق للبراوزر/CDN
     *
     * Response Example:
     * {
     *   "success": true,
     *   "data": {
     *     "key": "home",
     *     "items": [
     *       {
     *         "id": 1,
     *         "sort_order": 1,
     *         "title": "مرحباً في صالوننا",
     *         "subtitle": "أفضل خدمات الحلاقة",
     *         "description": "نقدم لك تجربة فريدة",
     *         "image_url": "https://example.com/storage/sliders/1/slide_1.jpg",
     *         "starts_at": null,
     *         "ends_at": null,
     *         "is_permanent": true
     *       }
     *     ]
     *   }
     * }
     */
    public function show(Request $request, string $key): JsonResponse
    {
        try {
            $locale = $this->resolveLocale($request);

            $slider = Slider::getCachedByKey($key);

            if (! $slider) {
                return response()->json([
                    'success' => false,
                    'message' => "Slider '{$key}' not found or inactive.",
                ], 404);
            }

            $items = $slider->activeItems
                ->map(fn (mixed $item) => $this->formatItem($item, $locale))
                ->values();

            return response()->json([
                'success' => true,
                'data'    => [
                    'key'   => $slider->key,
                    'items' => $items,
                ],
                'message' => 'Slider retrieved successfully.',
            ], 200, [
                'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
                'Vary'          => 'Accept-Language',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve slider.',
                'error'   => app()->isProduction() ? null : $e->getMessage(),
            ], 500);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * تحديد اللغة من query param أو Accept-Language header
     * مع fallback إلى 'en'
     */
    private function resolveLocale(Request $request): string
    {
        $supported = ['ar', 'en', 'de'];

        // 1. Query param: ?locale=ar
        $param = $request->query('locale');
        if ($param && in_array($param, $supported, true)) {
            return $param;
        }

        // 2. Accept-Language header (أول قيمة مدعومة)
        $header = $request->getPreferredLanguage($supported);
        if ($header) {
            return $header;
        }

        return 'en';
    }

    /**
     * تحويل SliderItem إلى مصفوفة API مع حقن الترجمة الصحيحة
     */
    private function formatItem(mixed $item, string $locale): array
    {
        $translation = $item->getTranslation($locale);

        return [
            'id'           => $item->id,
            'sort_order'   => $item->sort_order,
            'title'        => $translation?->title,
            'subtitle'     => $translation?->subtitle,
            'description'  => $translation?->description,
            'image_url'    => $item->image_url,
            'starts_at'    => $item->starts_at?->toIso8601String(),
            'ends_at'      => $item->ends_at?->toIso8601String(),
            'is_permanent' => $item->isPermanent(),
        ];
    }
}
