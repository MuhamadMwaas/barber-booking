<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DashboardMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dashboard_messages';

    protected $fillable = [
        'user_id',
        'body',
        'message_date',
        'is_pinned',
        'expires_at',
        'deleted_by',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'message_date' => 'date',
        'expires_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    /**
     * Active = not soft-deleted (default) and not expired.
     * Ordered: pinned (admin) first, then newest first.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');
    }

    /**
     * Limit to messages that belong to a specific calendar day. The board is
     * day-scoped: changing the selected day on the dashboard reloads only that
     * day's messages.
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('message_date', $date);
    }
}
