<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\AppointmentNotDeletableException;
use App\Models\Appointment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * THE single way an appointment is deleted — DEL-01.
 *
 * Every surface that deletes a booking (the Filament edit page, the Filament
 * bulk action, the StaffDashboard modal) MUST come through here. Before this
 * existed the StaffDashboard carried the only correct implementation while
 * Filament called a bare `$record->delete()`, which MySQL refused: both
 * `appointment_services.appointment_id` and `invoices.appointment_id` are
 * RESTRICT foreign keys. Those constraints stay RESTRICT on purpose — they are
 * the safety net that stops any future code path from silently dropping an
 * invoice — so the children are removed explicitly, here, in one transaction.
 *
 * The rules (unchanged from the StaffDashboard):
 *  - a paid appointment is never deleted (it is a financial record);
 *  - a completed appointment is never deleted (the service happened);
 *  - a parent with a child that is not cancelled is never deleted (the child's
 *    services live on the parent's invoice).
 * One addition: an appointment whose invoice is no longer DRAFT counts as paid
 * even if payment_status disagrees, because a finalized invoice carries a
 * sequential number and deleting it would leave a gap in the numbering.
 *
 * All-or-nothing: if any appointment in the request is blocked, nothing is
 * deleted and the exception lists every blocked one.
 */
class AppointmentDeletionService
{
    private const PAID_STATES = [
        PaymentStatus::PAID_ONLINE,
        PaymentStatus::PAID_ONSTIE_CASH,
        PaymentStatus::PAID_ONSTIE_CARD,
    ];

    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * @throws AppointmentNotDeletableException
     */
    public function delete(Appointment $appointment): void
    {
        $this->deleteMany([$appointment]);
    }

    /**
     * @param  iterable<Appointment|int>  $appointments
     * @return int How many appointments were deleted.
     *
     * @throws AppointmentNotDeletableException  Nothing was deleted.
     */
    public function deleteMany(iterable $appointments): int
    {
        $ids = collect($appointments)
            ->map(fn ($appointment) => $appointment instanceof Appointment ? $appointment->getKey() : (int) $appointment)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $parentsToRebuild = [];

        $deleted = DB::transaction(function () use ($ids, &$parentsToRebuild) {
            $rows = $this->lockAffectedGroups($ids);

            // An id missing here was deleted by someone else in the meantime —
            // the caller's intent is already satisfied, so it is not an error.
            $targets = $rows->only($ids->all());

            $this->assertDeletable($targets, $rows);

            foreach ($targets as $appointment) {
                $this->deleteOne($appointment);
            }

            // A deleted child's services are still on its parent's aggregated
            // invoice. Parents deleted in this same request need no rebuild.
            $parentsToRebuild = $targets->pluck('parent_appointment_id')
                ->filter()
                ->unique()
                ->diff($ids)
                ->values()
                ->all();

            return $targets->count();
        });

        foreach ($parentsToRebuild as $parentId) {
            $this->rebuildParentInvoice($parentId);
        }

        return $deleted;
    }

    /**
     * Lock every appointment the rules will look at: each target's whole
     * parent/children group.
     *
     * Same order as InvoiceFinalizationService::finalizeAppointmentPayment():
     * the invoice-owner (group root) rows first, then the group ordered by id.
     * Taking the locks in the same order means a payment and a delete on the
     * same group queue behind each other instead of deadlocking — and because
     * the rules are checked on the locked rows, a payment that commits a moment
     * before the delete is seen, so a just-paid invoice is never deleted.
     *
     * @return Collection<int, Appointment> keyed by id
     */
    private function lockAffectedGroups(Collection $ids): Collection
    {
        $roots = Appointment::query()
            ->whereKey($ids->all())
            ->get(['id', 'parent_appointment_id'])
            ->map(fn (Appointment $appointment) => $appointment->parent_appointment_id ?? $appointment->id)
            ->unique()
            ->values();

        if ($roots->isEmpty()) {
            return collect();
        }

        Appointment::query()
            ->whereKey($roots->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        return Appointment::query()
            ->where(fn ($query) => $query
                ->whereIn('id', $roots->all())
                ->orWhereIn('parent_appointment_id', $roots->all()))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Appointment>  $targets
     * @param  Collection<int, Appointment>  $rows  every locked row, for the children check
     *
     * @throws AppointmentNotDeletableException
     */
    private function assertDeletable(Collection $targets, Collection $rows): void
    {
        $blocked = [];

        foreach ($targets as $appointment) {
            $appointment->setRelation(
                'invoice',
                $appointment->invoice()->lockForUpdate()->first()
            );
            $appointment->setRelation(
                'children',
                $rows->where('parent_appointment_id', $appointment->id)->values()
            );

            $reason = $this->blockingReason($appointment);

            if ($reason !== null) {
                $blocked[] = $reason;
            }
        }

        if ($blocked !== []) {
            throw new AppointmentNotDeletableException($blocked);
        }
    }

    /**
     * @return array{number: string, reason: string, children_numbers: array<int, string>}|null
     */
    private function blockingReason(Appointment $appointment): ?array
    {
        $blocked = fn (string $reason, array $childrenNumbers = []) => [
            'number' => (string) $appointment->number,
            'reason' => $reason,
            'children_numbers' => $childrenNumbers,
        ];

        $invoice = $appointment->invoice;

        if (in_array($appointment->payment_status, self::PAID_STATES, true)
            || ($invoice && $invoice->status !== InvoiceStatus::DRAFT)) {
            return $blocked(AppointmentNotDeletableException::REASON_PAID);
        }

        if ($appointment->status === AppointmentStatus::COMPLETED) {
            return $blocked(AppointmentNotDeletableException::REASON_COMPLETED);
        }

        $check = $appointment->canBeCancelledOrDeleted();

        if (! $check['allowed']) {
            return $blocked(
                AppointmentNotDeletableException::REASON_HAS_ACTIVE_CHILDREN,
                $check['children_numbers'] ?? []
            );
        }

        return null;
    }

    /**
     * Children first, in the order the RESTRICT foreign keys demand:
     * invoice_items → invoice → appointment_services → appointment.
     * Reminders and colors go with the appointment (CASCADE); the `deleting`
     * hook retires the live reminder first, inside this same transaction.
     */
    private function deleteOne(Appointment $appointment): void
    {
        $invoice = $appointment->invoice;

        if ($invoice) {
            $invoice->items()->delete();
            $invoice->payments()->delete();
            $invoice->delete();
        }

        $appointment->services_record()->delete();
        $appointment->delete();
    }

    /**
     * Runs after commit, as the StaffDashboard always did: a failed rebuild is
     * logged, not rethrown, so it cannot resurrect a deletion that succeeded.
     */
    private function rebuildParentInvoice(int $parentId): void
    {
        try {
            $parent = Appointment::find($parentId);

            if ($parent) {
                $this->invoiceService->rebuildAggregatedInvoice($parent);
            }
        } catch (\Throwable $e) {
            Log::warning('Aggregated invoice rebuild after child delete failed', [
                'parent_appointment_id' => $parentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
