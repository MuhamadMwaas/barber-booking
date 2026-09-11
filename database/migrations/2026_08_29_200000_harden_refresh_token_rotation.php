<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AUTH-04 — schema support for refresh token rotation with reuse detection.
 *
 * TWO SEPARATE PROBLEMS ARE FIXED HERE
 * ====================================
 *
 * 1. `expires_at` rewrites itself on every UPDATE
 * -----------------------------------------------
 * `create_refresh_tokens_table` declared:
 *
 *     $table->timestamp('expires_at');
 *
 * `expires_at` is the FIRST `TIMESTAMP NOT NULL` column in this table, and with
 * `explicit_defaults_for_timestamp = OFF` (the default on this server) MySQL
 * silently attaches `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` to
 * it. Verified on the live schema:
 *
 *     `expires_at` timestamp NOT NULL
 *         DEFAULT current_timestamp() ON UPDATE current_timestamp()
 *
 * This is the same defect already corrected on `otps.expires_at`.
 *
 * Today it is only latent: every UPDATE this codebase performs on a refresh
 * token also revokes it, so a mangled expiry changes nothing. Rotation ends
 * that. Rotation writes to a token row and then *keeps reading* the row's
 * lifetime, so the column has to stop moving before it can be trusted.
 *
 * 2. A revocation carried no record of WHY or WHEN
 * ------------------------------------------------
 * Reuse detection is the act of telling two revoked tokens apart:
 *
 *   - a token revoked because it was ROTATED, coming back later, is evidence
 *     that a second copy of it exists — that is a leak, and every session for
 *     the account must die;
 *   - a token revoked by a logout or a password change, coming back later, is
 *     just a stale client that has not noticed. Nothing to react to.
 *
 * Without `revoked_reason` the two are indistinguishable and the alarm would
 * fire on ordinary logouts. `revoked_at` supplies the grace window that keeps
 * an honest client's retry of a lost reply from being read as theft, and
 * `replaced_by_id` chains each token to its successor so a compromised family
 * can be walked after the fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('refresh_tokens')) {
            return;
        }

        // An explicit DEFAULT is what suppresses MySQL's automatic
        // "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" pair. Writing
        // `$table->timestamp('expires_at')->change()` would re-emit a bare
        // TIMESTAMP NOT NULL and re-trigger the very rule being removed, so the
        // definition has to be spelled out in SQL. The default value itself is
        // never relied upon — AuthTokenService always supplies expires_at.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `refresh_tokens` MODIFY `expires_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            );
        }

        Schema::table('refresh_tokens', function (Blueprint $table) {
            // Nullable timestamps are exempt from the rule above: MySQL renders
            // them `NULL DEFAULT NULL` and attaches nothing.
            $table->timestamp('revoked_at')->nullable()->after('revoked');
            $table->string('revoked_reason', 32)->nullable()->after('revoked_at');
            $table->timestamp('last_used_at')->nullable()->after('revoked_reason');

            // Deliberately NOT a foreign key. It points into its own table and
            // is read only for forensics; a self-referencing constraint buys
            // nothing here and makes the SQLite test schema rebuild fragile.
            $table->unsignedBigInteger('replaced_by_id')->nullable()->after('last_used_at');

            // Revoking every token for one account is now a routine operation
            // (logout, password change, reuse detected), and it always filters
            // on exactly this pair.
            $table->index(['user_id', 'revoked'], 'refresh_tokens_user_id_revoked_index');
        });

        // Rows revoked before this migration have no timestamp and no reason.
        // They are backfilled as deliberate revocations, never as rotations:
        // rotation did not exist yet, so none of them can be evidence of a leak
        // and none of them may be allowed to trip the alarm retroactively.
        DB::table('refresh_tokens')
            ->where('revoked', true)
            ->whereNull('revoked_reason')
            ->update([
                'revoked_reason' => 'legacy',
                'revoked_at' => DB::raw('COALESCE(`updated_at`, `created_at`)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('refresh_tokens')) {
            return;
        }

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropIndex('refresh_tokens_user_id_revoked_index');
            $table->dropColumn(['revoked_at', 'revoked_reason', 'last_used_at', 'replaced_by_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `refresh_tokens` MODIFY `expires_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            );
        }
    }
};
