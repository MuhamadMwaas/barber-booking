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

    /**
     * Dropping this index is not a plain `dropIndex`.
     *
     * It leads with `provider_id`, which carries a foreign key. MySQL requires an
     * index on a foreign key's leading column, and when this composite index was
     * created it saw it as sufficient and silently dropped the FK's own
     * `appointments_provider_id_foreign` index. So this index is now the only one
     * backing that FK, and dropping it fails:
     *
     *     SQLSTATE[HY000] 1553: Cannot drop index
     *     'appointments_conflict_lookup_idx': needed in a foreign key constraint
     *
     * which broke `migrate:refresh`. Recreate a plain provider_id index first, in
     * the same ALTER, then drop this one.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! $this->hasIndex('appointments', 'appointments_provider_id_index')) {
                $table->index('provider_id', 'appointments_provider_id_index');
            }

            if ($this->hasIndex('appointments', 'appointments_conflict_lookup_idx')) {
                $table->dropIndex('appointments_conflict_lookup_idx');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return collect(
                Schema::getConnection()->getSchemaBuilder()->getIndexes($table)
            )->contains(fn ($i) => ($i['name'] ?? null) === $index);
        } catch (\Throwable) {
            return false;
        }
    }
};
