<?php

namespace App\Console\Commands;

use App\Services\GuestBookingClaimService;
use Illuminate\Console\Command;

/**
 * One-off backfill: accounts whose phone was verified before guest bookings were
 * claimed automatically never got their counter bookings. Safe to re-run.
 */
class ClaimGuestBookingsCommand extends Command
{
    protected $signature = 'appointments:claim-guest-bookings';

    protected $description = 'Link guest bookings to the verified accounts that own the same phone number';

    public function handle(GuestBookingClaimService $service): int
    {
        $linked = $service->claimForAllVerifiedUsers();

        foreach ($linked as $userId => $count) {
            $this->line("  user #{$userId}: {$count} appointment(s)");
        }

        $this->info(sprintf(
            'Linked %d appointment(s) to %d account(s).',
            array_sum($linked),
            count($linked),
        ));

        return self::SUCCESS;
    }
}
