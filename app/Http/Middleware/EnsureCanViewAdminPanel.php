<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces `StaffDashboard:view_admin` on the Filament panel itself.
 *
 * Without this the permission was cosmetic: it hid the "Admin" tab in
 * {@see resources/views/partials/staff-nav.blade.php} but nothing stopped a
 * staff member from typing /admin directly, because
 * {@see \App\Models\User::canAccessPanel()} only checks the ROLE
 * (SuperAdmin/admin/manager/provider), never the permission.
 *
 * It deliberately lives in the panel's `authMiddleware` rather than in
 * `canAccessPanel()`: that method also guards the LOGIN response, so failing it
 * would 403 the user out of logging in at all — locking them out of the Staff
 * Dashboard too, which is not what revoking "view admin" should mean.
 *
 * Denied users are redirected to the Staff Dashboard instead of being shown a
 * dead end, unless they cannot access that either.
 *
 * ── Why LOGOUT is exempt ─────────────────────────────────────────────────────
 *
 * The same reasoning that keeps this check out of canAccessPanel() applies to
 * signing OUT, and was originally missed. The Staff Dashboard is not a Filament
 * panel and has no logout route of its own — its logout button posts to the
 * PANEL's `filament.admin.auth.logout`, which lives inside `authMiddleware` and
 * therefore ran through this class. For a provider whose `view_admin` had been
 * revoked the branch below matched, the request was redirected back to the
 * dashboard, and Filament's logout handler NEVER RAN: the session stayed alive
 * and the logout button silently did nothing. Revoking a "may view /admin"
 * permission must never be able to trap someone in a session they cannot end.
 *
 * The pattern is matched (not the literal `filament.admin.*`) so a second panel
 * cannot reintroduce the trap. Ending a session is never something a *viewing*
 * permission may gate, on any panel.
 */
class EnsureCanViewAdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        // Signing out is always allowed — see the class docblock.
        if ($request->routeIs('filament.*.auth.logout')) {
            return $next($request);
        }

        $user = filament()->auth()->user() ?? $request->user();

        if (! $user) {
            return $next($request);
        }

        // SuperAdmin always keeps the panel — matches the bypass used by
        // ChecksPermissions::allowed() and InteractsWithDashboardPermissions.
        if ($user->hasRole('SuperAdmin') || $user->can('StaffDashboard:view_admin')) {
            return $next($request);
        }

        // Send them somewhere useful rather than a bare 403, but only if the
        // Staff Dashboard is actually open to them.
        if ($user->can('StaffDashboard:access')) {
            return redirect()->route('staff.dashboard');
        }

        abort(403);
    }
}
