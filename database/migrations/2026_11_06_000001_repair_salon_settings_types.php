<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs three seeded salon_settings rows. Data only.
 *
 * max_services_per_booking was typed `boolean`, so the admin page drew a toggle
 * for a number and saving it stored "false". The booking check then compares
 * `count($services) > "false"`, which PHP answers false for any count: the limit
 * was gone, silently. A value that is no longer a number goes back to the default.
 *
 * payment_methods held a JSON string inside the JSON cast (`"[\"cash\", ...]"`),
 * so it decoded to a string, not a list.
 *
 * max_daily_bookings carried the description of max_booking_days.
 *
 * salon_settings.value is JSON-cast on the model, so everything is json_encoded.
 */
return new class extends Migration
{
    private const DEFAULT_MAX_SERVICES = 10;

    public function up(): void
    {
        foreach (DB::table('salon_settings')->where('key', 'max_services_per_booking')->get() as $row) {
            $value = json_decode((string) $row->value);

            DB::table('salon_settings')->where('id', $row->id)->update([
                'type' => 'integer',
                'value' => json_encode(is_numeric($value) ? (int) $value : self::DEFAULT_MAX_SERVICES),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('salon_settings')->where('key', 'payment_methods')->get() as $row) {
            $value = json_decode((string) $row->value, true);
            $list = is_string($value) ? json_decode($value, true) : null;

            if (is_array($list)) {
                DB::table('salon_settings')->where('id', $row->id)->update([
                    'value' => json_encode($list),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('salon_settings')->where('key', 'max_daily_bookings')->update([
            'description' => 'Maximum bookings per customer per day',
            'updated_at' => now(),
        ]);
    }

    /**
     * Nothing is put back: the old type is what broke the limit, and the old
     * payment_methods shape is the same list, encoded twice.
     */
    public function down(): void {}
};
