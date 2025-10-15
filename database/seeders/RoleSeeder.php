<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // إنشاء الصلاحيات الأساسية لمشروع الصالون
        $permissions = [
            'manage branches',
            'manage services',
            'manage bookings',
            'manage providers',
            'manage customers',
            'view reports',
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }


        $roles = [
            'admin' => ['manage branches', 'manage services', 'manage bookings', 'manage providers', 'manage customers', 'view reports', 'manage settings'],
            'manager' => ['manage services', 'manage bookings', 'manage providers', 'view reports'],
            'provider' => ['manage bookings'],
            'customer' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }





        $this->command->info('Roles, permissions seeded successfully');
    }
}
