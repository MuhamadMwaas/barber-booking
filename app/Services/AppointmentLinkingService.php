<?php

namespace App\Services;

use App\Models\Appointment;
use InvalidArgumentException;

/**
 * AppointmentLinkingService
 *
 * Owns the rules for the parent ↔ children relationship between
 * appointments. The invariant we protect:
 *
 *   - SINGLE LEVEL: a parent has children, but a child cannot itself
 *     have children. If staff tries to add a service to a child via a
 *     different provider, we always link the new appointment to the
 *     ORIGINAL parent (flattening) — never to another child.
 *
 *   - SAME DATE: parent and child must share appointment_date.
 *
 *   - SAME CUSTOMER: customer_id (or guest contact) propagates from
 *     parent to child.
 *
 *   - INVOICE-OWNED-BY-PARENT: only the parent (or a standalone) carries
 *     an Invoice. Children have NO invoice of their own.
 */
class AppointmentLinkingService
{
    /**
     * Resolve the root of a linked group.
     *  - If $appointment is a child → return its parent.
     *  - Otherwise → return $appointment.
     *
     * Use this whenever you need "the appointment that owns the invoice".
     */
    public function getInvoiceOwner(Appointment $appointment): Appointment
    {
        return $appointment->parent ?? $appointment;
    }

    /**
     * Validate the parent ↔ child invariants before creating a child appointment.
     * The actual write (setting parent_appointment_id) is done by the caller.
     *
     * @throws InvalidArgumentException
     */
    public function validateChildCandidate(Appointment $parent, array $childData): void
    {
        // Parent must not itself be a child (single-level enforcement).
        if ($parent->parent_appointment_id !== null) {
            throw new InvalidArgumentException(
                'Cannot create a grandchild appointment. Single-level hierarchy enforced. ' .
                'Link to the original parent instead.'
            );
        }

        // Parent must still accept new services (status / payment / invoice gating).
        if (! $parent->canAcceptNewService()) {
            throw new InvalidArgumentException(
                'Parent appointment no longer accepts new services. ' .
                '(Likely paid, completed, or cancelled.)'
            );
        }

        // Same date.
        $parentDate = $parent->appointment_date->format('Y-m-d');
        $childDate  = isset($childData['appointment_date'])
            ? \Carbon\Carbon::parse($childData['appointment_date'])->format('Y-m-d')
            : null;

        if ($childDate !== null && $childDate !== $parentDate) {
            throw new InvalidArgumentException('Child appointment must be on the same date as parent.');
        }
    }

    /**
     * Set parent_appointment_id on an already-persisted child.
     * Used by BookingService when it has just created a new child via Appointment::create()
     * without the FK and now wants to attach it.
     *
     * @throws InvalidArgumentException
     */
    public function linkAsChild(Appointment $child, Appointment $parent): void
    {
        $this->validateChildCandidate($parent, [
            'appointment_date' => $child->appointment_date,
        ]);

        if ($child->parent_appointment_id === $parent->id) {
            return; // already linked
        }

        $child->update(['parent_appointment_id' => $parent->id]);
    }

    /**
     * Validation called from BookingService::addServiceToBooking() when the
     * anchor is itself a child appointment and the staff is adding another
     * service.
     *
     * Business rule confirmed with stakeholder:
     *   "نضيفها للحجز الابن — هما جزءين متصلين ببعض بس كل واحد له خدماته ومقدمه"
     *
     * → A new service on the child stays on the child; no further hierarchy.
     */
    public function validateAddServiceToChild(Appointment $child): void
    {
        if (! $child->is_child_booking) {
            throw new InvalidArgumentException('Appointment is not a child of any parent.');
        }

        if (! $child->canAcceptNewService()) {
            throw new InvalidArgumentException('This child appointment no longer accepts new services.');
        }

        $parent = $child->parent;
        if (! $parent) {
            throw new InvalidArgumentException('Child has no resolvable parent (data integrity error).');
        }

        if (! $parent->canAcceptNewService()) {
            throw new InvalidArgumentException(
                'Parent appointment has been paid/completed. Cannot modify children.'
            );
        }
    }
}
