<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiLocaleMiddlewareTest extends TestCase
{
    public function test_query_language_takes_priority_over_accept_language(): void
    {
        Route::middleware('api')->get('/api/locale-probe-query', fn () => response()->json([
            'locale' => app()->getLocale(),
        ]));

        $this->getJson('/api/locale-probe-query?lang=ar', [
            'Accept-Language' => 'de-DE,de;q=0.9',
        ])->assertOk()->assertJsonPath('locale', 'ar');
    }

    public function test_accept_language_sets_the_api_locale(): void
    {
        Route::middleware('api')->get('/api/locale-probe-header', fn () => response()->json([
            'locale' => app()->getLocale(),
        ]));

        $this->getJson('/api/locale-probe-header', [
            'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
        ])->assertOk()->assertJsonPath('locale', 'de');
    }

    public function test_unsupported_language_falls_back_to_the_cms_default(): void
    {
        Route::middleware('api')->get('/api/locale-probe-fallback', fn () => response()->json([
            'locale' => app()->getLocale(),
        ]));

        $this->getJson('/api/locale-probe-fallback?lang=fr', [
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])
            ->assertOk()
            ->assertJsonPath('locale', config('cms.default_language'));
    }
}
