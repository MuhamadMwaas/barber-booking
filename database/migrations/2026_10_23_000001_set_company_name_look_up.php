<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The salon's brand name is "Look up" (client-approved reminder wording:
 * "…Termin bei Look up am…", "Dein Look up Team"). The company_name setting was
 * seeded empty, so every reader fell back to APP_NAME ("LookUp") or printed
 * nothing at all.
 *
 * Data only, and only where the setting is still empty: a name already entered
 * in the admin panel wins.
 *
 * salon_settings.value is JSON-cast on the model, so an empty string is stored
 * as the two characters `""` and the name as `"Look up"`.
 */
return new class extends Migration
{
    private const NAME = 'Look up';

    public function up(): void
    {
        DB::table('salon_settings')
            ->where('key', 'company_name')
            ->where(fn ($query) => $query->whereNull('value')->orWhereIn('value', ['', json_encode('')]))
            ->update(['value' => json_encode(self::NAME), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('salon_settings')
            ->where('key', 'company_name')
            ->where('value', json_encode(self::NAME))
            ->update(['value' => json_encode(''), 'updated_at' => now()]);
    }
};
