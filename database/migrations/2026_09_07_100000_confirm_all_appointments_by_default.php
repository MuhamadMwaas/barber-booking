<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `created_status` was designed for an online-payment flow that does not exist:
 * 1 = confirmed and blocking its slot, 0 = awaiting payment. Since every booking
 * is paid in cash at the shop, nothing ever legitimately sits at 0 — and the
 * column defaulted to 0, so every appointment created outside BookingService
 * (seeders, direct inserts) was invisible to DashboardService,
 * DashboardStatsService, DailyReportService, GapAnalysisService and
 * PushBookingsService, all of which filter on created_status = 1.
 *
 * This flips the default and lifts the existing rows so the reporting layer sees
 * the bookings that actually happened. The column stays: it remains the flag a
 * future deposit / online-payment flow would use, and it is now read in exactly
 * one place — Appointment::scopeBlocksProviderTime().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->tinyInteger('created_status')->default(1)->change();
        });

        DB::table('appointments')->where('created_status', 0)->update(['created_status' => 1]);
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->tinyInteger('created_status')->default(0)->change();
        });

        // The pre-migration 0/1 split is not recoverable — every row was 0 — so
        // rolling back restores the default only, never the data.
    }
};
