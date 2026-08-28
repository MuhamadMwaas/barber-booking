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
 */
class EnsureCanViewAdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
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
