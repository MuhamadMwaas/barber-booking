<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'section',
        'type',
        'order',
        'is_enabled',
        'properties',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'properties' => '{}',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the template this line belongs to
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'template_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get only enabled lines
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to get lines by section
     */
    public function scopeSection($query, string $section)
    {
        return $query->where('section', $section);
    }

    /**
     * Scope to get lines by type
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to order by position
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get a property value
     */
    public function getProperty(string $key, $default = null)
    {
        return data_get($this->properties, $key, $default);
    }

    /**
     * Set a property value
     */
    public function setProperty(string $key, $value): void
    {
        $properties = $this->properties;
        data_set($properties, $key, $value);
        $this->properties = $properties;
    }

    /**
     * Get line type configuration from config
     */
    public function getTypeConfig(): ?array
    {
        return config("invoice-line-types.types.{$this->type}");
    }

    /**
     * Get line type label
     */
    public function getTypeLabel(): string
    {
        return $this->getTypeConfig()['label'] ?? $this->type;
    }

    /**
     * Get blade view for this line type
     */
    public function getBladeView(): string
    {
        return $this->getTypeConfig()['blade_view'] ?? 'invoices.line-types.default';
    }

    /**
     * Check if this line type is unique (can only appear once)
     */
    public function isUnique(): bool
    {
        return $this->getTypeConfig()['unique'] ?? false;
    }

    /**
     * Get allowed sections for this line type
     */
    public function getAllowedSections(): array
    {
        return $this->getTypeConfig()['sections'] ?? ['header', 'body', 'footer'];
    }

    /**
     * Check if line can be in given section
     */
    public function canBeInSection(string $section): bool
    {
        return in_array($section, $this->getAllowedSections());
    }

    /**
     * Get default properties for this line type
     */
    public function getDefaultProperties(): array
    {
        return $this->getTypeConfig()['properties'] ?? [];
    }

    /**
     * Merge properties with defaults
     */
    public function getMergedProperties(): array
    {
        return array_merge(
            $this->getDefaultProperties(),
            $this->properties ?? []
        );
    }

    /**
     * Move line up in order
     */
    public function moveUp(): bool
    {
        $previousLine = self::where('template_id', $this->template_id)
            ->where('section', $this->section)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();

        if ($previousLine) {
            $tempOrder = $this->order;
            $this->order = $previousLine->order;
            $previousLine->order = $tempOrder;

            $this->save();
            $previousLine->save();

            return true;
        }

        return false;
    }

    /**
     * Move line down in order
     */
    public function moveDown(): bool
    {
        $nextLine = self::where('template_id', $this->template_id)
            ->where('section', $this->section)
            ->where('order', '>', $this->order)
            ->orderBy('order', 'asc')
            ->first();

        if ($nextLine) {
            $tempOrder = $this->order;
            $this->order = $nextLine->order;
            $nextLine->order = $tempOrder;

            $this->save();
            $nextLine->save();

            return true;
        }

        return false;
    }

    /**
     * Duplicate this line
     */
    public function duplicate(): self
    {
        $newLine = $this->replicate();

        // Find the max order in the same section
        $maxOrder = self::where('template_id', $this->template_id)
            ->where('section', $this->section)
            ->max('order');

        $newLine->order = ($maxOrder ?? 0) + 1;
        $newLine->save();

        return $newLine;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Method
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        // When creating a new line, auto-assign order
        static::creating(function ($line) {
            if (!isset($line->order)) {
                $maxOrder = self::where('template_id', $line->template_id)
                    ->where('section', $line->section)
                    ->max('order');

                $line->order = ($maxOrder ?? -1) + 1;
            }

            // Merge with default properties if not set
            if (empty($line->properties)) {
                $line->properties = $line->getDefaultProperties();
            }
        });

        // When deleting a line, reorder remaining lines
        static::deleted(function ($line) {
            self::where('template_id', $line->template_id)
                ->where('section', $line->section)
                ->where('order', '>', $line->order)
                ->decrement('order');
        });
    }
}
