<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AUTHZ-01 — deactivating a staff account must actually revoke access.
 *
 * The regression these tests exist to prevent: EnsureStaffDashboardAccess used
 * to check only the `StaffDashboard:access` permission. Because the Staff
 * Dashboard is a plain route group and not a Filament panel path,
 * User::canAccessPanel() — the only place `is_active` was checked — never ran
 * there, so a deactivated employee kept the customer database, payment
 * collection and booking deletion.
 */
class StaffDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('StaffDashboard:access', 'web');

        foreach (['provider', 'admin', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Role::findByName('provider', 'web')->givePermissionTo('StaffDashboard:access');
        Role::findByName('admin', 'web')->givePermissionTo('StaffDashboard:access');
    }

    private function makeUser(string $role, bool $active = true): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'is_active' => $active,
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_active_provider_reaches_the_staff_dashboard(): void
    {
        $this->actingAs($this->makeUser('provider'))
            ->get(route('staff.dashboard'))
            ->assertOk();
    }

    /** The vulnerability itself. */
    public function test_deactivated_provider_is_denied_the_staff_dashboard(): void
    {
        $response = $this->actingAs($this->makeUser('provider', active: false))
            ->get(route('staff.dashboard'));

        $response->assertRedirect(route('staff.dashboard.login'));
    }

    /** Denial is not enough — the session must be destroyed, not just refused. */
    public function test_deactivated_provider_is_logged_out_not_merely_refused(): void
    {
        $this->actingAs($this->makeUser('provider', active: false))
            ->get(route('staff.dashboard'));

        $this->assertGuest();
    }

    public function test_deactivated_admin_is_denied_too(): void
    {
        $this->actingAs($this->makeUser('admin', active: false))
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.dashboard.login'));
    }

    /**
     * Role gate: holding the permission without a staff role is not enough.
     * Guards against a repeat of the removed /grant-view-stats route, which
     * granted StaffDashboard permissions to every role including customers.
     */
    public function test_customer_holding_the_permission_is_forbidden(): void
    {
        $customer = $this->makeUser('customer');
        $customer->givePermissionTo('StaffDashboard:access');

        $this->actingAs($customer->fresh())
            ->get(route('staff.dashboard'))
            ->assertForbidden();
    }

    public function test_active_staff_without_the_permission_is_forbidden(): void
    {
        Role::findByName('provider', 'web')->revokePermissionTo('StaffDashboard:access');

        $this->actingAs($this->makeUser('provider'))
            ->get(route('staff.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.dashboard.login'));
    }

    // ── The single source of truth ───────────────────────────────────────────

    public function test_is_active_staff_requires_both_halves(): void
    {
        $this->assertTrue($this->makeUser('provider')->isActiveStaff());
        $this->assertFalse($this->makeUser('provider', active: false)->isActiveStaff());
        $this->assertFalse($this->makeUser('customer')->isActiveStaff());
    }

    public function test_panel_access_and_dashboard_access_agree(): void
    {
        $panel = filament()->getDefaultPanel();

        foreach ([['provider', true], ['provider', false], ['customer', true]] as [$role, $active]) {
            $user = $this->makeUser($role, $active);

            $this->assertSame(
                $user->isActiveStaff(),
                $user->canAccessPanel($panel),
                "canAccessPanel() drifted from isActiveStaff() for {$role} (active: " . var_export($active, true) . ')',
            );
        }
    }

    // ── The observer: deactivation is a kill switch, not a flag ──────────────

    public function test_deactivating_a_user_revokes_api_and_refresh_tokens(): void
    {
        $user = $this->makeUser('provider');
        $user->createToken('mobile');

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'some-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $user->update(['is_active' => false]);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'revoked' => true,
            'revoked_reason' => RefreshToken::REASON_ACCOUNT_DISABLED,
        ]);
    }

    public function test_deactivating_a_user_clears_the_remember_me_token(): void
    {
        $user = $this->makeUser('provider');
        $user->forceFill(['remember_token' => 'a-live-recaller'])->saveQuietly();

        $user->update(['is_active' => false]);

        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_reactivating_a_user_does_not_revoke_anything(): void
    {
        $user = $this->makeUser('provider', active: false);
        $user->update(['is_active' => true]);

        $token = $user->createToken('mobile');
        $user->update(['first_name' => 'Renamed']);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotNull($token);
    }

    public function test_deleting_a_user_revokes_their_tokens(): void
    {
        $user = $this->makeUser('provider');
        $user->createToken('mobile');

        $user->delete();

        $this->assertSame(0, $user->tokens()->count());
    }
}
