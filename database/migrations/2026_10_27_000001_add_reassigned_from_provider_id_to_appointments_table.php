<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers whose booking this was before a leave moved it to someone else.
 *
 * Granting a leave over open bookings hands each of them to a free colleague
 * (LeaveReassignmentService). The customer keeps the time and the price, so
 * nothing else on the row records that the provider changed — this column is
 * the audit trail, and what the dashboard shows as "taken over from".
 *
 * Null = never reassigned. nullOnDelete: the trail is informational, it must
 * never stop a user row from being removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('reassigned_from_provider_id')
                ->nullable()
                ->after('provider_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reassigned_from_provider_id');
        });
    }
};
