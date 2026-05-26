<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Slider extends Model
{
    use HasFactory;

    protected $table = 'sliders';

    protected $fillable = [
        'key',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    /**
     * كل الشرائح (بدون فلترة)
     */
    public function items(): HasMany
    {
        return $this->hasMany(SliderItem::class)->orderBy('sort_order');
    }

    /**
     * الشرائح النشطة والمجدولة فقط — للعرض في التطبيق
     *   is_active = true
     *   AND (starts_at IS NULL OR starts_at <= NOW())
     *   AND (ends_at   IS NULL OR ends_at   >= NOW())
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(SliderItem::class)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                      ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Cache Helpers ──────────────────────────────────────────────────────────

    /**
     * جلب سلايدر بمفتاحه مع شرائحه النشطة مع الكاش (5 دقائق)
     * يُستخدم في SliderController
     */
    public static function getCachedByKey(string $key): ?self
    {
        return Cache::remember(
            "slider:{$key}",
            now()->addMinutes(5),
            fn() => self::with([
                'activeItems.translations.language',
                'activeItems.image',
            ])
            ->where('key', $key)
            ->where('is_active', true)
            ->first()
        );
    }

    /**
     * مسح كاش سلايدر محدد — يُستدعى عند التعديل من لوحة الإدارة
     */
    public static function clearCache(string $key): void
    {
        Cache::forget("slider:{$key}");
    }

    // ── Static Helpers ─────────────────────────────────────────────────────────

    /**
     * جلب سلايدر الصفحة الرئيسية مباشرة
     */
    public static function home(): ?self
    {
        return self::getCachedByKey('home');
    }
}
