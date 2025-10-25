<?php

namespace App\Models;

use App\Models\Translation\ServiceTranslation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Service extends Model
{
    use HasFactory;


    protected $table = 'services';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'discount_price',
        'duration_minutes',
        'is_active',
        'sort_order',
        'image_url',
        'color_code',
        'icon_url',
        'is_featured',
    ];
 protected $appends = [
        'image_url',
    ];


    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
    ];

    // Relationships

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function providers()
    {
        return $this->belongsToMany(User::class, 'provider_service', 'service_id', 'provider_id')
            ->withPivot(['is_active', 'custom_price', 'custom_duration', 'notes'])
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(ServiceReview::class);
    }

    public function appointmentServices(): HasMany
    {
        return $this->hasMany(AppointmentService::class, 'service_id');
    }

    public function activeProviders()
    {
        return $this->providers()
            ->wherePivot('is_active', true)
            ->where('users.is_active', true);
    }


    // accessors

    public function getDisplayPriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }


    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    // scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }


    // Translation methods
    public function translations(): HasMany
    {
        return $this->hasMany(ServiceTranslation::class);
    }
    public function translate(string $locale, array $attributes): ServiceTranslation
    {
        $language = Language::where('code', $locale)->firstOrFail();

        return $this->translations()->updateOrCreate(
            ['service_id' => $this->id, 'language_id' => $language->id],
            $attributes
        );
    }

    public function translation(string $locale): ?ServiceTranslation
    {
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            $language = Language::where('is_default', true)->first();
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

    public function image(): MorphOne{
        return $this->morphOne(File::class, 'fileable', 'instance_type', 'instance_id')->where('type', 'service_image');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return $this->image->urlFile();
        }
        return null;
    }

    public function invoiceItems(): MorphMany
{
    return $this->morphMany(InvoiceItem::class, 'itemable');
}
}
