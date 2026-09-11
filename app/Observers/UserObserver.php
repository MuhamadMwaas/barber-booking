<?php

namespace App\Observers;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Makes "deactivate this employee" an actual kill switch (AUTHZ-01).
 *
 * Middleware alone only decides the NEXT request on the surfaces that remember
 * to ask. Everything the account already holds — a live web session cookie, a
 * remember-me recaller, mobile API tokens — stays valid until it expires on its
 * own. With SESSION_LIFETIME being an IDLE timeout, a tab that keeps polling
 * refreshes `last_activity` forever, so "120 minutes" is not a bound on
 * anything as long as the browser stays open.
 *
 * So the moment `is_active` flips to false — or the account is deleted — every
 * credential it holds is destroyed here, in one place, whatever screen or
 * command performed the change.
 *
 * LIMIT worth knowing: this hooks Eloquent model events. A mass
 * `User::query()->update(['is_active' => false])`, a raw SQL update or a DB
 * import does NOT fire it. The `is_active` branch in
 * {@see \App\Http\Middleware\EnsureStaffDashboardAccess} is the fallback that
 * still catches those on the next request.
 */
class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $this->revokeAllAccess($user);
        }
    }

    /**
     * Fires for soft AND hard deletes. A soft-deleted user is already invisible
     * to the auth provider (the SoftDeletes global scope hides them from
     * retrieveById), but their refresh tokens are rows in another table with no
     * such scope, so they still have to be revoked explicitly.
     */
    public function deleted(User $user): void
    {
        $this->revokeAllAccess($user);
    }

    private function revokeAllAccess(User $user): void
    {
        // Mobile / API access.
        $user->tokens()->delete();
        RefreshToken::revokeAllFor($user->id, RefreshToken::REASON_ACCOUNT_DISABLED);

        // Web sessions (admin panel + staff dashboard). Only the database driver
        // stores a `user_id` we can target; on file/redis/array there is nothing
        // to delete and the middleware check is what ends the session instead.
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        // The remember-me cookie survives session deletion and would log them
        // straight back in. Nulling the token invalidates every issued recaller.
        // saveQuietly() so this write does not re-enter the observer.
        if ($user->exists) {
            $user->forceFill(['remember_token' => null])->saveQuietly();
        }
    }
}
