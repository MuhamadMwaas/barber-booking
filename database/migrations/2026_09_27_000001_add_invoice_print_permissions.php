<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * AUTHZ-03 — the permissions InvoicePolicy::print() checks.
 *
 * A migration, not "re-run the seeders": RoleSeeder SYNCS each role, which would
 * wipe every change an admin has made from the Roles screen in production. This
 * only ADDS the two new permissions to the roles that need them, so printing
 * keeps working for exactly the people who could print yesterday — minus the
 * cross-provider access, which is the point.
 *
 * Same grants as PermissionsSeeder::POLICY_ABILITIES + RoleSeeder for fresh
 * installs.
 */
return new class extends Migration
{
    private const GRANTS = [
        'SuperAdmin' => ['Invoice:print', 'Invoice:print_others'],
        'admin' => ['Invoice:print', 'Invoice:print_others'],
        'manager' => ['Invoice:print', 'Invoice:print_others'],
        'provider' => ['Invoice:print'],
    ];

    public function up(): void
    {
        foreach (['Invoice:print', 'Invoice:print_others'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::GRANTS as $roleName => $permissions) {
            // Absent on a fresh database (roles come from the seeders later).
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first()
                ?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['Invoice:print', 'Invoice:print_others'])
            ->get()
            ->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
