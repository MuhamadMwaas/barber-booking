<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SamplePage extends Model
{
    use HasFactory;

    protected $table = 'sample_pages';

    protected $fillable = [
        'page_key',
        'template',
        'is_published',
        'version',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'version'      => 'integer',
    ];

    /* ==========================
     | Relationships
     |========================== */

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class, 'page_id');
    }

    /* ==========================
     | Scopes
     |========================== */

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('page_key', $key);
    }

    /* ==========================
     | Translation Resolver
     |========================== */

    public function resolveTranslation(
        ?string $lang = null,
        ?string $fallback = null
    ): ?PageTranslation {
        $lang ??= app()->getLocale();
        $fallback ??= config('app.fallback_locale');

        // إذا كانت الترجمات محملة مسبقًا → لا استعلام إضافي
        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('lang', $lang)
                ?? $this->translations->firstWhere('lang', $fallback);
        }

        // وإلا → استعلام ذكي
        return $this->translations()
            ->whereIn('lang', [$lang, $fallback])
            ->orderByRaw("lang = ? desc", [$lang])
            ->first();
    }

    /* ==========================
     | Accessors (DX بدون كسر المعمارية)
     |========================== */

    protected function title(): Attribute
    {
        return Attribute::get(
            fn () => $this->resolveTranslation()?->title
        );
    }

    protected function content(): Attribute
    {
        return Attribute::get(
            fn () => $this->resolveTranslation()?->content
        );
    }

    protected function meta(): Attribute
    {
        return Attribute::get(
            fn () => $this->resolveTranslation()?->meta
        );
    }
}
