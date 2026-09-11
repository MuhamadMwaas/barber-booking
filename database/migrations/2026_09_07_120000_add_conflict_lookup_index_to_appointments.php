<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The conflict check — "is this provider busy in [start, end)?" — is the hottest
 * query in the booking path: it runs for every service on every booking attempt,
 * and again for every provider/day pair the availability endpoints fan out over.
 *
 * `appointments` carried only PRIMARY, customer_id, provider_id and
 * parent_appointment_id, so that query filtered on provider_id and then scanned
 * every one of that provider's appointments across all dates.
 *
 * Column order follows the query in Appointment::scopeBlocksProviderTime():
 * equality on provider_id, then the day, then the two status columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(
                ['provider_id', 'appointment_date', 'created_status', 'status'],
                'appointments_conflict_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_conflict_lookup_idx');
        });
    }
};
