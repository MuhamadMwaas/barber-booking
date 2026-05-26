<?php

namespace App\Services\Cms;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CmsLanguageResolver
{
    /**
     * Resolves the requested language from:
     *   1. ?lang= query parameter
     *   2. Accept-Language HTTP header
     *   3. Fallback: default language from config
     */
    public function resolve(Request $request): string
    {
        $supported = array_keys(config('cms.supported_languages'));
        $default   = config('cms.default_language', 'ar');

        $queryLang = Str::lower( $request->query('lang'));
        if ($this->isSupported($queryLang, $supported)) {
            return $queryLang;
        }

        $headerLang = $this->extractFromAcceptLanguage($request->header('Accept-Language'));
        if ($this->isSupported($headerLang, $supported)) {
            return $headerLang;
        }

        return $default;
    }

    protected function isSupported(?string $language, array $supported): bool
    {
        return is_string($language) && in_array($language, $supported, true);
    }

    protected function extractFromAcceptLanguage(?string $header): ?string
    {
        if (blank($header)) {
            return null;
        }

        $first    = explode(',', $header)[0] ?? null;
        $language = strtolower(trim(explode(';', $first)[0] ?? ''));

        if (str_contains($language, '-')) {
            $language = Str::before($language, '-');
        }

        return filled($language) ? $language : null;
    }
}
