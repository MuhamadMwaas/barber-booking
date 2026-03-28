<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
}
