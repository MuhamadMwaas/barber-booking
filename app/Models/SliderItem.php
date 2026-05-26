<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SliderItem extends Model
{
    use HasFactory;

    protected $table = 'slider_items';

    protected $fillable = [
        'slider_id',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function slider(): BelongsTo
    {
        return $this->belongsTo(Slider::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(SliderItemTranslation::class);
    }

    /**
     * الصورة — MorphOne عبر نظام File الموجود في المشروع
     * يستخدم نفس نمط Service::image()
     */
    public function image(): MorphOne
    {
        return $this->morphOne(File::class, 'fileable', 'instance_type', 'instance_id')
                    ->where('type', 'slider_image');
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getImageUrlAttribute(): ?string
    {
        return $this->image?->urlFile();
    }

    // ── Translation Helpers ────────────────────────────────────────────────────

    /**
     * جلب ترجمة شريحة بحسب اللغة
     * يرجع الـ default language إن لم توجد اللغة المطلوبة
     */
    public function translation(string $locale): ?SliderItemTranslation
    {
        $language = Language::where('code', $locale)->first()
            ?? Language::where('is_default', true)->first();

        if (! $language) {
            return null;
        }

        return $this->translations->firstWhere('language_id', $language->id);
    }

    /**
     * جلب ترجمة مع fallback سلس:
     *   1. اللغة المطلوبة
     *   2. اللغة الافتراضية
     *   3. أي ترجمة متاحة
     */
    public function getTranslation(string $locale): ?SliderItemTranslation
    {
        // 1. اللغة المطلوبة
        $lang = Language::where('code', $locale)->first();
        if ($lang) {
            $translation = $this->translations->firstWhere('language_id', $lang->id);
            if ($translation) {
                return $translation;
            }
        }

        // 2. اللغة الافتراضية
        $default = Language::where('is_default', true)->first();
        if ($default && $default->id !== $lang?->id) {
            $translation = $this->translations->firstWhere('language_id', $default->id);
            if ($translation) {
                return $translation;
            }
        }

        // 3. أي ترجمة متاحة
        return $this->translations->first();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /**
     * Scope: الشرائح النشطة مع احترام نافذة النشر
     */
    public function scopePublished($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    // ── Image Management ───────────────────────────────────────────────────────

    /**
     * رفع أو استبدال صورة الشريحة
     * يتبع نفس نمط Service::updateProfileImage()
     */
    public function uploadImage(UploadedFile $file): File
    {
        // حذف الصورة القديمة إن وجدت
        if ($this->image) {
            $this->image->delete();
        }

        $extension = $file->getClientOriginalExtension();
        $dir       = "sliders/{$this->slider_id}/{$this->id}";
        $fileName  = "slide_{$this->id}.{$extension}";
        $path      = "{$dir}/{$fileName}";

        Storage::disk('public')->makeDirectory($dir);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $file->storeAs($dir, $fileName, 'public');

        return $this->image()->create([
            'name'      => "slide_{$this->id}",
            'path'      => $path,
            'disk'      => 'public',
            'type'      => 'slider_image',
            'extension' => $extension,
            'group'     => 'slider',
            'key'       => 'image',
        ]);
    }

    // ── Computed Helpers ───────────────────────────────────────────────────────

    /**
     * هل الشريحة دائمة (بدون جدولة زمنية)؟
     */
    public function isPermanent(): bool
    {
        return is_null($this->starts_at) && is_null($this->ends_at);
    }

    /**
     * هل الشريحة مرئية الآن؟
     */
    public function isPublishedNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
