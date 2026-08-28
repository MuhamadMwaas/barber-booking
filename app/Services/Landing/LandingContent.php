<?php

namespace App\Services\Landing;

use App\Models\LandingSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side view model for the public landing page.
 *
 * The Blade views never touch Eloquent or the raw JSON payload; they ask this
 * object for already-resolved values by dot path:
 *
 *     $landing->text('hero.title_gold')     // translated string
 *     $landing->image('hero.image')         // public URL or null
 *     $landing->items('services.items')     // active repeater rows
 *     $landing->enabled('gallery')          // section toggle
 *
 * All eleven section rows are loaded once per request and cached, so a page
 * render costs a single query on a cold cache and none on a warm one.
 */
class LandingContent
{
    /** @var Collection<string, LandingSection>|null */
    private ?Collection $sections = null;

    private ?string $locale = null;

    /** Cache lifetime in seconds; writes from the admin panel bust it anyway. */
    private const TTL = 86400;

    public function __construct(?string $locale = null)
    {
        $this->locale = $locale;
    }

    /**
     * Render the page in an explicit locale instead of the app locale.
     * Used by the preview action in the admin panel.
     */
    public function forLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function locale(): string
    {
        return $this->locale ?? app()->getLocale();
    }

    /* ==========================================================
     | Loading
     |========================================================== */

    /** @return Collection<string, LandingSection> */
    public function sections(): Collection
    {
        return $this->sections ??= Cache::remember(
            LandingSection::CACHE_KEY,
            self::TTL,
            // toBase() matters: Eloquent\Collection overrides only() to filter by
            // PRIMARY KEY, ignoring the keyBy() keys entirely — so orderedBodyKeys()
            // would silently return nothing. A base collection keys by 'key' as
            // written.
            fn () => LandingSection::query()->get()->keyBy('key')->toBase(),
        );
    }

    public function section(string $key): ?LandingSection
    {
        return $this->sections()->get($key);
    }

    /** Is this section toggled on? Missing sections count as off. */
    public function enabled(string $key): bool
    {
        return (bool) $this->section($key)?->is_active;
    }

    /**
     * Body sections in admin-defined order, filtered to the enabled ones.
     *
     * @return array<int, string>
     */
    public function orderedBodyKeys(): array
    {
        return $this->sections()
            ->only(LandingSection::BODY_KEYS)
            ->filter->is_active
            ->sortBy('sort_order')
            ->keys()
            ->all();
    }

    /* ==========================================================
     | Value access
     |========================================================== */

    /**
     * Raw value at "<section key>.<dot path inside content>".
     * Returns $default when the section or the path is missing.
     */
    public function raw(string $path, mixed $default = null): mixed
    {
        [$key, $rest] = array_pad(explode('.', $path, 2), 2, null);

        $content = $this->section($key)?->content ?? [];

        if ($rest === null) {
            return $content;
        }

        return data_get($content, $rest, $default);
    }

    /** Translated string at the given path. */
    public function text(string $path, string $default = ''): string
    {
        $value = $this->raw($path);

        if ($value === null) {
            return $default;
        }

        $resolved = LandingSection::translate($value, $this->locale());

        return $resolved !== '' ? $resolved : $default;
    }

    /** Translate a value already pulled out of a repeater row. */
    public function t(mixed $value, string $default = ''): string
    {
        $resolved = LandingSection::translate($value, $this->locale());

        return $resolved !== '' ? $resolved : $default;
    }

    /** Public URL for the image stored at the given path, or null. */
    public function image(string $path): ?string
    {
        return LandingSection::imageUrl($this->raw($path));
    }

    /** Public URL for an image value already pulled out of a repeater row. */
    public function img(mixed $value): ?string
    {
        return LandingSection::imageUrl($value);
    }

    public function bool(string $path, bool $default = false): bool
    {
        $value = $this->raw($path);

        return $value === null ? $default : (bool) $value;
    }

    /**
     * Repeater rows at the given path, with rows whose `is_active` is explicitly
     * false removed. Rows without the flag are kept — most repeaters in the
     * editor do not expose one, and absence must not mean "hidden".
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(string $path): array
    {
        $items = $this->raw($path, []);

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            $items,
            fn ($item) => is_array($item) && ($item['is_active'] ?? true),
        ));
    }

    /**
     * Repeater rows at the given path with dead in-page anchors removed.
     *
     * A link to "#gallery" is only navigable while the gallery section is
     * actually rendered; once an admin switches that section off, the link would
     * scroll nowhere. Non-anchor links (other pages, external URLs) are always
     * kept — this only knows about sections of this page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function links(string $path): array
    {
        $live = array_map(
            fn (string $key) => '#' . $key,
            $this->orderedBodyKeys(),
        );

        return array_values(array_filter(
            $this->items($path),
            function (array $link) use ($live): bool {
                $url = $link['url'] ?? '';

                return ! is_string($url)
                    || ! str_starts_with($url, '#')
                    || in_array($url, $live, true);
            },
        ));
    }

    /**
     * Resolve a stored link target to a URL that works on the current page.
     *
     * In-page anchors ("#services") are only meaningful on the landing page.
     * The navbar and footer are shared with /app and the legal pages, where the
     * same anchor would scroll nowhere — so off the landing page they are
     * rewritten to point back at it ("/#services"). Everything else is passed
     * through untouched.
     */
    public function linkUrl(?string $url): string
    {
        if (! is_string($url) || blank($url)) {
            return '#';
        }

        if (! str_starts_with($url, '#')) {
            return $url;
        }

        return request()->routeIs('landing')
            ? $url
            : route('landing') . $url;
    }

    /* ==========================================================
     | Direction
     |========================================================== */

    /** Writing direction for the active locale. */
    public function direction(): string
    {
        return config("cms.supported_languages.{$this->locale()}.direction", 'ltr');
    }

    public function isRtl(): bool
    {
        return $this->direction() === 'rtl';
    }
}
