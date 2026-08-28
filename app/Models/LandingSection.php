<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * One editable section of the public landing page.
 *
 * @property string $key
 * @property bool   $is_active
 * @property int    $sort_order
 * @property array  $content
 */
class LandingSection extends Model
{
    protected $table = 'landing_sections';

    protected $fillable = [
        'key',
        'is_active',
        'sort_order',
        'content',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'content'    => 'array',
    ];

    /* ==========================================================
     | Section keys
     |
     | The single source of truth for which sections exist. The
     | Filament editor builds one tab per key, the seeder creates
     | one row per key, and the Blade views resolve by key.
     |========================================================== */

    public const BRAND    = 'brand';      // logo, wordmark, tagline
    public const SEO      = 'seo';        // <title>, meta description, OG image
    public const NAVBAR   = 'navbar';     // top navigation + its CTA
    public const HERO     = 'hero';       // headline, photo, badges
    public const SERVICES = 'services';   // the 4 service cards
    public const FEATURES = 'features';   // "Mehr als nur ein Friseurbesuch"
    public const GALLERY  = 'gallery';    // "Unsere Arbeiten"
    public const CTA      = 'cta';        // "Bereit für Ihren neuen Look?"
    public const INFO     = 'info';       // address / phone / hours / social
    public const FOOTER   = 'footer';     // footer columns + newsletter
    public const APP_PAGE = 'app_page';   // the standalone /app download page

    /**
     * Body sections in their default top-to-bottom order. `sort_order` only
     * applies to these — the structural rows below are positional by nature.
     */
    public const BODY_KEYS = [
        self::HERO,
        self::SERVICES,
        self::FEATURES,
        self::GALLERY,
        self::CTA,
        self::INFO,
    ];

    /** Every key the editor knows about, in tab order. */
    public const KEYS = [
        self::BRAND,
        self::SEO,
        self::NAVBAR,
        self::HERO,
        self::SERVICES,
        self::FEATURES,
        self::GALLERY,
        self::CTA,
        self::INFO,
        self::FOOTER,
        self::APP_PAGE,
    ];

    /* ==========================================================
     | Cache
     |========================================================== */

    public const CACHE_KEY = 'landing:sections';

    protected static function booted(): void
    {
        // Any write from the admin panel invalidates the whole page payload.
        // The landing page is always read as one unit, so a single key is both
        // sufficient and cheaper than tracking per-section entries.
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /* ==========================================================
     | Scopes
     |========================================================== */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /* ==========================================================
     | Translation helpers
     |========================================================== */

    /**
     * Resolve a per-locale map to a single string.
     *
     * Accepts either a translation map (["de" => "…", "en" => "…"]) or a plain
     * scalar (for values that were never translatable, like a phone number),
     * so callers never have to know which kind of field they are holding.
     *
     * Fallback order: requested locale → app fallback locale → German (the
     * salon's own language, and the language the seeded content is written in)
     * → the first non-empty value present.
     */
    public static function translate(mixed $value, ?string $locale = null): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        $locale ??= app()->getLocale();

        $candidates = [
            $locale,
            config('app.fallback_locale'),
            'de',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filled($value[$candidate] ?? null)) {
                return (string) $value[$candidate];
            }
        }

        foreach ($value as $entry) {
            if (is_string($entry) && filled(trim($entry))) {
                return $entry;
            }
        }

        return '';
    }

    /**
     * Public URL for a stored upload path.
     *
     * Uploads coming from Filament are relative paths on the `public` disk;
     * seeded defaults are already absolute ("/image/landing/…") so the page has
     * something to show before anyone opens the admin panel. Full URLs are
     * passed through untouched.
     */
    public static function imageUrl(mixed $path): ?string
    {
        if (! is_string($path) || blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
