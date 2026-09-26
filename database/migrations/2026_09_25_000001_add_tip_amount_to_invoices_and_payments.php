<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record a tip (Trinkgeld) next to the invoice it was paid with.
 *
 * When the cashier types MORE than the invoice total, the difference is a tip
 * for the provider(s) who served the customer. A voluntary tip to an employee
 * is not salon revenue and carries no VAT, so it must NEVER be folded into
 * total_amount / subtotal / tax_amount: those stay the taxable sale, exactly as
 * before. The tip lives in its own column beside them.
 *
 * Default 0 so every invoice/payment issued before this change reads "no tip".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tip_amount', 8, 2)
                ->default(0)
                ->after('discount_amount')
                ->comment('Tip paid on top of total_amount; not revenue, no VAT');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('tip_amount', 8, 2)
                ->default(0)
                ->after('amount')
                ->comment('Tip collected with this payment; amount stays the invoice total');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('tip_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tip_amount');
        });
    }
};
