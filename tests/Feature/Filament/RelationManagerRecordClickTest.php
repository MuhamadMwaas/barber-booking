<?php

/**
 * RM-01 — clicking a provider row on /admin/services/{id} crashed with
 * "Call to a member function isEmpty() on null" (ServiceInfolist.php:145).
 *
 * ProvidersRelationManager declared `$relatedResource = ServiceResource`, but its
 * rows are providers (User). Filament 4 then runs ServiceResource::configureTable()
 * on the manager's table; our own recordActions() hides ServicesTable's
 * ViewAction but it stays registered, so a row click mounted it and rendered
 * ServiceForm + ServiceInfolist against a User. The two Users relation managers
 * had the same mis-pointed $relatedResource (UserResource over services /
 * appointments). All three now have no $relatedResource and link the row to the
 * record's own resource page.
 */

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Services\Pages\ViewService;
use App\Filament\Resources\Services\RelationManagers\ProvidersRelationManager;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\CustomerAppointmentsRelationManager;
use App\Filament\Resources\Users\RelationManagers\ServicesRelationManager;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Filament::setCurrentPanel('admin');
});

function relationManagerViewer(string $role, array $permissions = []): User
{
    Role::findOrCreate($role, 'web');

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::create([
        'first_name' => 'Viewer',
        'last_name' => ucfirst($role),
        'email' => 'rm-'.$role.'-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $user->assignRole($role);
    $user->givePermissionTo($permissions);

    return $user->fresh();
}

function recordUrlOf($component, $record): ?string
{
    return $component->instance()->getTable()->getRecordUrl($record);
}

// ── The reported crash ───────────────────────────────────────────────────────

it('no longer carries a hidden view action on the service providers table', function () {
    $this->actingAs(relationManagerViewer('SuperAdmin'));

    Livewire::test(ProvidersRelationManager::class, [
        'ownerRecord' => $this->salon->service,
        'pageClass' => ViewService::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->salon->available])
        ->assertTableActionDoesNotExist('view');
});

it('links a provider row to the provider page', function () {
    $this->actingAs(relationManagerViewer('SuperAdmin'));

    $component = Livewire::test(ProvidersRelationManager::class, [
        'ownerRecord' => $this->salon->service,
        'pageClass' => ViewService::class,
    ]);

    expect(recordUrlOf($component, $this->salon->available))
        ->toBe(ProviderResource::getUrl('view', ['record' => $this->salon->available]));
});

it('does not link a provider row the viewer may not open', function () {
    $this->actingAs(relationManagerViewer('manager', ['Service:access', 'Service:view']));

    $component = Livewire::test(ProvidersRelationManager::class, [
        'ownerRecord' => $this->salon->service,
        'pageClass' => ViewService::class,
    ]);

    expect(recordUrlOf($component, $this->salon->available))->toBeNull();
});

it('does not link a linked user who is not a provider (ProviderResource would 404)', function () {
    $this->actingAs(relationManagerViewer('SuperAdmin'));

    $this->salon->available->removeRole('provider');

    $component = Livewire::test(ProvidersRelationManager::class, [
        'ownerRecord' => $this->salon->service,
        'pageClass' => ViewService::class,
    ]);

    expect(recordUrlOf($component, $this->salon->available->fresh()))->toBeNull();
});

// ── Same mis-pointed $relatedResource on the Users page ──────────────────────

it('links a service row on the user page to the service page', function () {
    $this->actingAs(relationManagerViewer('SuperAdmin'));

    $component = Livewire::test(ServicesRelationManager::class, [
        'ownerRecord' => $this->salon->available,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertTableActionDoesNotExist('view');

    expect(recordUrlOf($component, $this->salon->service))
        ->toBe(ServiceResource::getUrl('view', ['record' => $this->salon->service]));
});

it('links an appointment row on the customer page to the appointment page', function () {
    $this->actingAs(relationManagerViewer('SuperAdmin'));

    $appointment = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');

    $component = Livewire::test(CustomerAppointmentsRelationManager::class, [
        'ownerRecord' => $this->salon->filler,
        'pageClass' => ViewUser::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$appointment])
        ->assertTableActionDoesNotExist('view');

    expect(recordUrlOf($component, $appointment))
        ->toBe(AppointmentResource::getUrl('view', ['record' => $appointment]));
});
