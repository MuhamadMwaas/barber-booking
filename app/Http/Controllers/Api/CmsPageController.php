<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\Cms\CmsLanguageResolver;
use App\Services\Cms\CmsPageCacheService;
use App\Services\Cms\CmsPageTransformer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPageController extends Controller
{
    /**
     * GET /api/pages/{slug}?lang=ar
     *
     * Returns a CMS page's content for the requested language.
     *
     * Query Parameters:
     *   ?lang=ar|en|de  — override language (default: resolved from Accept-Language / config)
     *
     * Caching: per slug + language, TTL from config('cms.cache.ttl')
     *
     * Response shape:
     * {
     *   "data": {
     *     "slug": "privacy-policy",
     *     "language": "ar",
     *     "fallback_language": "ar",
     *     "direction": "rtl",
     *     "blocks": [ ... ]
     *   }
     * }
     */
    public function show(
        string              $slug,
        Request             $request,
        CmsLanguageResolver $languageResolver,
        CmsPageCacheService $cacheService,
        CmsPageTransformer  $transformer,
    ): JsonResponse {
        $language = $languageResolver->resolve($request);

        try {
            $data = $cacheService->remember(
                $slug,
                $language,
                function () use ($slug, $language, $transformer): array {
                    $page = CmsPage::query()
                        ->active()
                        ->where('slug', $slug)
                        ->firstOrFail();

                    return $transformer->transform($page, $language);
                }
            );
        } catch (ModelNotFoundException) {
            return response()->json(['message' => __('cms.page_not_found')], 404);
        }

        return response()->json(['data' => $data]);
    }
}
