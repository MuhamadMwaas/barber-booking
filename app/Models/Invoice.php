<?php

namespace App\Models;

use App\Enum\InvoiceStatus;
use App\Services\DocumentNumberGenerator;
use App\Services\TaxCalculatorService;
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
        'discount_amount',
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
        'discount_amount' => 'decimal:2',
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

    public function getCopyLabel(string $language = 'en'): string {
        $nextPrintNumber = $this->getNextPrintNumber();

        // Only label as a copy once the invoice has been printed before.
        if ($nextPrintNumber <= 1) {
            return '';
        }

        $word = $language === 'de' ? 'Kopie' : 'COPY';

        if ($nextPrintNumber === 2) {
            return ' (' . $word . ')';
        }

        return ' (' . $word . ' ' . ($nextPrintNumber - 1) . ')';
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

    /**
     * Pre-discount gross total = the sum of all item gross totals.
     *
     * Derived as (total_amount + discount_amount) so we never need a second
     * stored column. This is the "Artikel gesamt" / "Items total" value shown
     * on the receipt above the discount line.
     */
    public function getItemsTotalAttribute(): float
    {
        return (float) bcadd(
            (string) ($this->total_amount ?? 0),
            (string) ($this->discount_amount ?? 0),
            2
        );
    }

    /**
     * Recalculate the invoice money fields from its items, GROSS pricing model.
     *
     * Unified accounting rule for the whole system:
     *   itemsGross = Σ items.total_amount        (gross, tax-inclusive)
     *   total      = itemsGross - discount_amount (the amount actually owed/paid)
     *   {net,tax}  = reverse-extract tax from `total`  (net + tax == total)
     *
     * This replaces the previous FORWARD-tax formula ((net * rate) / 100) so it
     * matches TaxCalculatorService / rebuildAggregatedInvoice everywhere, and it
     * is discount-aware so the InvoiceItem observer can never clobber a discount
     * back to the full price.
     */
    public function calculateTotals(): void
    {
        $itemsGross = '0';
        foreach ($this->items as $item) {
            $itemsGross = bcadd($itemsGross, (string) $item->total_amount, 2);
        }

        $discount = (string) ($this->discount_amount ?? 0);
        $total = bcsub($itemsGross, $discount, 2);
        if (bccomp($total, '0', 2) < 0) {
            $total = '0.00';
        }

        $tax = app(TaxCalculatorService::class)->extractTax($total, (string) ($this->tax_rate ?? 0));

        $this->subtotal = $tax['net'];
        $this->tax_amount = $tax['tax'];
        $this->total_amount = $tax['gross']; // == $total, reconciled so net + tax == total

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

    /**
     * All appointments covered by this invoice = parent + every child.
     * Used by InvoiceService::rebuildAggregatedInvoice and PrintController
     * to render the unified receipt.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Appointment>
     */
    public function getCoveredAppointments()
    {
        if (! $this->appointment) {
            return collect();
        }

        // The invoice always lives on the PARENT (or standalone).
        // So $this->appointment is the root of the linked group.
        return $this->appointment
            ->linkedGroup()
            ->with(['services_record.service', 'provider'])
            ->orderByRaw('COALESCE(parent_appointment_id, id) ASC') // parent first (null), then children
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * True if this invoice covers more than one appointment (parent + children).
     */
    public function isAggregated(): bool
    {
        if (! $this->appointment) {
            return false;
        }
        return $this->appointment->children()->exists();
    }
}
