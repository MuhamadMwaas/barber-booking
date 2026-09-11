<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Serialises concurrent writes to a provider's calendar (BOOK-02).
 *
 * The problem it solves
 * --------------------
 * Checking "is 10:00 free?" with a SELECT reserves nothing. Two requests could
 * both read "free" and then both insert, producing two overlapping bookings on
 * one provider. Wrapping the INSERT in a transaction does not help: the check
 * still happened before the transaction, and under REPEATABLE-READ a plain
 * SELECT takes no locks at all.
 *
 * Why we lock the USER row and not the appointments
 * -------------------------------------------------
 * The state we need to defend is the ABSENCE of a row — "nothing is booked at
 * 10:00". You cannot lock rows that do not exist. `lockForUpdate()` on the
 * conflict query would lock zero rows and guarantee nothing. InnoDB gap locks
 * could in principle cover the empty range, but only over a suitable index, and
 * `appointments` has no composite index the planner is obliged to use — building
 * booking integrity on an unindexed scan's lock behaviour is not something to
 * rely on.
 *
 * So we lock a row that is always there: the provider's own row in `users`.
 * Every writer touching that provider's calendar takes the same lock, so they
 * queue instead of racing. The lock is released when the transaction ends.
 *
 * Deadlock safety
 * ---------------
 * Providers AND customers are both rows in `users`, so a booking that needs to
 * lock two providers and one customer locks three rows in ONE table. We sort the
 * ids and take them in a single ordered statement, so any two concurrent
 * bookings acquire their shared rows in the same order and cannot deadlock by
 * grabbing them in opposite sequence.
 *
 * The contract
 * ------------
 * A lock only works if everyone takes it. Any new code path that writes an
 * appointment's provider/date/time MUST call this first, inside the same
 * transaction, and then re-check the conflict — the pre-transaction check is
 * only a fast rejection for the common case, never the guarantee.
 */
class BookingLockService
{
    /**
     * Take row locks on the given users, in a deterministic order.
     *
     * Must be called inside a transaction; outside one the lock is released
     * immediately and buys nothing.
     *
     * @param  array<int|string|null>  $userIds  provider ids, customer id — nulls
     *                                           and duplicates are ignored, so
     *                                           guest bookings can pass a null
     *                                           customer id straight through.
     */
    public function lockUsers(array $userIds): void
    {
        $ids = collect($userIds)
            ->filter()          // drop nulls (guest bookings have no customer row)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()            // ← the deadlock-avoidance step
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        // Deliberately a raw locking read rather than an Eloquent get(): we want
        // the lock, not the models, and hydrating User objects here would only
        // add cost inside the critical section.
        DB::table('users')
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }
}
