<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * provider_attendances — work-time tracking for service providers.
 *
 * One row = one attendance SESSION (check-in → check-out). Multiple sessions per
 * day are allowed (e.g. a split shift, or leaving mid-day and coming back), so
 * there is deliberately NO unique constraint on (user_id, work_date).
 *
 * A session with check_out_at = NULL is "open" (provider is currently clocked in,
 * or forgot to clock out — see signature_missing equivalent: "no checkout").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // NB: the branches table is named `branchs` in this project (legacy
            // typo), so the referenced table is specified explicitly.
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branchs')
                ->nullOnDelete();

            // Local calendar date of the check-in — used for grouping/reporting.
            $table->date('work_date');

            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();

            // Where the punch came from (dashboard now; kiosk/api later).
            $table->string('source')->nullable()->default('dashboard');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_attendances');
    }
};
