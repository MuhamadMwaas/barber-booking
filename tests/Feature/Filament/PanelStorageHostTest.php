<?php

/**
 * The admin panel is reachable on the dashboard subdomain, but `public` disk
 * URLs were always built on APP_URL. FilePond loads an existing image with
 * fetch(), so from the subdomain that load was cross-origin, had no CORS header,
 * and every image field hung on "Waiting for size". Inside the panel the disk
 * must follow the host the panel was opened from; everywhere else it keeps
 * APP_URL.
 */

use App\Http\Middleware\ServePanelStorageFromCurrentHost;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

const APP_HOST = 'https://lookup.test';
const PANEL_HOST = 'https://dashboard.lookup.test';

beforeEach(function () {
    config(['app.url' => APP_HOST, 'filesystems.disks.public.url' => APP_HOST.'/storage']);
    Storage::forgetDisk('public');

    $permissions = ['StaffDashboard:access', 'StaffDashboard:view_admin', 'Service:access', 'Service:edit'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('admin', 'web')->givePermissionTo($permissions);
});

function panelAdmin(): User
{
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    return $admin->fresh();
}

it('builds image URLs on the subdomain the panel was opened from', function () {
    // The page from the report: /admin/services/{id}/edit.
    $service = (new SalonFixture)->service;

    test()->actingAs(panelAdmin())
        ->get(PANEL_HOST."/admin/services/{$service->id}/edit")
        ->assertOk();

    expect(Storage::disk('public')->url("services/images/1/Men's_Haircut_1.png"))
        ->toStartWith(PANEL_HOST.'/storage/services/images/1/');
});

it('runs on the Livewire requests that FileUpload uses to fetch its file URLs', function () {
    expect(Livewire::getPersistentMiddleware())
        ->toContain(ServePanelStorageFromCurrentHost::class);
});

it('leaves URLs outside the panel on APP_URL', function () {
    test()->get(PANEL_HOST.'/up')->assertOk();

    expect(Storage::disk('public')->url('services/images/1/a.png'))
        ->toBe(APP_HOST.'/storage/services/images/1/a.png');
});
