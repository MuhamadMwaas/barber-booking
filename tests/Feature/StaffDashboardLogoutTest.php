<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Revoking `StaffDashboard:view_admin` must never cost someone their logout.
 *
 * The Staff Dashboard is not a Filament panel and has no logout route of its
 * own — its button posts to the PANEL's `filament.admin.auth.logout`, which sits
 * inside the panel's `authMiddleware` and so ran through
 * {@see \App\Http\Middleware\EnsureCanViewAdminPanel}. A provider without
 * `view_admin` was redirected back to the dashboard before Filament's logout
 * handler ever ran: the button did nothing and the session survived.
 */
class StaffDashboardLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['StaffDashboard:access', 'StaffDashboard:view_admin'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['provider', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Role::findByName('provider', 'web')->givePermissionTo('StaffDashboard:access');
        Role::findByName('admin', 'web')
            ->givePermissionTo(['StaffDashboard:access', 'StaffDashboard:view_admin']);
    }

    private function makeUser(string $role): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    /** The regression itself. */
    public function test_provider_without_view_admin_can_still_log_out(): void
    {
        $this->actingAs($this->makeUser('provider'))
            ->post(route('filament.admin.auth.logout'));

        $this->assertGuest();
    }

    public function test_staff_with_view_admin_can_still_log_out(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->post(route('filament.admin.auth.logout'));

        $this->assertGuest();
    }

    /**
     * The exemption is for logging OUT only — /admin itself must stay closed to
     * a provider whose `view_admin` has been revoked.
     */
    public function test_provider_without_view_admin_is_still_kept_out_of_the_panel(): void
    {
        $this->actingAs($this->makeUser('provider'))
            ->get('/admin')
            ->assertRedirect(route('staff.dashboard'));
    }
}
