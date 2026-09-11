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

    /**
     * إعادة اشتقاق ضريبة البند وإجماليه.
     *
     * ═══════════════════════════════════════════════════════════════════
     * القاعدة (MON-01): الـ GROSS هو الحقيقة. إذا كان `total_amount`
     * مخزَّناً فلا يُعاد بناؤه من الصافي أبداً — الضريبة تُستخرج منه عكسياً.
     * ═══════════════════════════════════════════════════════════════════
     *
     * الاستخراج العكسي **دالة غير عكوسة**: عدة قيم gross تنتج نفس الـ net
     * المقرَّب، فلا يمكن استرجاع الـ gross الأصلي من الـ net. النسخة السابقة
     * كانت تفعل ذلك بـ `addTax($net)` فتُفقد سنتاً في كل سعر تقريباً:
     *
     *     gross 50.00 → net 42.01 → رجوعاً للـ gross: 49.99  ← فُقد 0.01
     *     gross 19.00 → net 15.96 → رجوعاً للـ gross: 18.99  ← فُقد 0.01
     *
     * وبما أن هذه الدالة تُنادى في `static::saving`، فأي حفظ لبند فاتورة —
     * حتى فاتورة مُنهاة ومطبوعة — كان يُصغّرها سنتاً. المسارَان الحاليان
     * اللذان ينشئان البنود ملفوفان بـ `withoutEvents()` فكان العطل خامداً،
     * لكن الأمان لا يجوز أن يعتمد على أن يتذكّر كل مسار مستقبلي ذلك.
     *
     * السلوك:
     *   • `total_amount` موجود  → يُحفظ كما هو، وتُستخرج الضريبة منه عكسياً.
     *   • `total_amount` مفقود  → بند بُني من صافٍ فقط، فيُشتق الإجمالي أمامياً.
     *
     * لتغيير سعر بندٍ قائم: اكتب `total_amount` الجديد (الـ gross). كتابة
     * `unit_price` وحده لن تغيّر الإجمالي — وهذا مقصود.
     */
    public function calculateTotal(): void
    {
        $tax  = app(TaxCalculatorService::class);
        $rate = (string) ($this->tax_rate ?? 0);

        $storedGross = (string) ($this->total_amount ?? 0);

        // الـ gross مخزَّن ⇒ هو المرجع. استخرج الضريبة منه ولا تلمسه.
        if (is_numeric($storedGross) && bccomp($storedGross, '0', 2) > 0) {
            $result = $tax->extractTax($storedGross, $rate, 2);

            $this->tax_amount   = $result['tax'];
            $this->total_amount = $result['gross']; // == $storedGross مقرَّباً لمنزلتين

            return;
        }

        // لا gross بعد: البند بُني من سعر وحدة صافٍ، فنشتق الإجمالي أمامياً.
        $netSubtotal = bcmul(
            (string) ($this->quantity ?? 1),
            (string) ($this->unit_price ?? 0),
            2
        );

        $result = $tax->addTax($netSubtotal, $rate, 2);

        $this->tax_amount   = $result['tax'];
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
