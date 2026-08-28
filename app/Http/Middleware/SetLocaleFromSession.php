<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides the active language for every `web` request.
 *
 * Resolution order, first match wins:
 *
 *   1. `locale` in the session   — an explicit choice the visitor already made
 *                                  (public language switcher, staff dashboard
 *                                  switcher, Filament language switcher).
 *   2. The signed-in user's `locale` column.
 *   3. The browser's `Accept-Language` header — so a first-time visitor from
 *      Germany lands on the German site without touching anything.
 *   4. The language flagged `is_default` in the `languages` table.
 *   5. `config('app.locale')` as a last resort.
 *
 * The result is written back to the session, so detection runs once per visitor
 * and every later request is a straight session read.
 */
class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $activeCodes = Language::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->pluck('code')
            ->filter()
            ->values();

        $locale = $request->session()->get('locale');

        if (! $locale) {
            $userLocale = $request->user()?->locale;

            if (is_string($userLocale) && $activeCodes->contains($userLocale)) {
                $locale = $userLocale;
            }
        }

        if (! $locale || ! $activeCodes->contains($locale)) {
            $locale = $this->localeFromBrowser($request, $activeCodes);
        }

        if (! $locale || ! $activeCodes->contains($locale)) {
            $locale = Language::query()
                ->where('is_active', true)
                ->where('is_default', true)
                ->value('code')
                ?? config('app.locale');
        }

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }

    /**
     * Best supported language for the visitor's `Accept-Language` header.
     *
     * Laravel's `getPreferredLanguage()` already parses and q-sorts the header,
     * but it matches on the full tag, so a browser announcing `de-DE` would not
     * match our `de`. We therefore ask it with both the plain codes and their
     * common regional forms, then map whatever comes back down to the base code.
     */
    private function localeFromBrowser(Request $request, Collection $activeCodes): ?string
    {
        if (blank($request->header('Accept-Language'))) {
            return null;
        }

        $candidates = $activeCodes
            ->flatMap(fn (string $code) => [$code, ...$this->regionalVariants($code)])
            ->all();

        $preferred = $request->getPreferredLanguage($candidates);

        if (! is_string($preferred)) {
            return null;
        }

        // "de_DE" / "de-DE" → "de"
        $base = strtolower(explode('_', str_replace('-', '_', $preferred))[0]);

        return $activeCodes->contains($base) ? $base : null;
    }

    /**
     * Regional tags a browser is likely to send for a given base language.
     * Only the languages this project ships are listed; anything else falls
     * through to base-code matching alone, which is still correct.
     *
     * @return array<int, string>
     */
    private function regionalVariants(string $code): array
    {
        return match ($code) {
            'de'    => ['de_DE', 'de_AT', 'de_CH'],
            'en'    => ['en_US', 'en_GB'],
            'ar'    => ['ar_SA', 'ar_EG', 'ar_AE', 'ar_SY'],
            default => [],
        };
    }
}
