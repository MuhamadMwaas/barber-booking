<?php

namespace App\Livewire\Concerns;

use App\Models\Appointment;

/**
 * Authorization helpers for the StaffDashboard Livewire component.
 *
 * Every actionable button on the dashboard maps to a `StaffDashboard:<ability>`
 * Spatie permission (see PermissionsSeeder). These helpers are used both:
 *   - in Blade, to hide buttons the user is not allowed to use, and
 *   - in the action methods, to hard-stop a forbidden action server-side
 *     (so hiding a button is never the only line of defence).
 *
 * Ownership: providers may always act on their own bookings. Acting on a
 * booking that belongs to another provider additionally requires the
 * `edit_others` ability. Admins/managers are never constrained by ownership.
 */
trait InteractsWithDashboardPermissions
{
    protected function dashUser()
    {
        return auth()->user();
    }

    /** Is the logged-in user a service provider (vs admin/manager)? */
    public function isCurrentUserProvider(): bool
    {
        $user = $this->dashUser();

        return $user && method_exists($user, 'isProvider') && $user->isProvider();
    }

    /** The provider id to scope "my bookings" to, or null for non-providers. */
    public function currentProviderId(): ?int
    {
        return $this->isCurrentUserProvider() ? (int) $this->dashUser()->id : null;
    }

    /** Check a StaffDashboard ability (SuperAdmin bypasses everything). */
    public function dashCan(string $ability): bool
    {
        $user = $this->dashUser();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('SuperAdmin')) {
            return true;
        }

        return $user->can('StaffDashboard:' . $ability);
    }

    /**
     * Server-side guard: returns true (and notifies the user) when the action
     * must be blocked. Usage: `if ($this->dashDeny('take_payment')) return;`
     */
    protected function dashDeny(string $ability): bool
    {
        if ($this->dashCan($ability)) {
            return false;
        }

        $this->dispatch('notify', type: 'error', message: __('dashboard.permission_denied'));

        return true;
    }

    /** May the current user act on this specific appointment (ownership rule)? */
    public function canActOnAppointment(?Appointment $appointment): bool
    {
        if (! $appointment) {
            return false;
        }

        // Admins / managers are not constrained by ownership.
        if (! $this->isCurrentUserProvider()) {
            return true;
        }

        if ((int) $appointment->provider_id === $this->currentProviderId()) {
            return true;
        }

        return $this->dashCan('edit_others');
    }

    /**
     * Combined guard for actions that target an existing appointment: checks the
     * ability AND ownership. Returns true when the action must be blocked.
     */
    protected function dashDenyOnAppointment(string $ability, ?Appointment $appointment): bool
    {
        if ($this->dashDeny($ability)) {
            return true;
        }

        if (! $this->canActOnAppointment($appointment)) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.not_your_booking_denied'));

            return true;
        }

        return false;
    }
}
