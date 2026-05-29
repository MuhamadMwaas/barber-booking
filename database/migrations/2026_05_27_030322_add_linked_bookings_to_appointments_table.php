<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add_linked_bookings_to_appointments_table
 *
 * Purpose:
 *  - Enable parent/child linking between appointments (different providers, same customer/day)
 *  - Preserve original times before any push cascading operation (for traceability)
 *
 * Columns added:
 *  - parent_appointment_id : FK -> appointments(id), nullable. Single-level only.
 *  - original_start_time   : datetime, set once on first push to keep the true original.
 *  - original_end_time     : datetime, set once on first push.
 *  - was_pushed            : boolean flag for quick UI detection.
 *  - last_pushed_at        : timestamp of the most recent push operation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_appointment_id')->nullable()->after('provider_id');
            $table->foreign('parent_appointment_id')
                ->references('id')->on('appointments')
                ->nullOnDelete();
            $table->index('parent_appointment_id', 'appointments_parent_id_idx');

            $table->dateTime('original_start_time')->nullable()->after('end_time');
            $table->dateTime('original_end_time')->nullable()->after('original_start_time');
            $table->boolean('was_pushed')->default(false)->after('original_end_time');
            $table->dateTime('last_pushed_at')->nullable()->after('was_pushed');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['parent_appointment_id']);
            $table->dropIndex('appointments_parent_id_idx');
            $table->dropColumn([
                'parent_appointment_id',
                'original_start_time',
                'original_end_time',
                'was_pushed',
                'last_pushed_at',
            ]);
        });
    }
};
