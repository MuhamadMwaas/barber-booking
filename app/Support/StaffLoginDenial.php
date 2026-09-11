<?php

namespace App\Support;

use App\Models\User;

/**
 * THE single answer to "may this account SIGN IN to a staff surface?"
 *
 * The companion to {@see \App\Models\User::isActiveStaff()}, which answers "may
 * this account USE a staff surface?". Both surfaces — the Filament panel login
 * and the Staff Dashboard login — must ask this class, for exactly the reason
 * `isActiveStaff()` exists: the same question answered twice in two files drifts,
 * and an authorisation rule that drifts is an authorisation rule that is missing
 * somewhere.
 *
 * `for()` returns null precisely when `isActiveStaff()` is true. The two are
 * asserted equivalent in StaffAuthTest so they cannot separate; this class exists
 * on top of it only to say WHY access was refused, because "your account is
 * disabled" and "this is a customer account" need different messages and a bare
 * boolean cannot carry that.
 *
 * Note what is NOT here: `StaffDashboard:access`, `view_admin`, or any other
 * Spatie permission. Those govern which SURFACE an authenticated employee may
 * open, and are enforced by EnsureStaffDashboardAccess / EnsureCanViewAdminPanel
 * once they are in. Folding a per-surface permission into the sign-in decision is
 * what produced the original bug: revoking a viewing permission is not supposed
 * to be able to lock somebody out of authenticating at all.
 */
final class StaffLoginDenial
{
    /** Account exists and the password is right, but `is_active` is false. */
    public const DISABLED = 'disabled';

    /** A valid account with no staff role — a customer, or a role-less user. */
    public const NOT_STAFF = 'not_staff';

    /**
     * Why this user may not sign in to a staff surface, or null if they may.
     *
     * Order matters: a deactivated employee is told their account is disabled
     * (actionable — talk to management) rather than that they are not staff.
     */
    public static function for(User $user): ?string
    {
        if (! $user->is_active) {
            return self::DISABLED;
        }

        // Deliberately `! hasStaffRole()` and not `hasRole('customer')`, which is
        // what the Filament login checked before this class. A user with NO role
        // at all passed that check and then failed canAccessPanel(), so they were
        // shown "these credentials do not match our records" — a lie that sends
        // them to reset a password that was never the problem.
        if (! $user->hasStaffRole()) {
            return self::NOT_STAFF;
        }

        return null;
    }

    public static function title(string $reason): string
    {
        return $reason === self::DISABLED
            ? __('auth.account_disabled_title')
            : __('auth.customer_not_allowed_title');
    }

    public static function body(string $reason): string
    {
        return $reason === self::DISABLED
            ? __('auth.account_disabled_body')
            : __('auth.customer_not_allowed_body');
    }
}
