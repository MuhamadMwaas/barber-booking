<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'status',
        'class',
    ];

    protected $casts = [
        'type' => 'integer',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants for types
    const TYPE_CREDIT_CARD = 1;
    const TYPE_DEBIT_CARD = 2;
    const TYPE_PAYPAL = 3;
    const TYPE_STRIPE = 4;
    const TYPE_CASH = 5;
    const TYPE_BANK_TRANSFER = 6;

    // Relationships

    public function savedPaymentMethods(): HasMany
    {
        return $this->hasMany(SavePaymentMethod::class, 'payment_method_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_method_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeByType($query, int $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    // Accessors

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            self::TYPE_CREDIT_CARD => 'Credit Card',
            self::TYPE_DEBIT_CARD => 'Debit Card',
            self::TYPE_PAYPAL => 'PayPal',
            self::TYPE_STRIPE => 'Stripe',
            self::TYPE_CASH => 'Cash',
            self::TYPE_BANK_TRANSFER => 'Bank Transfer',
            default => 'Unknown',
        };
    }

    public function getIsOnlineAttribute(): bool
    {
        return in_array($this->type, [
            self::TYPE_CREDIT_CARD,
            self::TYPE_DEBIT_CARD,
            self::TYPE_PAYPAL,
            self::TYPE_STRIPE,
        ]);
    }

    public function getIsOfflineAttribute(): bool
    {
        return in_array($this->type, [
            self::TYPE_CASH,
            self::TYPE_BANK_TRANSFER,
        ]);
    }

    // Static Methods

    public static function getTypes(): array
    {
        return [
            self::TYPE_CREDIT_CARD => 'Credit Card',
            self::TYPE_DEBIT_CARD => 'Debit Card',
            self::TYPE_PAYPAL => 'PayPal',
            self::TYPE_STRIPE => 'Stripe',
            self::TYPE_CASH => 'Cash',
            self::TYPE_BANK_TRANSFER => 'Bank Transfer',
        ];
    }
}
