<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record WHICH channels a reminder actually went out on.
 *
 * Once push, email and SMS are each independently switchable, "the reminder was
 * sent" stops being a single fact: a row marked `sent` may have reached the
 * customer on three channels, on one, or — if they switched everything off
 * between scheduling and firing — on none at all. Without this column the most
 * common support ticket ("I set a reminder and nothing arrived") is
 * undiagnosable, because the only evidence left is a `sent` flag that says
 * nothing about delivery.
 *
 * Written AFTER the send attempt, outside the claiming transaction, so a failure
 * in one channel is visible rather than silently rolled back with the others.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_reminders', function (Blueprint $table) {
            $table->json('delivered_channels')
                ->nullable()
                ->after('sent_at')
                ->comment('Channels the reminder actually went out on, e.g. ["sms"]; [] means no channel was enabled');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_reminders', function (Blueprint $table) {
            $table->dropColumn('delivered_channels');
        });
    }
};
