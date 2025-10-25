<?php

namespace App\Models;

use App\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_method_id',
        'payment_number',
        'amount',
        'subtotal',
        'status',
        'payment_gateway_id',
        'payment_metadata',
        'tax_amount',
        'type',
        'paymentable_id',
        'paymentable_type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'payment_metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants for types
    const TYPE_FULL = 'full';
    const TYPE_PARTIAL = 'partial';
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_REFUND = 'refund';

    // Relationships

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function paymentable(): MorphTo
    {
        return $this->morphTo('paymentable', 'paymentable_type', 'paymentable_id');
    }

    // Scopes

    public function scopeByStatus($query, PaymentStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [
            PaymentStatus::PAID_ONLINE,
            PaymentStatus::PAID_ONSTIE_CASH,
            PaymentStatus::PAID_ONSTIE_CARD,
        ]);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    public function scopeRefunded($query)
    {
        return $query->whereIn('status', [
            PaymentStatus::REFUNDED,
            PaymentStatus::PARTIALLY_REFUNDED,
        ]);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Accessors

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' AED';
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal, 2) . ' AED';
    }

    public function getFormattedTaxAmountAttribute(): string
    {
        return number_format($this->tax_amount, 2) . ' AED';
    }

    public function getIsSuccessfulAttribute(): bool
    {
        return in_array($this->status, [
            PaymentStatus::PAID_ONLINE,
            PaymentStatus::PAID_ONSTIE_CASH,
            PaymentStatus::PAID_ONSTIE_CARD,
        ]);
    }

    public function getIsRefundedAttribute(): bool
    {
        return in_array($this->status, [
            PaymentStatus::REFUNDED,
            PaymentStatus::PARTIALLY_REFUNDED,
        ]);
    }

    // Methods

    public function markAsPaid(): bool
    {
        return $this->update(['status' => PaymentStatus::PAID_ONLINE]);
    }

    public function markAsFailed(): bool
    {
        return $this->update(['status' => PaymentStatus::FAILED]);
    }

    public function markAsRefunded(): bool
    {
        return $this->update(['status' => PaymentStatus::REFUNDED]);
    }

    public function refund(?float $amount = null): self
    {
        $refundAmount = $amount ?? $this->amount;

        $refund = self::create([
            'payment_method_id' => $this->payment_method_id,
            'payment_number' => self::generatePaymentNumber(),
            'amount' => $refundAmount,
            'subtotal' => $refundAmount,
            'status' => PaymentStatus::REFUNDED,
            'tax_amount' => 0,
            'type' => self::TYPE_REFUND,
            'paymentable_id' => $this->paymentable_id,
            'paymentable_type' => $this->paymentable_type,
            'payment_metadata' => [
                'original_payment_id' => $this->id,
                'refund_date' => now()->toDateTimeString(),
            ],
        ]);

        if ($refundAmount < $this->amount) {
            $this->update(['status' => PaymentStatus::PARTIALLY_REFUNDED]);
        } else {
            $this->update(['status' => PaymentStatus::REFUNDED]);
        }

        return $refund;
    }

    // Generate unique payment number
    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }

    // Static Methods

    public static function getTypes(): array
    {
        return [
            self::TYPE_FULL => 'Full Payment',
            self::TYPE_PARTIAL => 'Partial Payment',
            self::TYPE_DEPOSIT => 'Deposit',
            self::TYPE_REFUND => 'Refund',
        ];
    }
}
