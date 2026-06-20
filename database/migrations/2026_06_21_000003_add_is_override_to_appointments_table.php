<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "force booking" (override) marker columns to appointments.
 *
 * WHY real columns (not notes / invoice_data JSON):
 *  - `is_override` is an auditable, query-able fact: "this slot was booked by
 *    trusted staff while BYPASSING the provider availability window (working
 *    day, working hours, full-day / hourly time-off)". It must be filterable
 *    for the timeline marker, statistics, and any future German audit export.
 *  - `override_reason` stores the optional human justification the staff member
 *    typed when forcing the booking (e.g. "VIP — opened the shop on Friday").
 *
 * IMPORTANT — what is_override does NOT mean:
 *  - It is NOT a "skip everything" flag. Even an override booking still passes
 *    the hard conflict check (provider must be free) and the
 *    "provider offers the service" check. See BookingValidationService.
 *
 * Default is false so every existing row and every non-staff path (API / Web /
 * Filament / legacy) keeps the exact previous semantics — booking is "normal"
 * unless a force-booking flag is explicitly raised server-side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('is_override')
                ->default(false)
                ->after('created_status')
                ->comment('True when booked via the staff "force booking" path, bypassing the provider availability window (NOT the conflict / offers-service checks).');

            $table->string('override_reason')
                ->nullable()
                ->after('is_override')
                ->comment('Optional staff justification for the forced booking.');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['is_override', 'override_reason']);
        });
    }
};
