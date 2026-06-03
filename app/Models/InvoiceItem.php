<?php

namespace App\Models;

use App\Services\TaxCalculatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_amount',
        'tax_rate',
        'total_amount',
        'itemable_id',
        'itemable_type',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'subtotal',
    ];

    // Relationships

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo('itemable', 'itemable_type', 'itemable_id');
    }

    // Scopes

    public function scopeForInvoice($query, int $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    // Accessors

    public function getFormattedUnitPriceAttribute(): string
    {
        return number_format($this->unit_price, 2) . ' EUR';
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return number_format($this->total_amount, 2) . ' EUR';
    }

    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    // Methods

    public function calculateTotal(): void
    {
        // المبلغ الصافي للبند = الكمية × سعر الوحدة (net) — بـ bcmath بدل ضرب float
        $netSubtotal = bcmul(
            (string) ($this->quantity ?? 1),
            (string) ($this->unit_price ?? 0),
            2
        );

        // unit_price مُخزَّن دائماً كصافي (net)، فنعيد بناء الإجمالي بإضافة الضريبة.
        // addTax يضمن: التقريب لمنزلتين + تطابق net + tax = gross تماماً (لا فرق سنت).
        // يتعامل مع tax_rate = 0/null داخلياً (يرجع ضريبة 0 وإجمالي = الصافي).
        $result = app(TaxCalculatorService::class)
            ->addTax($netSubtotal, (string) ($this->tax_rate ?? 0), 2);

        $this->tax_amount = $result['tax'];
        $this->total_amount = $result['gross'];
    }

    // Events

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateTotal();
        });

        static::saved(function ($item) {
            $item->invoice->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->invoice->calculateTotals();
        });
    }
}
