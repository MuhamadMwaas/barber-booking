<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SavePaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_method_id',
        'display_name',
        'last_four',
        'email',
        'token',
        'expires_at',
        'is_default',
        'data',
        'zip',
        'exp_month',
        'exp_year',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_default' => 'boolean',
        'data' => 'array',
        'exp_month' => 'integer',
        'exp_year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
        'data',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    // Scopes

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    // Accessors

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getMaskedCardNumberAttribute(): string
    {
        return $this->last_four ? '**** **** **** ' . $this->last_four : 'N/A';
    }

    public function getExpirationDateAttribute(): ?string
    {
        if ($this->exp_month && $this->exp_year) {
            return sprintf('%02d/%d', $this->exp_month, $this->exp_year);
        }
        return null;
    }

    public function getCardBrandAttribute(): ?string
    {
        if (isset($this->data['brand'])) {
            return $this->data['brand'];
        }
        return null;
    }

    // Methods

    public function setAsDefault(): bool
    {
        // Remove default from other payment methods for this user
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        return $this->update(['is_default' => true]);
    }

    public function isValid(): bool
    {
        return !$this->is_expired && $this->expires_at > now();
    }

    // Mutators

    public function setTokenAttribute($value)
    {
        $this->attributes['token'] = Crypt::encryptString($value);
    }

    public function getTokenAttribute($value)
    {
        return Crypt::decryptString($value);
    }
}
