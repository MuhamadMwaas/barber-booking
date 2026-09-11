<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AttendanceService;
use App\Support\StaffLoginDenial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Staff Dashboard's own sign-in and sign-out.
 *
 * The dashboard used to have neither: guests were bounced to the Filament
 * panel's login on the MAIN domain and the logout button posted to the panel's
 * logout, which put both inside the panel's `authMiddleware`. Adding
 * EnsureCanViewAdminPanel there then made revoking a provider's
 * `StaffDashboard:view_admin` silently disable their logout button.
 *
 * These tests pin the two invariants that failure taught:
 *   1. Signing in and out never depend on a per-surface VIEWING permission.
 *   2. Both logins answer "may this account sign in?" through one class, so they
 *      cannot drift the way canAccessPanel() and the dashboard gate once did.
 */
class StaffAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('staff-login:ip:127.0.0.1');

        foreach (['StaffDashboard:access', 'StaffDashboard:view_admin'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['provider', 'admin', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Deliberately WITHOUT view_admin — the configuration that broke logout.
        Role::findByName('provider', 'web')->givePermissionTo('StaffDashboard:access');
        Role::findByName('admin', 'web')
            ->givePermissionTo(['StaffDashboard:access', 'StaffDashboard:view_admin']);
    }

    private function makeUser(string $role, bool $active = true): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => Hash::make(self::PASSWORD),
            'is_active' => $active,
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    private function attempt(User $user, string $password = self::PASSWORD)
    {
        return $this->post(route('staff.dashboard.login.attempt'), [
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    // ── Sign in ──────────────────────────────────────────────────────────────

    public function test_login_page_is_reachable_without_authentication(): void
    {
        $this->get(route('staff.dashboard.login'))->assertOk();
    }

    public function test_provider_without_view_admin_can_sign_in(): void
    {
        $provider = $this->makeUser('provider');

        $this->attempt($provider)->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticatedAs($provider);
    }

    public function test_signing_in_lands_on_the_dashboard_it_was_asked_for(): void
    {
        $provider = $this->makeUser('provider');

        // The gate stores the intended URL, so a deep link survives the login.
        $this->get(route('staff.dashboard.customers'))
            ->assertRedirect(route('staff.dashboard.login'));

        $this->attempt($provider)->assertRedirect(route('staff.dashboard.customers'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->attempt($this->makeUser('provider'), 'not-the-password')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_staff_are_told_their_account_is_disabled(): void
    {
        $this->attempt($this->makeUser('provider', active: false))
            ->assertSessionHasErrors(['email' => __('auth.account_disabled_body')]);

        $this->assertGuest();
    }

    public function test_customer_accounts_cannot_sign_in(): void
    {
        $this->attempt($this->makeUser('customer'))
            ->assertSessionHasErrors(['email' => __('auth.customer_not_allowed_body')]);

        $this->assertGuest();
    }

    public function test_an_authenticated_provider_is_not_shown_the_login_form(): void
    {
        $this->actingAs($this->makeUser('provider'))
            ->get(route('staff.dashboard.login'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_repeated_failures_are_rate_limited_per_account(): void
    {
        $provider = $this->makeUser('provider');
        $max = config('rate_limits.login.per_account');

        for ($i = 0; $i < $max; $i++) {
            $this->attempt($provider, 'wrong');
        }

        // The account dimension has been spent; even the RIGHT password is now
        // refused, which is what stops a spray from ending in a success.
        $this->attempt($provider)->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_successful_sign_in_clears_earlier_failures(): void
    {
        $provider = $this->makeUser('provider');

        $this->attempt($provider, 'wrong');
        $this->attempt($provider)->assertRedirect(route('staff.dashboard'));

        $this->assertSame(
            0,
            RateLimiter::attempts('staff-login:acct:' . hash('sha256', $provider->email)),
        );
    }

    // ── Sign out ─────────────────────────────────────────────────────────────

    /** The regression this whole change exists for. */
    public function test_provider_without_view_admin_can_sign_out(): void
    {
        $this->actingAs($this->makeUser('provider'))
            ->post(route('staff.dashboard.logout'))
            ->assertRedirect(route('staff.dashboard.login'));

        $this->assertGuest();
    }

    public function test_signing_out_does_not_touch_attendance_unless_asked(): void
    {
        $provider = $this->makeUser('provider');

        // Opened through the service, so the fixture matches what the dashboard
        // actually writes (work_date, branch, source) rather than a hand-built row.
        $session = app(AttendanceService::class)->checkIn($provider)['attendance'];

        $this->actingAs($provider)->post(route('staff.dashboard.logout'));

        $this->assertNull($session->fresh()->check_out_at);
        $this->assertGuest();
    }

    public function test_signing_out_with_check_out_closes_the_open_session(): void
    {
        $provider = $this->makeUser('provider');

        // Opened through the service, so the fixture matches what the dashboard
        // actually writes (work_date, branch, source) rather than a hand-built row.
        $session = app(AttendanceService::class)->checkIn($provider)['attendance'];

        $this->actingAs($provider)
            ->post(route('staff.dashboard.logout'), ['check_out' => '1']);

        $this->assertNotNull($session->fresh()->check_out_at);
        $this->assertGuest();
    }

    /**
     * Attendance bookkeeping must never be able to hold a session open — a
     * logout that can fail is the exact failure this change removes.
     */
    public function test_check_out_with_no_open_session_still_signs_out(): void
    {
        $this->actingAs($this->makeUser('provider'))
            ->post(route('staff.dashboard.logout'), ['check_out' => '1'])
            ->assertRedirect(route('staff.dashboard.login'));

        $this->assertGuest();
    }

    // ── The single source of truth ───────────────────────────────────────────

    /**
     * StaffLoginDenial::for() must return null exactly when isActiveStaff() is
     * true. If these two ever disagree, one surface admits somebody the other
     * refuses — which is the class of bug both of them were written to end.
     */
    public function test_login_denial_agrees_with_is_active_staff(): void
    {
        foreach ([['provider', true], ['provider', false], ['admin', true], ['customer', true]] as [$role, $active]) {
            $user = $this->makeUser($role, $active);

            $this->assertSame(
                $user->isActiveStaff(),
                StaffLoginDenial::for($user) === null,
                "StaffLoginDenial drifted from isActiveStaff() for {$role} (active: " . var_export($active, true) . ')',
            );
        }
    }

    public function test_a_role_less_account_is_refused_as_not_staff(): void
    {
        $user = User::create([
            'first_name' => 'No',
            'last_name' => 'Role',
            'email' => 'no-role-' . uniqid() . '@example.com',
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
        ]);

        $this->assertSame(StaffLoginDenial::NOT_STAFF, StaffLoginDenial::for($user->fresh()));
    }
}
