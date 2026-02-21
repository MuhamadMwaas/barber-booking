<?php

namespace App\Models;

use App\Enum\InvoiceStatus;
use App\Services\DocumentNumberGenerator;
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
        'segnture',
        'signature_missing_reason',
        'print_count',
        'first_printed_at',
        'last_printed_at',

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
        'print_count' => 'integer',
        'first_printed_at' => 'datetime',
        'last_printed_at' => 'datetime',

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


    public function printLogs(): HasMany {
        return $this->hasMany(PrintLog::class)->orderBy('created_at', 'desc');
    }

    public function lastPrintLog(): HasOne {
        return $this->hasOne(PrintLog::class)->latestOfMany();
    }

    public function getNextPrintNumber(): int {
        return $this->print_count + 1;
    }

    public function isPrinted(): bool {
        return $this->print_count > 0;
    }

    public function getCopyLabel(): string {
        $nextPrintNumber = $this->getNextPrintNumber();

        if ($nextPrintNumber === 1) {
            return '';
        }

        if ($nextPrintNumber === 2) {
            return ' (COPY)';
        }

        return ' (COPY ' . ($nextPrintNumber - 1) . ')';
    }

    public function incrementPrintCount(): void {
        $this->increment('print_count');

        if (!$this->first_printed_at) {
            $this->update(['first_printed_at' => now()]);
        }

        $this->update(['last_printed_at' => now()]);
    }

    public function getTotalPrintCopies(): int {
        return $this->printLogs()->success()->sum('copies');
    }

    public function getLastPrintedAt(): ?string {
        if (!$this->last_printed_at) {
            return null;
        }

        return $this->last_printed_at->diffForHumans();
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
        return $this->update(['status' => InvoiceStatus::PAID]);
    }

    public function markAsCancelled(): bool
    {
        return $this->update(['status' => InvoiceStatus::CANCELLED]);

    }

    public function calculateTotals(): void
    {
        $subtotal = '0';
        foreach ($this->items as $item) {
            $subtotal = bcadd($subtotal, (string)$item->subtotal, 2);
        }

        $this->subtotal = (float)$subtotal;

        // حساب الضريبة: (Subtotal * Rate) / 100
        $taxAmount = bcmul($subtotal, (string)$this->tax_rate, 4);
        $taxAmount = bcdiv($taxAmount, '100', 2);

        $this->tax_amount =(float) $taxAmount;
        $this->total_amount = bcadd($subtotal, $taxAmount, 2);

        $this->save();
    }

    // Generate unique invoice number
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        return DocumentNumberGenerator::generate('invoices', 'invoice_number', $prefix);
    }

    public function getCustomerName(): string
    {
        return $this->customer->name ?? $this->appointment->customer_name ?? '';
    }

    public function getTemplateOrDefault(): InvoiceTemplate
    {
        $template = InvoiceTemplate::where('is_default', true)->first();

        return $template;
    }
}
