<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a booking whose customer deleted their account.
 *
 * Deleting an account removes the users row but keeps the bookings, with the
 * customer's name, phone and email copied onto them (AccountDeletionService).
 * Without a marker such a row is indistinguishable from a counter guest
 * booking, and GuestBookingClaimService would hand it to the next account that
 * verifies the same phone number. Null = not orphaned by an account deletion.
 *
 * dateTime, not timestamp: on this server a bare timestamp column can gain
 * ON UPDATE CURRENT_TIMESTAMP and stamp every booking on its next edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('customer_account_deleted_at')->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('customer_account_deleted_at');
        });
    }
};
