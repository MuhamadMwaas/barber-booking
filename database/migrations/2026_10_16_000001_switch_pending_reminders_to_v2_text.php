<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reminders already queued were scheduled with the legacy text keys. Point the
 * still-pending ones at the current wording so no customer receives the old
 * text after the change ships. Sent/cancelled rows are history and keep their
 * keys — the in-app notifications created from them still need the legacy text.
 *
 * Data only: the send job rebuilds params at delivery, so the new placeholders
 * are filled even though these rows were saved without them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('appointment_reminders')
            ->where('status', 'pending')
            ->where('message_key', 'appointment_reminder.message')
            ->update([
                'title_key' => 'appointment_reminder.v2.title',
                'message_key' => 'appointment_reminder.v2.message',
            ]);
    }

    public function down(): void
    {
        DB::table('appointment_reminders')
            ->where('status', 'pending')
            ->where('message_key', 'appointment_reminder.v2.message')
            ->update([
                'title_key' => 'appointment_reminder.title',
                'message_key' => 'appointment_reminder.message',
            ]);
    }
};
