<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;

class PermissionsSeeder extends Seeder
{

    private const GUARD = 'web';


    private const BASE_ABILITIES = [
        'access',
        'view',
        'create',
        'edit',
        'delete',
        'force_delete',
    ];


    private const EXTRA_ABILITIES = [
        'Appointment'          => ['cancel', 'reschedule', 'export'],
        'User'                 => ['export', 'impersonate'],
        'Provider'             => ['manage_schedule', 'export'],
        'InvoiceTemplate'      => ['print', 'export'],
        'PrintLog'             => ['export'],
        'Service'              => ['export'],
    ];


    private const PAGE_ABILITIES = [
        'Reports'                       => ['access', 'export'],
        'ProviderReport'                => ['access', 'export'],
        'ManageSalonSchedules'          => ['access'],
        'ManageProviderSchedules'       => ['access'],
        'ManageProviderLeaves'          => ['access'],
        'ViewProviderScheduleTimeline'  => ['access'],
        'AttendanceBoard'               => ['access'],

        // StaffDashboard — one ability per actionable button on the dashboard so
        // each can be toggled per-role from the Roles screen. `access` opens the
        // page; `view_admin` controls whether the "Admin" tab (link to the
        // Filament panel) is shown in the dashboard nav; `view_team` controls
        // whether other providers' columns are visible; `edit_others` controls
        // whether a provider may act on bookings that are not their own.
        'StaffDashboard'                => [
            'access',
            'view_admin',
            'view_stats',
            'create_booking',
            // Force booking: bypass the provider availability window (working day,
            // working hours, full-day & hourly time-off) for a single booking.
            // The conflict + offers-service checks still apply.
            'force_booking',
            'add_service',
            'edit_appointment',
            'edit_others',
            'cancel_appointment',
            'delete_appointment',
            'take_payment',
            'print_invoice',
            'print_ticket',
            'manage_timeoff',
            'manage_colors',
            'edit_notes',
            'post_message',
            'view_team',
        ],
    ];


    /**
     * Abilities for models that are managed through a relation manager instead of
     * a Resource of their own, so `discoverResources()` cannot find them.
     *
     * `ProviderTimeOff` backs the "Leave management" tab on the Providers resource
     * (see {@see \App\Filament\Resources\Providers\RelationManagers\TimeOffsRelationManager}).
     */
    private const RELATION_ABILITIES = [
        'ProviderTimeOff' => ['view', 'create', 'edit', 'delete'],
    ];

    private const ROLES = [
        'SuperAdmin',
    ];

    /**
     * Permissions that belong to exactly one role. The listed role is granted
     * them, and every OTHER role (except SuperAdmin, which bypasses all checks)
     * has them revoked — so re-running the seeder always restores the intended
     * exclusivity even if a permission was handed out from the Roles screen.
     *
     * Provider leave management is admin-only: `manager` and `provider` must not
     * be able to create, edit or delete a provider's leave.
     */
    private const EXCLUSIVE_PERMISSIONS = [
        'admin' => [
            'ProviderTimeOff:view',
            'ProviderTimeOff:create',
            'ProviderTimeOff:edit',
            'ProviderTimeOff:delete',
        ],
    ];

    // ─────────────────────────────────────────────
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->line('');
        $this->command->info('┌─────────────────────────────────────────┐');
        $this->command->info('│        Permissions Seeder               │');
        $this->command->info('└─────────────────────────────────────────┘');

        // ── 1. Discover Resources ──────────────────
        $resources = $this->discoverResources();

        $this->command->line('');
        $this->command->comment("Discovered {$resources['count']} resources:");
        foreach ($resources['names'] as $name) {
            $this->command->line("  • {$name}");
        }

        // ── 2. Build permissions list ──────────────
        $allPermissions = $this->buildPermissionsList($resources['names']);

        // ── 3. Upsert permissions ──────────────────
        $this->command->line('');
        $this->command->comment('Creating permissions...');

        $created  = 0;
        $existing = 0;

        foreach ($allPermissions as $permName) {
            $perm = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => self::GUARD]
            );
            $perm->wasRecentlyCreated ? $created++ : $existing++;
        }

        $total = count($allPermissions);
        $this->command->info("  ✓ {$created} new | {$existing} already exist | {$total} total");

        // ── 3b. Prune stale permissions ────────────
        // Keeps the catalog in sync on every re-run: any "web"-guard permission
        // that the code no longer defines (renamed/removed resource or page) is
        // deleted, cascading its role/model pivot rows. The canonical list is the
        // single source of truth, so re-running the seeder reconciles fully.
        $pruned = $this->pruneStalePermissions($allPermissions);

        // ── 4. Upsert Roles ────────────────────────
        $this->command->line('');
        $this->command->comment('Creating roles...');

        foreach (self::ROLES as $roleName) {
            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => self::GUARD,
            ]);

            // SuperAdmin gets every permission
            $role->syncPermissions($allPermissions);

            $status = $role->wasRecentlyCreated ? 'created' : 'updated';
            $this->command->info("  ✓ Role [{$roleName}] {$status} → synced with {$total} permissions");
        }

        // ── 4b. Enforce single-role permissions ────
        $this->applyExclusivePermissions();

        // ── 5. Summary ─────────────────────────────
        $this->command->line('');
        $this->command->info('┌─────────────────────────────────────────┐');
        $this->command->info("│  Resources  : " . str_pad($resources['count'], 26) . '│');
        $this->command->info("│  Pages      : " . str_pad(count(self::PAGE_ABILITIES), 26) . '│');
        $this->command->info("│  Permissions: " . str_pad($total, 26) . '│');
        $this->command->info("│  Pruned     : " . str_pad($pruned, 26) . '│');
        $this->command->info("│  Guard      : " . str_pad(self::GUARD, 26) . '│');
        $this->command->info('└─────────────────────────────────────────┘');
        $this->command->line('');
    }

    // ─────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────

    /**
     * Auto-discover all *Resource.php files under app/Filament/Resources
     * and extract the resource name (class_basename without "Resource").
     */
    private function discoverResources(): array
    {
        $path  = app_path('Filament/Resources');
        $names = [];

        $finder = (new Finder())
            ->files()
            ->name('*Resource.php')
            ->in($path);

        foreach ($finder as $file) {
            $basename = $file->getBasename('.php');           // e.g. UserResource
            $name     = str_replace('Resource', '', $basename); // e.g. User

            if (!empty($name)) {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return [
            'names' => $names,
            'count' => count($names),
        ];
    }

    /**
     * Build the full flat list of "ResourceName:ability" strings.
     */
    private function buildPermissionsList(array $resourceNames): array
    {
        $permissions = [];

        // ─ Resources ──────────────────────────────
        foreach ($resourceNames as $resourceName) {

            // Base abilities (from NavigationDefaultAccess trait)
            foreach (self::BASE_ABILITIES as $ability) {
                $permissions[] = "{$resourceName}:{$ability}";
            }

            // Extra abilities specific to this Resource
            if (isset(self::EXTRA_ABILITIES[$resourceName])) {
                foreach (self::EXTRA_ABILITIES[$resourceName] as $ability) {
                    $permissions[] = "{$resourceName}:{$ability}";
                }
            }
        }

        // ─ Pages ──────────────────────────────────
        foreach (self::PAGE_ABILITIES as $pageName => $abilities) {
            foreach ($abilities as $ability) {
                $permissions[] = "{$pageName}:{$ability}";
            }
        }

        // ─ Relation managers ──────────────────────
        foreach (self::RELATION_ABILITIES as $modelName => $abilities) {
            foreach ($abilities as $ability) {
                $permissions[] = "{$modelName}:{$ability}";
            }
        }

        return $permissions;
    }

    /**
     * Grant each {@see EXCLUSIVE_PERMISSIONS} entry to its owning role and revoke
     * it from every other role, so "only this role may do it" stays true across
     * re-runs and manual edits from the Roles screen.
     */
    private function applyExclusivePermissions(): void
    {
        if (empty(self::EXCLUSIVE_PERMISSIONS)) {
            return;
        }

        $this->command->line('');
        $this->command->comment('Applying single-role permissions...');

        foreach (self::EXCLUSIVE_PERMISSIONS as $roleName => $permissionNames) {
            $owner = Role::where('name', $roleName)
                ->where('guard_name', self::GUARD)
                ->first();

            if (! $owner) {
                $this->command->warn("  ! Role [{$roleName}] not found — skipped " . count($permissionNames) . ' permission(s)');

                continue;
            }

            $owner->givePermissionTo($permissionNames);
            $this->command->info("  ✓ [{$roleName}] granted: " . implode(', ', $permissionNames));

            // Everyone else loses them. SuperAdmin is exempt: it holds the full
            // catalog by design and bypasses the checks anyway.
            $others = Role::where('guard_name', self::GUARD)
                ->whereNotIn('name', [$roleName, ...self::ROLES])
                ->get();

            foreach ($others as $role) {
                $revoked = $role->permissions
                    ->pluck('name')
                    ->intersect($permissionNames);

                if ($revoked->isEmpty()) {
                    continue;
                }

                $role->revokePermissionTo($revoked->all());
                $this->command->line("  − [{$role->name}] revoked: " . $revoked->implode(', '));
            }
        }
    }

    /**
     * Delete every "web"-guard permission that the code no longer defines, so a
     * re-run reconciles the catalog instead of only ever adding to it.
     *
     * @param  array<int, string>  $allPermissions  the canonical list (source of truth)
     * @return int                 number of permissions removed
     */
    private function pruneStalePermissions(array $allPermissions): int
    {
        $stale = Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereNotIn('name', $allPermissions)
            ->get();

        if ($stale->isEmpty()) {
            return 0;
        }

        $this->command->line('');
        $this->command->comment("Pruning {$stale->count()} stale permission(s)...");

        foreach ($stale as $permission) {
            $this->command->line("  − {$permission->name}");
            $permission->delete(); // cascades role_has_permissions / model_has_permissions
        }

        return $stale->count();
    }
}
