<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the four-column unique index on `appointment_reminders` with one that
 * expresses the rule the application actually enforces.
 *
 * THE BUG. The old index was `unique(appointment_id, user_id, remind_at, status)`.
 * It reads as "no duplicate reminder", but `status` is mutable: rescheduling calls
 * AppointmentReminderService::cancelRemindersForAppointment(), which flips the live
 * row from `pending` to `cancelled` rather than deleting it. So every reschedule
 * mints a permanent `cancelled` row, and the index forbids two cancelled rows that
 * share a remind_at. A customer toggling the dropdown back and forth between
 * "1 hour before" and "2 hours before" — exactly what a dropdown invites — walks
 * into a duplicate-key error on the UPDATE inside cancelRemindersForAppointment().
 * That surfaces as a QueryException, which the controller's `catch (\Throwable)`
 * turns into a bare 500 on a perfectly legal request.
 *
 * THE FIX. The real invariant is "at most ONE live reminder per appointment per
 * user"; history rows must not participate at all. `active_slot` encodes exactly
 * that: it holds AppointmentReminder::ACTIVE_SLOT (1) while the reminder is
 * pending and NULL once it is sent or cancelled. MySQL (like the SQL standard)
 * does not treat NULLs as equal in a unique index, so any number of historical
 * rows coexist while the database still refuses a second live one.
 *
 * This keeps the project's two-layer defence intact (the service prevents the
 * race, the UNIQUE constraint catches what we forgot) instead of dropping the
 * constraint and trusting application code alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_reminders', function (Blueprint $table) {
            // Nullable on purpose: NULL is what takes a history row OUT of the
            // unique index. A default of 1 would defeat the whole mechanism.
            $table->unsignedTinyInteger('active_slot')
                ->nullable()
                ->after('status')
                ->comment('1 = live reminder (unique per appointment+user); NULL = sent/cancelled history');
        });

        // Backfill: only rows still waiting to fire occupy the live slot.
        DB::table('appointment_reminders')
            ->where('status', 'pending')
            ->whereNull('cancelled_at')
            ->whereNull('sent_at')
            ->update(['active_slot' => 1]);

        // Any pre-existing duplicates would block the new index. Keep the newest
        // live row per (appointment, user) and demote the rest to history, so the
        // migration cannot fail on legacy data.
        $duplicates = DB::table('appointment_reminders')
            ->select('appointment_id', 'user_id', DB::raw('MAX(id) as keep_id'))
            ->whereNotNull('active_slot')
            ->groupBy('appointment_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('appointment_reminders')
                ->where('appointment_id', $duplicate->appointment_id)
                ->where('user_id', $duplicate->user_id)
                ->whereNotNull('active_slot')
                ->where('id', '!=', $duplicate->keep_id)
                ->update([
                    'active_slot' => null,
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
        }

        Schema::table('appointment_reminders', function (Blueprint $table) {
            $table->dropUnique('appointment_reminders_unique_pending');
            $table->unique(
                ['appointment_id', 'user_id', 'active_slot'],
                'appointment_reminders_one_active'
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointment_reminders', function (Blueprint $table) {
            $table->dropUnique('appointment_reminders_one_active');
            $table->unique(
                ['appointment_id', 'user_id', 'remind_at', 'status'],
                'appointment_reminders_unique_pending'
            );
            $table->dropColumn('active_slot');
        });
    }
};
