<?php

/**
 * The admin surfaces that write a leave — ViewProvider's two header actions and
 * the leaves relation manager (create + edit) — all go through
 * LeaveReassignmentService: a leave over open bookings opens "resolveLeaveConflicts",
 * then "confirmLeaveReassignment", and is only saved from there.
 *
 * `available` goes on leave; at 10:00 only `hourlyLeave` can take its booking.
 */

use App\Enum\AppointmentStatus;
use App\Filament\Resources\Providers\Pages\ViewProvider;
use App\Filament\Resources\Providers\RelationManagers\TimeOffsRelationManager;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\User;
use App\Services\LeaveReassignmentService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Filament::setCurrentPanel('admin');

    Role::findOrCreate('SuperAdmin', 'web');
    $admin = User::create([
        'first_name' => 'Leave',
        'last_name' => 'Admin',
        'email' => 'leave-admin-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $admin->assignRole('SuperAdmin');
    $this->actingAs($admin->fresh());

    $this->leaver = $this->salon->available;
    $this->reasonId = ReasonLeave::firstOrCreate(['name' => 'Sick'])->id;
});

afterEach(function () {
    Carbon::setTestNow();
});

function filamentLeaveBooking(User $provider, string $date = SalonFixture::DATE): Appointment
{
    $appointment = test()->salon->bookSlot($provider, $date, '10:00', '11:00');
    AppointmentService::create([
        'appointment_id' => $appointment->id,
        'service_id' => test()->salon->service->id,
        'service_name' => test()->salon->service->name,
        'duration_minutes' => 60,
        'price' => 100.00,
        'sequence_order' => 1,
    ]);

    return $appointment;
}

function leavesOf(User $provider)
{
    return ProviderTimeOff::where('user_id', $provider->id)->get();
}

it('saves a daily leave over an empty calendar directly', function () {
    Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()])
        ->callAction('add_daily_leave', [
            'start_date' => SalonFixture::DATE,
            'end_date' => SalonFixture::DATE,
            'reason_id' => $this->reasonId,
        ])
        ->assertHasNoActionErrors();

    expect(leavesOf($this->leaver))->toHaveCount(1);
});

it('walks a daily leave over a booking through the conflict and plan modals', function () {
    $booking = filamentLeaveBooking($this->leaver);

    $page = Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()])
        ->callAction('add_daily_leave', [
            'start_date' => SalonFixture::DATE,
            'end_date' => SalonFixture::DATE,
            'reason_id' => $this->reasonId,
        ])
        ->assertActionMounted('resolveLeaveConflicts');

    expect(leavesOf($this->leaver))->toBeEmpty();

    $page->callMountedAction()
        ->assertActionMounted('confirmLeaveReassignment');

    expect(leavesOf($this->leaver))->toBeEmpty();

    $page->callMountedAction();

    expect(leavesOf($this->leaver))->toHaveCount(1)
        ->and($booking->fresh()->provider_id)->toBe($this->salon->hourlyLeave->id)
        ->and($booking->fresh()->reassigned_from_provider_id)->toBe($this->leaver->id);
});

it('cancels what cannot be moved once the plan is confirmed', function () {
    // hourlyLeave is the only other candidate at 10:00 — take it away.
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');
    $booking = filamentLeaveBooking($this->leaver);

    Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()])
        ->callAction('add_hourly_leave', [
            'start_date' => SalonFixture::DATE,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'reason_id' => $this->reasonId,
        ])
        ->assertActionMounted('resolveLeaveConflicts')
        ->callMountedAction()
        ->assertActionMounted('confirmLeaveReassignment')
        ->callMountedAction();

    expect($booking->fresh()->status)->toBe(AppointmentStatus::ADMIN_CANCELLED)
        ->and(leavesOf($this->leaver))->toHaveCount(1);
});

it('keeps the plan modal open with a fresh plan when the calendar changed', function () {
    filamentLeaveBooking($this->leaver);

    $page = Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()])
        ->callAction('add_daily_leave', [
            'start_date' => SalonFixture::DATE,
            'end_date' => SalonFixture::DATE,
            'reason_id' => $this->reasonId,
        ])
        ->callMountedAction()
        ->assertActionMounted('confirmLeaveReassignment');

    // The planned colleague gets booked before "confirm".
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');

    $page->callMountedAction()
        ->assertActionMounted('confirmLeaveReassignment', fn (array $arguments) => $arguments['changed'] === true
            && $arguments['plan']['cancellations'] === 1);

    expect(leavesOf($this->leaver))->toBeEmpty();
});

it('rejects an hourly leave whose end time is before its start time', function () {
    Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()])
        ->callAction('add_hourly_leave', [
            'start_date' => SalonFixture::DATE,
            'start_time' => '22:00',
            'end_time' => '02:00',
            'reason_id' => $this->reasonId,
        ])
        ->assertHasActionErrors(['end_time']);

    expect(leavesOf($this->leaver))->toBeEmpty();
});

it('routes the relation manager create through the conflict modals', function () {
    $booking = filamentLeaveBooking($this->leaver);

    $manager = Livewire::test(TimeOffsRelationManager::class, [
        'ownerRecord' => $this->leaver,
        'pageClass' => ViewProvider::class,
    ])
        ->callAction(TestAction::make('create')->table(), [
            'type' => ProviderTimeOff::TYPE_FULL_DAY,
            'start_date' => SalonFixture::DATE,
            'end_date' => SalonFixture::DATE,
            'reason_id' => $this->reasonId,
        ])
        ->assertActionMounted('resolveLeaveConflicts');

    expect(leavesOf($this->leaver))->toBeEmpty();

    $manager->callMountedAction()
        ->assertActionMounted('confirmLeaveReassignment')
        ->callMountedAction();

    expect(leavesOf($this->leaver))->toHaveCount(1)
        ->and($booking->fresh()->provider_id)->toBe($this->salon->hourlyLeave->id);
});

it('routes a relation manager edit that stretches a leave over a booking through the conflict modal', function () {
    $leaveId = $this->salon->giveFullDayLeave($this->leaver, '2026-09-10', '2026-09-10');
    filamentLeaveBooking($this->leaver);   // on SalonFixture::DATE, the day before

    Livewire::test(TimeOffsRelationManager::class, [
        'ownerRecord' => $this->leaver,
        'pageClass' => ViewProvider::class,
    ])
        ->callAction(TestAction::make('edit')->table(ProviderTimeOff::find($leaveId)), [
            'type' => ProviderTimeOff::TYPE_FULL_DAY,
            'start_date' => SalonFixture::DATE,
            'end_date' => '2026-09-10',
            'reason_id' => $this->reasonId,
        ])
        ->assertActionMounted('resolveLeaveConflicts');

    expect(Carbon::parse(ProviderTimeOff::find($leaveId)->start_date)->format('Y-m-d'))->toBe('2026-09-10');
});

// ── Review regressions: permissions and record scope (TO-01, TO-02, TO-03) ──
// The modals' arguments are client state; these tests send them the way a
// crafted request would (mountAction + callMountedAction), not through the UI.

function leaveStaff(string $role, array $permissions): User
{
    Role::findOrCreate($role, 'web');
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::create([
        'first_name' => 'Staff', 'last_name' => ucfirst($role),
        'email' => 'leave-'.$role.'-'.uniqid().'@example.com',
        'password' => Hash::make('password'), 'is_active' => true,
    ]);
    $user->assignRole($role);
    $user->givePermissionTo($permissions);

    return $user->fresh();
}

/** Can open a provider page, but holds no leave permission. */
function providerViewer(): User
{
    return leaveStaff('manager', ['StaffDashboard:view_admin', 'StaffDashboard:access', 'Provider:access', 'Provider:view']);
}

function craftedConfirm($component, array $arguments)
{
    return $component
        ->call('mountAction', 'confirmLeaveReassignment', $arguments)
        ->call('callMountedAction');
}

function fullDayLeaveColumns(): array
{
    return [
        'type' => ProviderTimeOff::TYPE_FULL_DAY,
        'start_date' => SalonFixture::DATE,
        'end_date' => SalonFixture::DATE,
        'reason_id' => test()->reasonId,
    ];
}

it('TO-01: hides the leave buttons from a user who may only view the provider', function () {
    $this->actingAs(providerViewer());

    Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()])
        ->assertActionHidden('add_daily_leave')
        ->assertActionHidden('add_hourly_leave');
});

it('TO-01: a crafted confirm from a user without leave permission changes nothing', function () {
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');
    $booking = filamentLeaveBooking($this->leaver);
    $plan = app(LeaveReassignmentService::class)
        ->plan(new ProviderTimeOff(['user_id' => $this->leaver->id, ...fullDayLeaveColumns()]));
    expect($plan['cancellations'])->toBe(1);

    $this->actingAs(providerViewer());
    craftedConfirm(Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()]), [
        'leave' => fullDayLeaveColumns(), 'time_off_id' => null, 'plan' => $plan,
    ]);

    expect($booking->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and(leavesOf($this->leaver))->toBeEmpty();
});

it('TO-02: a user_id smuggled into the arguments is ignored; the leave is the page provider\'s', function () {
    $other = $this->salon->notWorking;

    craftedConfirm(Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()]), [
        'leave' => ['user_id' => $other->id, ...fullDayLeaveColumns()],
        'time_off_id' => null,
        'plan' => ['signature' => ''],
    ]);

    expect(leavesOf($other))->toBeEmpty()
        ->and(leavesOf($this->leaver))->toHaveCount(1);
});

it('TO-02: another provider\'s leave cannot be edited from this provider\'s tab', function () {
    $otherLeaveId = $this->salon->giveFullDayLeave($this->salon->notWorking, '2026-09-10', '2026-09-10');
    $manager = Livewire::test(TimeOffsRelationManager::class, [
        'ownerRecord' => $this->leaver, 'pageClass' => ViewProvider::class,
    ]);

    expect(fn () => craftedConfirm($manager, [
        'leave' => ['end_date' => '2026-09-12'], 'time_off_id' => $otherLeaveId, 'plan' => ['signature' => ''],
    ]))->toThrow(ModelNotFoundException::class);

    expect(Carbon::parse(ProviderTimeOff::find($otherLeaveId)->end_date)->format('Y-m-d'))->toBe('2026-09-10');
});

it('TO-02: a create-only user cannot edit a leave through the confirm modal', function () {
    $this->actingAs(leaveStaff('admin', ['ProviderTimeOff:view', 'ProviderTimeOff:create']));
    $ownLeaveId = $this->salon->giveFullDayLeave($this->leaver, '2026-09-10', '2026-09-10');

    craftedConfirm(Livewire::test(TimeOffsRelationManager::class, [
        'ownerRecord' => $this->leaver, 'pageClass' => ViewProvider::class,
    ]), [
        'leave' => ['end_date' => '2026-09-12'], 'time_off_id' => $ownLeaveId, 'plan' => ['signature' => ''],
    ]);

    expect(Carbon::parse(ProviderTimeOff::find($ownLeaveId)->end_date)->format('Y-m-d'))->toBe('2026-09-10');
});

it('TO-02: a create-only user can still create', function () {
    $this->actingAs(leaveStaff('admin', ['ProviderTimeOff:view', 'ProviderTimeOff:create']));

    craftedConfirm(Livewire::test(TimeOffsRelationManager::class, [
        'ownerRecord' => $this->leaver, 'pageClass' => ViewProvider::class,
    ]), [
        'leave' => fullDayLeaveColumns(), 'time_off_id' => null, 'plan' => ['signature' => ''],
    ]);

    expect(leavesOf($this->leaver))->toHaveCount(1);
});

it('TO-03: a crafted confirm with a backwards hourly leave is refused', function () {
    craftedConfirm(Livewire::test(ViewProvider::class, ['record' => $this->leaver->getRouteKey()]), [
        'leave' => [
            ...fullDayLeaveColumns(),
            'type' => ProviderTimeOff::TYPE_HOURLY,
            'start_time' => '22:00',
            'end_time' => '02:00',
        ],
        'time_off_id' => null,
        'plan' => ['signature' => ''],
    ])->assertNotified(__('dashboard.time_off_modal.end_time_after_start'));

    expect(leavesOf($this->leaver))->toBeEmpty();
});

it('completes an edit that stretches a leave over a booking through both modals', function () {
    $leaveId = $this->salon->giveFullDayLeave($this->leaver, '2026-09-10', '2026-09-10');
    $booking = filamentLeaveBooking($this->leaver);

    Livewire::test(TimeOffsRelationManager::class, ['ownerRecord' => $this->leaver, 'pageClass' => ViewProvider::class])
        ->callAction(TestAction::make('edit')->table(ProviderTimeOff::find($leaveId)), [
            ...fullDayLeaveColumns(),
            'end_date' => '2026-09-10',
        ])
        ->assertActionMounted('resolveLeaveConflicts')
        ->callMountedAction()
        ->assertActionMounted('confirmLeaveReassignment')
        ->callMountedAction();

    expect(Carbon::parse(ProviderTimeOff::find($leaveId)->start_date)->format('Y-m-d'))->toBe(SalonFixture::DATE)
        ->and(leavesOf($this->leaver))->toHaveCount(1)
        ->and($booking->fresh()->provider_id)->toBe($this->salon->hourlyLeave->id);
});
