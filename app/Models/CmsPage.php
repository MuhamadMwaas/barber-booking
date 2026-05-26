<?php

namespace App\Models;

use App\Services\Cms\CmsBlockNormalizer;
use App\Services\Cms\CmsPageCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CmsPage extends Model
{
    use HasFactory;

    protected $table = 'cms_pages';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'blocks',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'blocks'    => 'array',
    ];

    /* ==========================
     | Hooks
     |========================== */

    protected static function booted(): void
    {
        static::saving(function (CmsPage $page): void {
            if (blank($page->slug)) {
                $page->slug = Str::slug($page->name);
            }

            $page->blocks = app(CmsBlockNormalizer::class)
                ->normalizeBlocks($page->blocks ?? []);
        });

        static::saved(function (CmsPage $page): void {
            app(CmsPageCacheService::class)->forgetPage($page->slug);
        });

        static::deleted(function (CmsPage $page): void {
            app(CmsPageCacheService::class)->forgetPage($page->slug);
        });
    }

    /* ==========================
     | Scopes
     |========================== */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
