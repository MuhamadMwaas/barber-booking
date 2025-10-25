<?php

namespace App\Models;

use App\Enum\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'customer_id',
        'invoice_number',
        'subtotal',
        'tax_amount',
        'tax_rate',
        'total_amount',
        'status',
        'notes',
        'invoice_data',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => InvoiceStatus::class,

    ];


    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'paymentable_id')
            ->where('paymentable_type', self::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }




    // Accessors


    public function getStatusLabelAttribute(): string
    {
        return $this->status->getLabel();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->getColor();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return $this->status->getBadgeClass();
    }




    // Methods

    public function markAsPaid(): bool
    {
        return $this->update(['status' => 'paid']);
    }

    public function markAsCancelled(): bool
    {
        return $this->update(['status' => 'cancelled']);
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total_amount');
        $this->tax_amount = $this->subtotal * ($this->tax_rate / 100);
        $this->total_amount = $this->subtotal + $this->tax_amount;
        $this->save();
    }

    // Generate unique invoice number
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }
}
