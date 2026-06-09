<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The board moves from "global" to "day-scoped": every message now belongs
        // to a specific calendar day (message_date). Legacy rows have no sensible
        // day, so per the agreed spec we reset the board before adding the column.
        DB::table('dashboard_messages')->delete();

        Schema::table('dashboard_messages', function (Blueprint $table) {
            // The calendar day this message belongs to. Set from the dashboard's
            // selectedDate at post time, so a manager can pre-write a note for a
            // future day. The board only shows messages whose message_date equals
            // the currently selected day.
            $table->date('message_date')->after('body')->index();
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_messages', function (Blueprint $table) {
            $table->dropIndex(['message_date']);
            $table->dropColumn('message_date');
        });
    }
};
