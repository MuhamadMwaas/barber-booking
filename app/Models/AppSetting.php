<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Definition of a single user-facing application option (the catalog row).
 *
 * Holds everything that is the SAME for every user: the translated label, the
 * value type, the default value, and the validation rules (stored as data). The
 * actual per-user value lives in {@see UserSetting}.
 */
class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'label_translations',
        'description_translations',
        'type',
        'default_value',
        'validation',
        'group',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'label_translations' => 'array',
        'description_translations' => 'array',
        'default_value' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';
    const TYPE_DECIMAL = 'decimal';

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Translated label for the given locale, with graceful fallback to en then key.
     */
    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $translations = $this->label_translations ?? [];

        return $translations[$locale]
            ?? $translations['en']
            ?? $this->key;
    }

    public function description(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $translations = $this->description_translations ?? [];

        return $translations[$locale] ?? $translations['en'] ?? null;
    }
}
