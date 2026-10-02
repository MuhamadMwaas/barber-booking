<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * AUTHZ-03 — who may print an appointment's work ticket (`/appointment/{id}/print`).
 *
 * The ticket is not an invoice, but it carries the customer's name and phone,
 * and the route used to check only "are you logged in?".
 *
 * Registered as the `printAppointmentTicket` Gate ability in AppServiceProvider,
 * NOT as an `AppointmentPolicy`: Filament 4 picks up any policy named after a
 * resource's model and would then deny `viewAny`/`update`/… on the whole
 * Appointments resource because this class does not define them.
 *
 * The rule mirrors the Staff Dashboard, which is where the button lives: the
 * `print_ticket` switch, plus — for providers — the booking must be theirs or
 * they must already be allowed to see the team's columns (`view_team`), since
 * that is exactly the set of bookings the board shows them anyway.
 */
class AppointmentTicketPolicy
{
    public const ABILITY = 'printAppointmentTicket';

    public function print(User $user, Appointment $appointment): bool
    {
        if (! $user->isActiveStaff()) {
            return false;
        }

        if ($user->hasRole('SuperAdmin')) {
            return true;
        }

        if (! $user->can('StaffDashboard:print_ticket')) {
            return false;
        }

        // Admins / managers are not bound by ownership on the dashboard either
        // (InteractsWithDashboardPermissions::canActOnAppointment).
        if (! $user->isProvider()) {
            return true;
        }

        if ($user->can('StaffDashboard:view_team')) {
            return true;
        }

        // The ticket prints the whole linked group, so serving any segment of it
        // is enough.
        return $appointment->linkedGroup()
            ->where('provider_id', $user->id)
            ->exists();
    }
}
