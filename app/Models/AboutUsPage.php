<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

class AboutUsPage extends Model {

    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'hero_description',
        'contact_phone',
        'contact_address',
        'contact_email',
        'opening_hours',
        'social_title',
        'social_links',
        'legal_links',
        'features',
        'newsletter_title',
        'newsletter_description',
        'newsletter_enabled',
        'is_active',
    ];

    protected $casts = [
        'hero_title' => 'array',
        'hero_subtitle' => 'array',
        'hero_description' => 'array',
        'contact_phone' => 'array',
        'contact_address' => 'array',
        'opening_hours' => 'array',
        'social_title' => 'array',
        'social_links' => 'array',
        'legal_links' => 'array',
        'features' => 'array',
        'newsletter_title' => 'array',
        'newsletter_description' => 'array',
        'newsletter_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ── Relations ───────────────────────────────
    public function heroImages(): MorphMany {
        return $this->morphMany(File::class, 'fileable', 'instance_type', 'instance_id')
            ->where('key', 'hero_image')
            ->orderBy('sort_order');
    }

    public function teamMembers(): HasMany {
        return $this->hasMany(AboutUsTeamMember::class)->orderBy('sort_order');
    }

    public function activeTeamMembers(): HasMany {
        return $this->hasMany(AboutUsTeamMember::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    // ── Cache Helpers ───────────────────────────
    public static function getCached(): ?self {
        return Cache::remember(
            'about_us',
            now()->addHours(24),
            fn() => self::with(['heroImages', 'activeTeamMembers'])
                ->where('is_active', true)
                ->first()
        );
    }

    public static function clearCache(): void {
        Cache::forget('about_us');
    }

    // ── Translation Helpers ─────────────────────
    public function getTranslation(string $field, string $locale = null): mixed {
        $locale ??= app()->getLocale();
        $value = $this->getAttribute($field);

        if (is_array($value)) {
            return $value[$locale] ?? $value['de'] ?? $value['en'] ?? reset($value);
        }

        return $value;
    }
}
