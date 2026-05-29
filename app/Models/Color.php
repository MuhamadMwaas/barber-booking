<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Color extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hex_code',
        'brand',
        'unit',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'stock_quantity' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /**
     * Appointments that used this color.
     */
    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_colors')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Returns the display label: "name (brand)" or just "name".
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->brand
            ? "{$this->name} ({$this->brand})"
            : $this->name;
    }
}
