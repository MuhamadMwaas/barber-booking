<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stops `otps.expires_at` from being silently rewritten on every UPDATE.
 *
 * THE DEFECT
 * ----------
 * The original migration declared:
 *
 *     $table->timestamp('expires_at');
 *
 * With MySQL/MariaDB running `explicit_defaults_for_timestamp = OFF` (the
 * default on this server), the FIRST `TIMESTAMP NOT NULL` column in a table that
 * declares neither NULL, nor an explicit DEFAULT, nor an explicit ON UPDATE, is
 * automatically given BOTH `DEFAULT CURRENT_TIMESTAMP` and — the damaging half —
 * `ON UPDATE CURRENT_TIMESTAMP`.
 *
 * Nothing in the Laravel migration says so, which is why it went unnoticed: the
 * behaviour is added by the database, not by the code.
 *
 * WHAT IT BROKE
 * -------------
 * `OtpService::validate()` increments `attempts` on a wrong guess. That UPDATE
 * reset `expires_at` to NOW(), so the code the customer was holding became
 * expired the instant they made their first typo:
 *
 *     seeded          expires_at = 18:44:18   (now = 18:34:18)
 *     one wrong guess attempts   = 1
 *                     expires_at = 18:34:18   <-- expired on the spot
 *     correct code    -> rejected             <-- customer locked out
 *
 * The customer then had to wait out `otp.resend_cooldown_seconds` (60s) and
 * request a whole new code, for a single mistyped digit.
 *
 * WHY IT IS FIXED HERE AND NOT LATER
 * ----------------------------------
 * The AUTH-02 fix in OtpService::validate() atomically claims an attempt with a
 * conditional UPDATE before comparing the code. Every one of those writes would
 * keep resetting the expiry, so the attempt cap would appear to work while
 * actually destroying the code on the first failure. This column has to be
 * corrected for that fix to mean anything.
 *
 * WHY RAW SQL
 * -----------
 * `$table->timestamp('expires_at')->change()` would re-emit a bare
 * `TIMESTAMP NOT NULL`, which re-triggers the very rule above. Naming an explicit
 * DEFAULT is what suppresses the automatic attributes, so the definition has to
 * be spelled out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('otps') || DB::getDriverName() !== 'mysql') {
            return;
        }

        // An explicit DEFAULT suppresses MySQL's automatic
        // "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" pair. The
        // default value itself is never relied on — OtpService always supplies
        // expires_at — it exists purely to keep ON UPDATE from coming back.
        DB::statement(
            'ALTER TABLE `otps` MODIFY `expires_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        // Any row updated while the defect was live has a corrupted expiry. Those
        // codes are unusable either way, so retire them rather than leaving rows
        // whose expires_at means nothing.
        DB::table('otps')
            ->where('used', false)
            ->where('expires_at', '<=', now())
            ->update(['used' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('otps') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE `otps` MODIFY `expires_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
    }
};
