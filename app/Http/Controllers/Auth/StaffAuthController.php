<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\StaffLoginDenial;
use App\Support\ThrottleKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Sign-in and sign-out for the Staff Dashboard — its OWN, on its OWN domain.
 *
 * ── Why the dashboard needed its own auth at all ─────────────────────────────
 *
 * The Staff Dashboard is a group of plain web routes, not a Filament panel, but
 * it used to borrow the panel's auth routes wholesale: guests were bounced to
 * `filament.admin.auth.login` and the logout button posted to
 * `filament.admin.auth.logout`. Borrowing meant INHERITING the panel's
 * `authMiddleware`, and the day `EnsureCanViewAdminPanel` was added there,
 * revoking a provider's `StaffDashboard:view_admin` silently took away their
 * ability to LOG OUT — the redirect fired before Filament's logout handler ran,
 * so the button did nothing and the session survived. A permission about
 * *viewing /admin* could reach into *ending a session*, because both surfaces
 * shared one door.
 *
 * The borrowed login had a second failure mode that no permission change fixes:
 * the panel's login route is not domain-bound, so it resolves against APP_URL —
 * the MAIN domain — while the dashboard lives on its own subdomain. That works
 * only while the session cookie is shared across the parent domain
 * (SESSION_DOMAIN=.example.com). Without it, signing in loops forever:
 * dashboard (guest) → main-domain login → already authenticated → /admin →
 * EnsureCanViewAdminPanel → dashboard → guest → … The dashboard now
 * authenticates on its own host, so it no longer depends on that cookie to let
 * anyone in.
 *
 * ── What is deliberately still SHARED ────────────────────────────────────────
 *
 * The `web` guard and {@see StaffLoginDenial}. Same session, same rules. Where
 * the cookie IS shared across the parent domain, one sign-in still serves both
 * surfaces and one sign-out still ends both — the intended behaviour. What is no
 * longer shared is the panel's middleware stack, which is the part that broke.
 */
class StaffAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        // Never show a login form to someone who is already signed in — that dead
        // end is what made the original redirect loop so hard to read.
        if ($user instanceof User && $user->isActiveStaff()) {
            return redirect()->intended(route('staff.dashboard'));
        }

        return view('auth.staff-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, $credentials)) {
            $this->hit($request);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Disabled and non-staff accounts get a SPECIFIC message. Hiding them
        // behind "credentials do not match" sends an employee whose account was
        // just deactivated off to reset a password that works perfectly well.
        if ($reason = StaffLoginDenial::for($user)) {
            $this->hit($request);

            throw ValidationException::withMessages([
                'email' => StaffLoginDenial::body($reason),
            ]);
        }

        RateLimiter::clear($this->ipKey($request));
        RateLimiter::clear($this->accountKey($request));

        Auth::login($user, $request->boolean('remember'));

        // Fixation defence: the pre-login session id must not survive the
        // privilege change.
        $request->session()->regenerate();

        return redirect()->intended(route('staff.dashboard'));
    }

    /**
     * Sign out of the dashboard.
     *
     * Open to any authenticated user and gated by NOTHING else — see the class
     * docblock. Ending your own session is not a privilege that a permission may
     * withhold.
     *
     * `check_out=1` additionally closes the provider's open attendance session.
     * The logout button asks first (staff-nav.blade.php) instead of deciding for
     * them: a barber stepping out for ten minutes and a barber going home both
     * press logout, and only they know which one it is.
     */
    public function logout(Request $request, AttendanceService $attendance): RedirectResponse
    {
        $user = Auth::user();

        if ($user instanceof User && $request->boolean('check_out') && $user->isProvider()) {
            try {
                $attendance->checkOut($user);
            } catch (Throwable $e) {
                // No open session (already closed in another tab), or the service
                // refused. Attendance bookkeeping must never block a sign-out —
                // a logout that can fail is the exact failure this change removes.
                report($e);
            }
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('staff.dashboard.login');
    }

    // ── Rate limiting ────────────────────────────────────────────────────────
    //
    // Two dimensions on the same numbers as the API's `auth-login` limiter
    // (config/rate_limits.php): per IP to brake credential stuffing from one
    // place, per ACCOUNT to brake password spraying from many. Enforced in the
    // controller rather than as route middleware so a rejection renders as a
    // field error on the form instead of Laravel's bare 429 page.

    private function ensureIsNotRateLimited(Request $request): void
    {
        $limits = config('rate_limits.login');

        foreach ([
            [$this->ipKey($request), $limits['per_ip']],
            [$this->accountKey($request), $limits['per_account']],
        ] as [$key, $max]) {
            if (! RateLimiter::tooManyAttempts($key, $max)) {
                continue;
            }

            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }
    }

    private function hit(Request $request): void
    {
        RateLimiter::hit($this->ipKey($request));
        RateLimiter::hit($this->accountKey($request));
    }

    private function ipKey(Request $request): string
    {
        return 'staff-login:ip:' . $request->ip();
    }

    /**
     * Falls back to the IP when the request carries no readable identifier, so a
     * malformed body can never become an unlimited path — the same rule as
     * AppServiceProvider's `$accountKey`.
     */
    private function accountKey(Request $request): string
    {
        $identifier = ThrottleKey::forIdentifier($request);

        return $identifier !== null
            ? 'staff-login:acct:' . $identifier
            : $this->ipKey($request);
    }
}
