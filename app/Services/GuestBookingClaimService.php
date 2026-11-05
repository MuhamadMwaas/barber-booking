<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands a customer the bookings staff made for them as a guest.
 *
 * At the counter the staff often books a walk-in as a guest (customer_id NULL,
 * contact kept in customer_name / customer_phone). When that person later opens
 * an account and PROVES the number with an SMS OTP, every guest booking under the
 * same number becomes theirs — appointments and their invoices, paid ones
 * included, so the history and receipts show up in the app.
 *
 * Rules:
 *  - Only a VERIFIED phone claims anything. An unverified number is just text the
 *    user typed; claiming on it would hand a stranger someone else's history.
 *  - Numbers are compared through PhoneNumber::key(), the same rule the overlap
 *    guard uses, so `0176 1234567` typed at the counter matches `+49176…`.
 *  - Only guest rows (customer_id NULL) move. A booking that already belongs to
 *    an account is never re-assigned, and neither is one left behind by a
 *    deleted account (customer_account_deleted_at set).
 *  - The raw guest columns are kept as typed — they are what the salon recorded.
 *  - Idempotent: running it twice links nothing the second time.
 */
class GuestBookingClaimService
{
    /**
     * Link every guest booking under $user's verified phone to $user.
     *
     * @return int number of appointments linked
     */
    public function claimFor(User $user): int
    {
        if ($user->phone_verified_at === null) {
            return 0;
        }

        $key = PhoneNumber::key($user->phone);
        if ($key === null) {
            return 0;
        }

        $ids = $this->guestAppointmentIdsByKey()[$key] ?? [];

        return $this->link($user, $ids);
    }

    /**
     * Backfill for accounts verified before this feature existed. Numbers shared
     * by more than one verified account are skipped — there is no telling whose
     * bookings they are.
     *
     * @return array<int, int> user id => appointments linked
     */
    public function claimForAllVerifiedUsers(): array
    {
        $usersByKey = [];
        User::query()
            ->whereNotNull('phone_verified_at')
            ->whereNotNull('phone')
            ->select(['id', 'phone', 'phone_verified_at'])
            ->each(function (User $user) use (&$usersByKey) {
                $key = PhoneNumber::key($user->phone);
                if ($key !== null) {
                    $usersByKey[$key][] = $user;
                }
            });

        $linked = [];
        foreach ($this->guestAppointmentIdsByKey() as $key => $ids) {
            $owners = $usersByKey[$key] ?? [];
            if (count($owners) !== 1) {
                continue;
            }

            $count = $this->link($owners[0], $ids);
            if ($count > 0) {
                $linked[$owners[0]->id] = $count;
            }
        }

        return $linked;
    }

    /**
     * Guest appointment ids grouped by phone key.
     *
     * The key is computed in PHP (phone numbers are stored as typed, so SQL
     * cannot normalise them portably); only id + phone are loaded, in chunks.
     *
     * @return array<string, list<int>>
     */
    private function guestAppointmentIdsByKey(): array
    {
        $byKey = [];

        Appointment::query()
            ->whereNull('customer_id')
            // Left behind by a deleted account, not made at the counter: they
            // belong to nobody now, least of all the next owner of the number.
            ->whereNull('customer_account_deleted_at')
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->select(['id', 'customer_phone'])
            ->lazyById(1000)
            ->each(function (Appointment $appointment) use (&$byKey) {
                $key = PhoneNumber::key($appointment->getRawOriginal('customer_phone'));
                if ($key !== null) {
                    $byKey[$key][] = $appointment->id;
                }
            });

        return $byKey;
    }

    /** @param list<int> $appointmentIds */
    private function link(User $user, array $appointmentIds): int
    {
        if ($appointmentIds === []) {
            return 0;
        }

        $linked = DB::transaction(function () use ($user, $appointmentIds) {
            $linked = 0;

            foreach (array_chunk($appointmentIds, 500) as $chunk) {
                // `whereNull` again: a row claimed since we read it stays put.
                $linked += Appointment::query()
                    ->whereIn('id', $chunk)
                    ->whereNull('customer_id')
                    ->update(['customer_id' => $user->id]);

                Invoice::query()
                    ->whereIn('appointment_id', $chunk)
                    ->whereNull('customer_id')
                    ->update(['customer_id' => $user->id]);
            }

            return $linked;
        });

        if ($linked > 0) {
            Log::info('Guest bookings claimed by verified phone', [
                'user_id' => $user->id,
                'appointments' => $linked,
            ]);
        }

        return $linked;
    }
}
