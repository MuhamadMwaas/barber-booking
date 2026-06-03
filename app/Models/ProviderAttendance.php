<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single attendance session for a provider (check-in → check-out).
 *
 * @property \Carbon\Carbon      $work_date
 * @property \Carbon\Carbon      $check_in_at
 * @property \Carbon\Carbon|null $check_out_at
 */
class ProviderAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'work_date'    => 'date',
        'check_in_at'  => 'datetime',
        'check_out_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('work_date', $date);
    }

    /** Sessions that have not been checked out yet. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('check_out_at');
    }

    // ── Accessors ──────────────────────────────────────────────────────────
    public function getIsOpenAttribute(): bool
    {
        return $this->check_out_at === null;
    }

    /** Worked minutes for a closed session (null while still open). */
    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->check_in_at || ! $this->check_out_at) {
            return null;
        }

        return (int) $this->check_in_at->diffInMinutes($this->check_out_at);
    }
}
