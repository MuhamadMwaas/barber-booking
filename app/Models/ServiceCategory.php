<?php

namespace App\Models;

use App\Models\Translation\ServiceCategoryTranslation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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


    protected $appends = [
        // 'image_url',
        'translated_name',

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

    public function getTranslatedNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->getNameIn($locale);
    }

      public function image(): MorphOne
    {
        return $this->morphOne(File::class, 'fileable', 'instance_type', 'instance_id')->where('type', 'service_category_image');
    }

        public function updateImage(UploadedFile $image): File
    {
        if ($this->image) {
            $this->image->delete();
        }
            $folder = 'images';
            $type = 'service_category_image';


        $name = str_replace(' ', '_', trim($this->name));
        $extension = $image->getClientOriginalExtension();
        $dir = "service-categories/{$folder}/{$this->id}";
        $fileName = "{$name}_{$this->id}.{$extension}";
        $path = "{$dir}/{$fileName}";

        Storage::disk('public')->makeDirectory($dir);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $image->storeAs($dir, $fileName, 'public');

        return $this->image()->create([
            'name' => $name . '_' . $this->id,
            'path' => $path,
            'disk' => 'public',
            'type' => $type,
            'extension' => $extension,
            'group' => 'service-categories',
            'key' => 'image',
        ]);
    }
}
