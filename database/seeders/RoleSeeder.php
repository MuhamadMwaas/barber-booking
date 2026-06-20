<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    private const GUARD = 'web';


    private const ROLE_PERMISSIONS = [

        // ── SuperAdmin ─────────────────────────────────────────────────────
        // Full bypass — gets every permission in the system.
        'SuperAdmin' => 'all',

        // ── Admin ──────────────────────────────────────────────────────────
        // Same as SuperAdmin, full access to everything.
        'admin' => 'all',

        // ── Manager ────────────────────────────────────────────────────────
        // Manages day-to-day salon operations.
        // Cannot: manage users/roles, touch system settings, languages, or static pages.
        'manager' => [
            // Appointments — full operational access
            'Appointment:access', 'Appointment:view', 'Appointment:create',
            'Appointment:edit',   'Appointment:cancel', 'Appointment:reschedule',
            'Appointment:export',

            // Providers — can manage but not delete
            'Provider:access', 'Provider:view', 'Provider:create',
            'Provider:edit',   'Provider:manage_schedule', 'Provider:export',

            // Services & Categories — can manage but not delete
            'Service:access',         'Service:view',   'Service:create',
            'Service:edit',           'Service:export',
            'ServiceCategory:access', 'ServiceCategory:view',
            'ServiceCategory:create', 'ServiceCategory:edit',

            // Provider Schedules — full access
            'ProviderScheduledWork:access', 'ProviderScheduledWork:view',
            'ProviderScheduledWork:create', 'ProviderScheduledWork:edit',
            'ProviderScheduledWork:delete',

            // Leave Reasons — can manage
            'ReasonLeave:access', 'ReasonLeave:view',
            'ReasonLeave:create', 'ReasonLeave:edit',

            // Users — view only (sensitive resource)
            'User:access', 'User:view',

            // Invoices — can view & print, not edit/delete templates
            'InvoiceTemplate:access', 'InvoiceTemplate:view', 'InvoiceTemplate:print',

            // Printers & Print Logs — view & export only
            'PrinterSetting:access', 'PrinterSetting:view',
            'PrintLog:access',       'PrintLog:view', 'PrintLog:export',

            // Reports pages
            'Reports:access',       'Reports:export',
            'ProviderReport:access', 'ProviderReport:export',

            // Schedule & leave pages
            'ManageSalonSchedules:access',
            'ManageProviderSchedules:access',
            'ManageProviderLeaves:access',
            'ViewProviderScheduleTimeline:access',

            // Staff Dashboard — full operational control
            'StaffDashboard:access', 'StaffDashboard:view_admin',
            'StaffDashboard:view_stats',
            'StaffDashboard:create_booking',
            // Force booking (override availability window) — management only.
            'StaffDashboard:force_booking',
            'StaffDashboard:add_service', 'StaffDashboard:edit_appointment',
            'StaffDashboard:edit_others', 'StaffDashboard:cancel_appointment',
            'StaffDashboard:delete_appointment', 'StaffDashboard:take_payment',
            'StaffDashboard:print_invoice', 'StaffDashboard:print_ticket',
            'StaffDashboard:manage_timeoff', 'StaffDashboard:manage_colors',
            'StaffDashboard:edit_notes', 'StaffDashboard:post_message',
            'StaffDashboard:view_team',

            // Provider Attendance — can review attendance logs
            'ProviderAttendance:access', 'ProviderAttendance:view',
            'ProviderAttendance:edit',
            'AttendanceBoard:access',

            // CMS Pages — can manage content pages
            'CmsPage:access', 'CmsPage:view', 'CmsPage:create', 'CmsPage:edit',
        ],

        // ── Provider (Staff) ───────────────────────────────────────────────
        // A barber / service provider. Works primarily from the StaffDashboard
        // (operational board) and gets a limited Filament panel. The dashboard
        // action permissions below are granted in full by default — admins can
        // narrow them per provider from the Roles screen.
        'provider' => [
            'Appointment:access',          'Appointment:view',
            'Appointment:cancel',

            'ProviderScheduledWork:access', 'ProviderScheduledWork:view',

            'ProviderReport:access',

            'ManageProviderSchedules:access',
            'ManageProviderLeaves:access',
            'ViewProviderScheduleTimeline:access',

            // Staff Dashboard — full operational control by default.
            'StaffDashboard:access', 'StaffDashboard:view_admin',
            'StaffDashboard:view_stats',
            'StaffDashboard:create_booking',
            'StaffDashboard:add_service', 'StaffDashboard:edit_appointment',
            'StaffDashboard:edit_others', 'StaffDashboard:cancel_appointment',
            'StaffDashboard:delete_appointment', 'StaffDashboard:take_payment',
            'StaffDashboard:print_invoice', 'StaffDashboard:print_ticket',
            'StaffDashboard:manage_timeoff', 'StaffDashboard:manage_colors',
            'StaffDashboard:edit_notes', 'StaffDashboard:post_message',
            'StaffDashboard:view_team',
        ],

        // ── Customer ───────────────────────────────────────────────────────
        // End-user: uses the mobile/web API only, no admin panel access.
        'customer' => [],
    ];

    // ─────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->line('');
        $this->command->info('┌─────────────────────────────────────────┐');
        $this->command->info('│            Roles Seeder                  │');
        $this->command->info('└─────────────────────────────────────────┘');

        // All permissions already seeded by PermissionsSeeder
        $allPermissions = Permission::where('guard_name', self::GUARD)
            ->pluck('name')
            ->toArray();

        $this->command->line('');
        $this->command->comment('Found ' . count($allPermissions) . ' permissions in guard [' . self::GUARD . ']');

        // Seed each role
        $this->command->line('');
        $this->command->comment('Seeding roles...');

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {

            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => self::GUARD,
            ]);

            $status = $role->wasRecentlyCreated ? '<fg=green>created</>' : '<fg=yellow>updated</>';

            if ($permissions === 'all') {
                // SuperAdmin & admin — sync everything
                $role->syncPermissions($allPermissions);
                $count = count($allPermissions);
                $this->command->line("  ✓ [{$roleName}] {$status} → {$count} permissions (all)");

            } else {
                // Filter to only permissions that actually exist (safety)
                $valid = array_intersect($permissions, $allPermissions);
                $role->syncPermissions($valid);
                $count = count($valid);
                $this->command->line("  ✓ [{$roleName}] {$status} → {$count} permissions");
            }
        }

        // ── Summary ─────────────────────────────────────────────────────
        $this->command->line('');
        $this->command->info('┌─────────────────────────────────────────┐');
        $this->command->info('│  Roles seeded: ' . str_pad(count(self::ROLE_PERMISSIONS), 25) . '│');
        $this->command->info('│  Guard       : ' . str_pad(self::GUARD, 25) . '│');
        $this->command->info('└─────────────────────────────────────────┘');
        $this->command->line('');
    }
}
