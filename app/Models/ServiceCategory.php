<?php

namespace App\Models;

use App\Models\Translation\ServiceCategoryTranslation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasFactory;


    protected $table = 'service_categories';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // relationships

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    // scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    public function scopeSearch($query, string $term)
    {
        return $query->where('name', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%");
    }

    public function getServicesCountAttribute(): int
    {
        return $this->services()->count();
    }

    // Translation methods

    public function translate(string $locale, array $attributes): ServiceCategoryTranslation
    {
        $language = Language::where('code', $locale)->firstOrFail();

        return $this->translations()->updateOrCreate(
            ['service_category_id' => $this->id, 'language_id' => $language->id],
            $attributes
        );
    }

        public function translations(): HasMany
    {
        return $this->hasMany(ServiceCategoryTranslation::class);
    }
    public function translation(string $locale): ?ServiceCategoryTranslation
    {
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            $language= Language::where('is_default', true)->first();
        }

        return $this->translations()->where('language_id', $language->id)->first();
    }

    public function getNameIn(string $locale): string
    {
        return $this->translation($locale)?->name ?? $this->name;
    }

    public function getDescriptionIn(string $locale): ?string
    {
        return $this->translation($locale)?->description ?? $this->description;
    }
}
