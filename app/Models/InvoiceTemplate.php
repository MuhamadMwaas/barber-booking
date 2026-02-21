<?php

namespace App\Models;

use App\Enum\TemplateSectionType;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'is_default',
        'language',
        'paper_size',
        'paper_width',
        'font_family',
        'font_size',
        'global_styles',
        'company_info',
        'metadata',
        'static_body_html',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'global_styles' => 'array',
        'company_info' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'global_styles' => '{}',
        'company_info' => '{}',
        'metadata' => '{}',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get all lines for this template
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TemplateLine::class, 'template_id')
            ->orderBy('section')
            ->orderBy('order');
    }

    /**
     * Get header lines
     */
    public function headerLines(): HasMany
    {
        return $this->hasMany(TemplateLine::class, 'template_id')
            ->where('section', TemplateSectionType::Header->value)
            ->orderBy('order');
    }

    /**
     * Get body lines
     */
    public function bodyLines(): HasMany
    {
        return $this->hasMany(TemplateLine::class, 'template_id')
            ->where('section',  TemplateSectionType::Body->value)
            ->orderBy('order');
    }

    /**
     * Get footer lines
     */
    public function footerLines(): HasMany
    {
        return $this->hasMany(TemplateLine::class, 'template_id')
            ->where('section',  TemplateSectionType::Footer->value)
            ->orderBy('order');
    }

    /**
     * Get invoices using this template
     */
    // public function invoices(): HasMany
    // {
    //     return $this->hasMany(Invoice::class, 'template_id');
    // }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get only active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default template
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get templates by language
     */
    public function scopeLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the default template
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Set this template as default
     */
    public function setAsDefault(): void
    {
        // Remove default from all other templates
        self::where('id', '!=', $this->id)->update(['is_default' => false]);

        // Set this template as default
        $this->update(['is_default' => true, 'is_active' => true]);
    }

    /**
     * Get a global style value
     */
    public function getGlobalStyle(string $key, $default = null)
    {
        return data_get($this->global_styles, $key, $default);
    }

    /**
     * Get a company info value
     */
    public function getCompanyInfo(string $key, $default = null)
    {
        return data_get($this->company_info, $key, $default);
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Check if template has a specific line type
     */
    public function hasLineType(string $type): bool
    {
        return $this->lines()->where('type', $type)->exists();
    }

    /**
     * Get line by type (for unique line types)
     */
    public function getLineByType(string $type): ?TemplateLine
    {
        return $this->lines()->where('type', $type)->first();
    }

    /**
     * Duplicate this template
     */
    public function duplicate(?string $newName = null): self
    {
        $newTemplate = $this->replicate();
        $newTemplate->name = $newName ?? $this->name . ' (Copy)';
        $newTemplate->is_default = false;
        $newTemplate->save();

        // Duplicate all lines
        foreach ($this->lines as $line) {
            $newLine = $line->replicate();
            $newLine->template_id = $newTemplate->id;
            $newLine->save();
        }

        return $newTemplate;
    }

    /**
     * Get paper width in pixels (approximate conversion)
     */
    public function getPaperWidthInPixels(): int
    {
        // Approximate conversion: 1mm = 3.78px at 96 DPI
        return (int) ($this->paper_width * 3.78);
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Method
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        // When creating a new template, set default values
        static::creating(function ($template) {
            if (empty($template->global_styles)) {
                $template->global_styles = [
                    'primary_color' => '#000000',
                    'secondary_color' => '#666666',
                    'line_height' => 1.2,
                    'padding' => 5,
                    'border_color' => '#cccccc',
                ];
            }

            if (empty($template->company_info)) {
                $template->company_info = [
                    'name' => SettingsService::get('company_name')?? config('app.name'),
                    'address' => SettingsService::get('company_address')?? '',
                    'phone' => SettingsService::get('company_phone')?? '',
                    'email' => SettingsService::get('company_email')?? '',
                    'tax_number' => SettingsService::get('company_tax_number')?? '',
                    'logo_path' => null,
                ];
            }
        });

        // When setting a template as default, remove default from others
        static::saving(function ($template) {
            if ($template->is_default && $template->isDirty('is_default')) {
                self::where('id', '!=', $template->id)->update(['is_default' => false]);
            }
        });

        // When deleting a template, if it was default, set another as default
        static::deleting(function ($template) {
            if ($template->is_default) {
                $newDefault = self::where('id', '!=', $template->id)
                    ->where('is_active', true)
                    ->first();

                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }
        });
    }
}
