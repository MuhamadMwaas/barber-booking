<?php

namespace App\Models;

use App\Enum\OtpType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @see \App\Services\PasswordResetService
 */
class PasswordResetGrant extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'channel',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'channel' => OtpType::class,
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRedeemable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
