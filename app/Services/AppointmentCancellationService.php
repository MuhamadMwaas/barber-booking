<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * THE single way an appointment is cancelled — BOOKING-GAP-01.
 *
 * A multi-service booking with a gap, or with more than one provider, is stored
 * as a GROUP: one appointment per contiguous block, the earliest one the root
 * (it owns the draft invoice), the others its children. Each block can be
 * cancelled on its own — the customer keeps the afternoon and drops the
 * morning — so a cancellation is no longer a one-column write:
 *
 *  - cancelling a CHILD rebuilds the root's invoice without that block;
 *  - cancelling the ROOT while other blocks still stand PROMOTES the earliest
 *    standing block to root: the draft invoice moves to it and every other
 *    member (the cancelled old root included) is re-linked under it, so the
 *    invoice always lives on an appointment that is actually going to happen;
 *  - cancelling a standalone appointment is what it always was.
 *
 * The old root is re-linked rather than detached so it stays visible as part
 * of the same booking — and so COALESCE(parent_appointment_id, id) still puts
 * every block in one group, which is what the daily limit and the repeat-
 * cancellation alert count.
 *
 * Every cancel surface comes through here: the customer's two API endpoints
 * (via Appointment::cancel()), the StaffDashboard, and the Filament table.
 *
 * Locks are taken in the same order as InvoiceFinalizationService and
 * AppointmentDeletionService — group root first, then the group by id — so a
 * payment, a delete and a cancel on one group queue instead of deadlocking.
 */
class AppointmentCancellationService
{
    private const CANCEL_STATUSES = [
        AppointmentStatus::USER_CANCELLED,
        AppointmentStatus::ADMIN_CANCELLED,
    ];

    private const PAID_STATES = [
        PaymentStatus::PAID_ONLINE,
        PaymentStatus::PAID_ONSTIE_CASH,
        PaymentStatus::PAID_ONSTIE_CARD,
    ];

    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * @return Appointment the cancelled appointment, re-read after the write.
     *
     * @throws InvalidArgumentException when the appointment is not a pending,
     *                                  unpaid booking any more.
     */
    public function cancel(Appointment $appointment, AppointmentStatus $status, ?string $reason = null): Appointment
    {
        if (! in_array($status, self::CANCEL_STATUSES, true)) {
            throw new InvalidArgumentException("{$status->name} is not a cancellation status.");
        }

        $cancelled = DB::transaction(function () use ($appointment, $status, $reason) {
            $group = $this->lockGroup($appointment);

            /** @var Appointment|null $target */
            $target = $group->firstWhere('id', $appointment->id);

            // Re-checked on the locked row: the caller's copy may be stale — a
            // payment or another cancel can have committed since it was read.
            if (! $target
                || $target->status !== AppointmentStatus::PENDING
                || in_array($target->payment_status, self::PAID_STATES, true)) {
                throw new InvalidArgumentException(__('booking.only_pending_can_be_cancelled'));
            }

            $target->update([
                'status' => $status,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->reorganiseGroup($target, $group);

            return $target->fresh();
        });

        if ($status === AppointmentStatus::USER_CANCELLED) {
            // After commit, and it swallows its own failures: an alerting
            // problem must never undo a cancellation that already happened.
            app(CancellationMonitor::class)->recordCustomerCancellation($cancelled);
        }

        return $cancelled;
    }

    /**
     * @return Collection<int, Appointment> every member of the group, locked.
     */
    private function lockGroup(Appointment $appointment): Collection
    {
        $rootId = Appointment::query()
            ->whereKey($appointment->id)
            ->value(DB::raw('COALESCE(parent_appointment_id, id)'));

        if ($rootId === null) {
            return collect();
        }

        Appointment::query()->whereKey($rootId)->lockForUpdate()->get(['id']);

        return Appointment::query()
            ->where(fn ($query) => $query
                ->where('id', $rootId)
                ->orWhere('parent_appointment_id', $rootId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Bring the group back to a valid shape after $cancelled left it.
     *
     * @param  Collection<int, Appointment>  $group  locked, as read before the write
     */
    private function reorganiseGroup(Appointment $cancelled, Collection $group): void
    {
        $standing = $group
            ->reject(fn (Appointment $member) => $member->id === $cancelled->id)
            ->filter(fn (Appointment $member) => $member->isActiveInGroup());

        if ($cancelled->parent_appointment_id !== null) {
            // A child left. The root keeps the invoice; drop the child from it —
            // unless the root is itself gone, in which case there is nothing
            // left that will be billed through that invoice.
            $root = $group->firstWhere('id', $cancelled->parent_appointment_id);

            if ($root && $root->isActiveInGroup()) {
                $this->invoiceService->rebuildAggregatedInvoice($root);
            }

            return;
        }

        // The root left. Standalone, or every other block already cancelled:
        // nothing to hand over — the booking is simply cancelled, as before.
        if ($standing->isEmpty()) {
            return;
        }

        $this->promoteNewRoot($cancelled, $group, $standing);
    }

    /**
     * @param  Collection<int, Appointment>  $group  every member, locked
     * @param  Collection<int, Appointment>  $standing  members still active
     */
    private function promoteNewRoot(Appointment $oldRoot, Collection $group, Collection $standing): void
    {
        /** @var Appointment $newRoot */
        $newRoot = $standing
            ->sortBy(fn (Appointment $member) => [$member->start_time->getTimestamp(), $member->id])
            ->first();

        $newRoot->update(['parent_appointment_id' => null]);

        // Everyone else — the cancelled old root included — hangs under the
        // new root. One UPDATE; the hierarchy stays single-level.
        Appointment::query()
            ->whereIn('id', $group->pluck('id')->reject(fn ($id) => $id === $newRoot->id)->all())
            ->update(['parent_appointment_id' => $newRoot->id]);

        $invoice = Invoice::query()
            ->where('appointment_id', $oldRoot->id)
            ->lockForUpdate()
            ->first();

        if ($invoice) {
            // A draft has no number yet, so re-pointing it is invisible to the
            // numbering and to every report. A finalized one would mean the
            // group was paid — and a paid appointment was refused above.
            if ($invoice->status !== InvoiceStatus::DRAFT) {
                throw new InvalidArgumentException(__('booking.only_pending_can_be_cancelled'));
            }

            $invoice->update([
                'appointment_id' => $newRoot->id,
                'customer_id' => $newRoot->customer_id,
            ]);
        }

        $this->invoiceService->rebuildAggregatedInvoice($newRoot->fresh());

        Log::info('Group root cancelled; next block promoted', [
            'old_root_id' => $oldRoot->id,
            'new_root_id' => $newRoot->id,
            'invoice_id' => $invoice?->id,
        ]);
    }
}
