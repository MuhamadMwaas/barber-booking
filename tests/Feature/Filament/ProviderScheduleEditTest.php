<?php

/**
 * Admin weekly-schedule editor (/admin/provider-scheduled-works/{user}/edit).
 *
 * The list table is a table of providers, so the route key is a user id.
 * Two regressions are covered:
 *  - a provider with no shifts yet has an unsaved record; Filament nulled it
 *    after the repeater "add" action and the next request ("delete") crashed
 *    with "Typed property ...::$record must not be accessed before initialization".
 *  - the page first looked the key up as a schedule id, so a provider whose
 *    user id matched another provider's schedule row saved onto that provider.
 */

use App\Filament\Resources\ProviderScheduledWorks\Pages\EditProviderScheduledWork;
use App\Models\ProviderScheduledWork;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    Role::findOrCreate('SuperAdmin', 'web');
    Role::findOrCreate('provider', 'web');

    $admin = User::create([
        'first_name' => 'Schedule',
        'last_name' => 'Admin',
        'email' => 'schedule-admin-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $admin->assignRole('SuperAdmin');
    $this->actingAs($admin->fresh());
});

function scheduleProvider(string $name): User
{
    $provider = User::factory()->create(['first_name' => $name, 'is_active' => true]);
    $provider->assignRole('provider');

    return $provider;
}

function mondayShift(User $provider, array $attributes = []): ProviderScheduledWork
{
    return ProviderScheduledWork::forceCreate([
        'user_id' => $provider->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_work_day' => true,
        'is_active' => true,
        ...$attributes,
    ]);
}

it('adds and deletes a shift for a provider without any schedule', function () {
    $provider = scheduleProvider('Newcomer');

    $page = Livewire::test(EditProviderScheduledWork::class, ['record' => $provider->id])
        ->assertOk()
        ->callAction(TestAction::make('add')->schemaComponent('days.1.shifts', 'form'))
        ->assertHasNoErrors();

    $items = $page->get('data.days.1.shifts');
    expect($items)->toHaveCount(1);

    $page->callAction(
        TestAction::make('delete')
            ->schemaComponent('days.1.shifts', 'form')
            ->arguments(['item' => array_key_first($items)])
    )->assertOk();

    expect($page->get('data.days.1.shifts'))->toBeEmpty();
});

it('saves the first schedule of a new provider onto that provider', function () {
    $provider = scheduleProvider('Newcomer');

    Livewire::test(EditProviderScheduledWork::class, ['record' => $provider->id])
        ->callAction(TestAction::make('add')->schemaComponent('days.1.shifts', 'form'))
        ->call('saveSchedule')
        ->assertRedirect();

    expect(ProviderScheduledWork::where('user_id', $provider->id)->count())->toBe(1);
});

it('never saves onto the provider who owns a schedule row with the same id', function () {
    $owner = scheduleProvider('Owner');
    $editor = scheduleProvider('Editor');

    // Owner's schedule row id collides with the edited provider's user id.
    mondayShift($owner, ['id' => $editor->id]);
    mondayShift($editor, ['start_time' => '10:00:00', 'end_time' => '14:00:00']);

    Livewire::test(EditProviderScheduledWork::class, ['record' => $editor->id])
        ->call('saveSchedule')
        ->assertRedirect();

    // The form saves the picker's raw "Y-m-d H:i:s" state; MySQL's TIME column
    // drops the date, SQLite keeps it — so compare the time part only.
    $startTimes = fn (User $provider) => ProviderScheduledWork::where('user_id', $provider->id)
        ->pluck('start_time')
        ->map(fn (string $time) => substr($time, -8))
        ->all();

    expect($startTimes($owner))->toBe(['09:00:00'])
        ->and($startTimes($editor))->toBe(['10:00:00']);
});

it('returns 404 for a user who is not a provider', function () {
    $customer = User::factory()->create();

    Livewire::test(EditProviderScheduledWork::class, ['record' => $customer->id])
        ->assertNotFound();
});
