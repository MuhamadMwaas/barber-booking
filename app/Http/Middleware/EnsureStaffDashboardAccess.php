<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ONLY gate in front of the Staff Dashboard surface
 * (dashboard.lookupfriseur.com/* — see routes/web.php).
 *
 * ── Why this class has to repeat what canAccessPanel() does ──────────────────
 *
 * The Staff Dashboard is NOT a Filament panel path. It is a group of ordinary
 * web routes, so {@see \App\Models\User::canAccessPanel()} — where the
 * `is_active` check lives for /admin — is never invoked here. Before AUTHZ-01
 * this middleware checked only the `StaffDashboard:access` permission, which
 * meant a DEACTIVATED employee kept a fully working dashboard: today's bookings,
 * the whole customer database (CustomerLookup), payment collection and booking
 * deletion. Setting `is_active = false` in /admin/users locked them out of
 * /admin and nothing else — a false sense of having revoked access, which is
 * more dangerous than having no kill switch at all.
 *
 * Both surfaces now answer the question through the SAME method,
 * {@see \App\Models\User::isActiveStaff()}, so they cannot drift apart again.
 * Any future staff surface must call that method too.
 *
 * ── Why deactivation LOGS OUT instead of returning 403 ───────────────────────
 *
 * A 403 leaves the session alive; the employee keeps a valid cookie and only
 * has to wait for someone to flip the flag back, or to find a surface that
 * forgot to check. Destroying the session makes the revocation immediate and
 * total. {@see \App\Observers\UserObserver} does the same thing proactively at
 * the moment the flag is flipped (sessions row, Sanctum tokens, refresh tokens,
 * remember-me token); this branch is the belt to that suspenders — it catches a
 * flag flipped by a raw SQL update, a seeder, or an import that bypasses model
 * events.
 *
 * ── Why it is also registered as Livewire PERSISTENT middleware ──────────────
 *
 * Route middleware runs on the full page load only. Every dashboard ACTION
 * (processPayment, deleteAppointment, the customer search) is a
 * `POST /livewire/update` against a different route that carries just the `web`
 * group — no auth, no permission check. Livewire re-runs only middleware that
 * has been declared persistent, so without the
 * `Livewire::addPersistentMiddleware()` call in AppServiceProvider a fired
 * employee with the tab already open could keep taking money through the AJAX
 * endpoint even though this class rejects their next page load.
 */
class EnsureStaffDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = filament()->auth();

        // The dashboard's OWN login, on the dashboard's OWN domain. It used to
        // send guests to the Filament panel's login instead — a route that is not
        // domain-bound and so resolves against APP_URL, i.e. the MAIN domain.
        // That only works while the session cookie is shared across the parent
        // domain; without it the bounce becomes a loop the employee cannot
        // escape. See App\Http\Controllers\Auth\StaffAuthController.
        if (! $auth->check()) {
            return redirect()->guest(route('staff.dashboard.login'));
        }

        $user = $auth->user();

        // ── Disabled account: eject, do not merely deny ──────────────────────
        if (! $user->is_active) {
            return $this->ejectDisabledUser($request, $auth);
        }

        // ── Role gate. Mirrors canAccessPanel(); defence in depth against a
        //    non-staff role that somehow holds the permission (which is exactly
        //    what the removed GET /grant-view-stats route used to cause). ──────
        if (! $user->hasStaffRole()) {
            abort(403);
        }

        if (! $user->can('StaffDashboard:access')) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * Kill the session of a deactivated employee and send them to the dashboard
     * login page with the same message the Filament login form shows.
     *
     * Order matters: invalidate() flushes the session before migrating to a new
     * id, so the message must be flashed AFTER it or it would be wiped.
     *
     * A session flash, not a Filament Notification: the destination is now the
     * dashboard's own Blade login page, which has no Filament notification
     * component to render one into. A notification nobody can see is the same as
     * telling a locked-out employee nothing.
     */
    private function ejectDisabledUser(Request $request, $auth): Response
    {
        // Also cycles the remember-me token, so the recaller cookie cannot
        // silently log them straight back in on the next request.
        $auth->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->flash('status', __('auth.account_disabled_body'));
        }

        return redirect()->route('staff.dashboard.login');
    }
}
