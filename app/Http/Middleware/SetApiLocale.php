<?php

namespace App\Http\Middleware;

use App\Services\Cms\CmsLanguageResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function __construct(private CmsLanguageResolver $languageResolver) {}

    /**
     * Set one locale for the whole lifetime of the API request.
     *
     * Priority: ?lang=ar, legacy ?locale=ar, Accept-Language, CMS default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->languageResolver->resolve($request);

        app()->setLocale($locale);
        $request->attributes->set('locale', $locale);

        return $next($request);
    }
}
