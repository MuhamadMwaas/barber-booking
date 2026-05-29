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
        'StaffDashboard'                => ['access'],
    ];


    private const ROLES = [
        'SuperAdmin',
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

        // ── 5. Summary ─────────────────────────────
        $this->command->line('');
        $this->command->info('┌─────────────────────────────────────────┐');
        $this->command->info("│  Resources  : " . str_pad($resources['count'], 26) . '│');
        $this->command->info("│  Pages      : " . str_pad(count(self::PAGE_ABILITIES), 26) . '│');
        $this->command->info("│  Permissions: " . str_pad($total, 26) . '│');
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

        return $permissions;
    }
}
