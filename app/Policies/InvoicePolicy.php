<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * AUTHZ-03 — who may see or print an invoice.
 *
 * Every print entry point used to check only "are you logged in?", so any
 * employee (and, over the API, any customer) could print any invoice in the
 * salon by changing the id in the URL. `auth` answers "who are you?"; this
 * policy answers "is this invoice yours to touch?" — and every route that takes
 * an invoice id must ask it before doing any work.
 *
 * Two different acts, deliberately kept apart:
 *
 *  - view  — a CUSTOMER reading their own receipt from the app. Read-only: it
 *            never increments print_count, never writes a PrintLog, and is
 *            rendered as a copy (see Invoice::$renderAsCustomerCopy).
 *  - print — STAFF producing the receipt at the salon. It has side effects
 *            (PrintLog, print_count, the "Kopie N" label), so it is staff-only.
 */
class InvoicePolicy
{
    /** Print invoices for bookings the user served. */
    public const PRINT = 'Invoice:print';

    /**
     * Print invoices for bookings served by someone else. Deliberately separate
     * from `StaffDashboard:edit_others`: being allowed to move a colleague's
     * booking is not the same as reading their customer's bill.
     */
    public const PRINT_OTHERS = 'Invoice:print_others';

    /**
     * A customer's own, PAID invoice. Ownership is the whole rule — no permission
     * can grant it and none can take it away, so no role (SuperAdmin included)
     * reaches another person's receipt through this ability.
     *
     * Drafts are excluded: they still change (services added, discounts) and are
     * not a document the salon has issued.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->customer_id !== null
            && (int) $invoice->customer_id === (int) $user->id
            && $invoice->status?->isPaid() === true;
    }

    public function print(User $user, Invoice $invoice): bool
    {
        // is_active matters: a deactivated employee keeps a valid web session
        // until it expires (AUTHZ-01).
        if (! $user->isActiveStaff()) {
            return false;
        }

        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        if (! $user->can(self::PRINT)) {
            return false;
        }

        return $user->can(self::PRINT_OTHERS)
            || $this->collectedBy($user, $invoice)
            || $this->servedBy($user, $invoice);
    }

    /**
     * Did this user take the payment? A provider with `take_payment` may cash a
     * colleague's customer, and whoever takes the money must be able to hand
     * over the receipt (Belegausgabepflicht) — they have just seen the whole
     * bill anyway. Invoices finalized before `finalized_by_id` existed fall back
     * to the other rules.
     */
    private function collectedBy(User $user, Invoice $invoice): bool
    {
        $collectorId = $invoice->invoice_data['finalized_by_id'] ?? null;

        return $collectorId !== null && (int) $collectorId === (int) $user->id;
    }

    /**
     * Did this user serve any booking the invoice covers?
     *
     * The invoice lives on the root of a split-booking group, but a provider who
     * worked only a child segment still served that customer, so the whole
     * active group counts — not just `invoice->appointment->provider_id`.
     * Cancelled / no-show segments are not on the invoice, so they don't count.
     */
    private function servedBy(User $user, Invoice $invoice): bool
    {
        $root = $invoice->appointment;

        if (! $root) {
            return false;
        }

        return $root->activeLinkedGroup()
            ->where('provider_id', $user->id)
            ->exists();
    }
}
