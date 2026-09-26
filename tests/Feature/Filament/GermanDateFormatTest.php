<?php

/**
 * German admin dates read day-first ("30/12/2026") with 24-hour times ("14:30").
 *
 * The formats live in App\Support\DateFormat; AppServiceProvider makes them the
 * Filament defaults, and pages with a hard-coded format (ManageProviderLeaves)
 * call the helper directly. Other locales keep their previous formats.
 */

use App\Filament\Pages\ManageProviderLeaves;
use App\Models\User;
use App\Support\DateFormat;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Filament::setCurrentPanel('admin');

    Role::findOrCreate('SuperAdmin', 'web');
    $admin = User::create([
        'first_name' => 'Date',
        'last_name' => 'Admin',
        'email' => 'date-admin-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $admin->assignRole('SuperAdmin');
    $this->actingAs($admin->fresh());

    $this->salon->giveFullDayLeave($this->salon->available, '2026-12-21', '2026-12-30');
    $this->salon->giveHourlyLeave($this->salon->available, '2026-11-18', '14:30', '17:45');
});

it('uses day-first dates and a 24-hour clock in German', function () {
    app()->setLocale('de');

    expect(DateFormat::date())->toBe('d/m/Y')
        ->and(DateFormat::time())->toBe('H:i');

    Livewire::test(ManageProviderLeaves::class)
        ->assertOk()
        ->assertSee('21/12/2026')
        ->assertSee('30/12/2026')
        ->assertSee('18/11/2026')
        ->assertSee('14:30')
        ->assertSee('17:45')
        ->assertDontSee('2026-12-30')
        ->assertDontSee('2:30 PM');
});

it('leaves the English format unchanged', function () {
    app()->setLocale('en');

    Livewire::test(ManageProviderLeaves::class)
        ->assertOk()
        ->assertSee('2026-12-30')
        ->assertDontSee('30/12/2026');
});
