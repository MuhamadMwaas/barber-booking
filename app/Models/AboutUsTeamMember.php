<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AboutUsTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'about_us_page_id',
        'name',
        'position',
        'description',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'position' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
    ];

    public function page(): BelongsTo {
        return $this->belongsTo(AboutUsPage::class, 'about_us_page_id');
    }

    public function getName(string $locale = null): string {
        $locale ??= app()->getLocale();
        return $this->name[$locale] ?? $this->name['de'] ?? $this->name['en'] ?? '';
    }

    public function getPosition(string $locale = null): string {
        $locale ??= app()->getLocale();
        return $this->position[$locale] ?? $this->position['de'] ?? $this->position['en'] ?? '';
    }

    public function getDescription(string $locale = null): string {
        $locale ??= app()->getLocale();
        return $this->description[$locale] ?? $this->description['de'] ?? $this->description['en'] ?? '';
    }
}
