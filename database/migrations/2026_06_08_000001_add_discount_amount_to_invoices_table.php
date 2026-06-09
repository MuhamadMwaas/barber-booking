<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a first-class `discount_amount` column to invoices.
 *
 * WHY a real column (not invoice_data JSON):
 *  - It is the single source of truth read directly by the print templates
 *    (totals-summary blade + the invoice.discount / invoice.items_total
 *    dynamic fields), and is query-able for future reporting / German audit.
 *  - It holds the GROSS discount (tax-inclusive), because all prices in this
 *    system are gross and tax is always reverse-extracted.
 *
 * Invariant enforced by InvoiceService::applyFinalAmount() and
 * Invoice::calculateTotals():
 *     total_amount            = (sum of items.total_amount) - discount_amount
 *     subtotal + tax_amount   = total_amount   (reverse-tax on the discounted gross)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_amount', 8, 2)
                ->default(0)
                ->after('total_amount')
                ->comment('Gross discount granted at payment time (items total - amount paid).');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
