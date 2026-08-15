<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The polymorphic `paymentable` columns were created without an index, so every
 * lookup of "the payments for this invoice" is a full table scan. That was
 * tolerable while payments were only read one invoice at a time, but the Daily
 * Report joins across a whole date range at once (DailyReportService).
 *
 * A composite index on (paymentable_type, paymentable_id) is the standard shape
 * for a morph relation — it serves both the eager load and the `whereHas`.
 * The extra index on `created_at` serves the report's collection-date window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['paymentable_type', 'paymentable_id'], 'payments_paymentable_index');
            $table->index('created_at', 'payments_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_paymentable_index');
            $table->dropIndex('payments_created_at_index');
        });
    }
};
