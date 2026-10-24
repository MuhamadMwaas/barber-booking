<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app now shows availability a month at a time and lets the customer step
 * to the following month (/api/availability/days). max_booking_days decides how
 * far ahead a date may be booked, and both the month views and the booking call
 * honour it — at the seeded 10 days (or lower) the month view is mostly greyed
 * out as `outside_booking_window`.
 *
 * Data only, and only upwards: a window an admin already set to 60 or more wins.
 * A missing row is created, because the code default (10) is too short as well.
 *
 * salon_settings.value is JSON-cast on the model; the seeder stores an integer,
 * so the new value is written as the JSON number `60`.
 */
return new class extends Migration
{
    private const DAYS = 60;

    public function up(): void
    {
        $row = DB::table('salon_settings')->where('key', 'max_booking_days')->first();

        if ($row === null) {
            DB::table('salon_settings')->insert([
                'key' => 'max_booking_days',
                'value' => json_encode(self::DAYS),
                'type' => 'integer',
                'description' => 'Maximum days in advance for booking',
                'setting_group' => 'booking',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ((int) json_decode((string) $row->value) < self::DAYS) {
            DB::table('salon_settings')
                ->where('id', $row->id)
                ->update(['value' => json_encode(self::DAYS), 'updated_at' => now()]);
        }
    }

    /**
     * The previous value is not recorded, so nothing is put back: shrinking the
     * window on rollback could strand bookings customers already made.
     */
    public function down(): void
    {
    }
};
