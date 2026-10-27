<?php

/**
 * The StaffDashboard leave modal over open bookings: saving returns the plan
 * instead of the leave, confirming applies it (LeaveReassignmentService).
 *
 * `available` goes on leave; at 10:00 only `hourlyLeave` can take its booking.
 */

use App\Enum\AppointmentStatus;
use App\Livewire\StaffDashboard;
use App\Models\AppointmentService;
use App\Models\Language;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);

    foreach (['access', 'manage_timeoff', 'cancel_appointment'] as $ability) {
        Permission::findOrCreate("StaffDashboard:{$ability}", 'web');
    }
    Role::findByName('admin', 'web')->givePermissionTo([
        'StaffDashboard:access', 'StaffDashboard:manage_timeoff', 'StaffDashboard:cancel_appointment',
    ]);
    Role::findByName('manager', 'web')->givePermissionTo([
        'StaffDashboard:access', 'StaffDashboard:manage_timeoff',
    ]);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->leaver = $this->salon->available;

    $this->booking = $this->salon->bookSlot($this->leaver, SalonFixture::DATE, '10:00', '11:00');
    AppointmentService::create([
        'appointment_id' => $this->booking->id,
        'service_id' => $this->salon->service->id,
        'service_name' => $this->salon->service->name,
        'duration_minutes' => 60,
        'price' => 100.00,
        'sequence_order' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function dashboardLeavePayload(): array
{
    return [
        'providerId' => test()->leaver->id,
        'type' => (string) ProviderTimeOff::TYPE_FULL_DAY,
        'startDate' => SalonFixture::DATE,
        'endDate' => SalonFixture::DATE,
        'startTime' => '',
        'endTime' => '',
        'reasonId' => ReasonLeave::firstOrCreate(['name' => 'Sick'])->id,
    ];
}

function dashboardAs(User $user)
{
    return Livewire::actingAs($user)->test(StaffDashboard::class);
}

it('returns the reassignment plan instead of saving a leave over open bookings', function () {
    $component = dashboardAs($this->admin)->call('saveTimeOffFromAlpine', dashboardLeavePayload());

    $result = $component->effects['returns'][0];

    expect($result['status'])->toBe('conflicts')
        ->and($result['plan']['rows'][0])
        ->appointment_id->toBe($this->booking->id)
        ->target_provider_name->toBe($this->salon->hourlyLeave->full_name)
        ->and(ProviderTimeOff::where('user_id', $this->leaver->id)->exists())->toBeFalse();

    $component->assertNotDispatched('timeoff-saved');
});

it('moves the bookings and saves the leave once the plan is confirmed', function () {
    $component = dashboardAs($this->admin)->call('saveTimeOffFromAlpine', dashboardLeavePayload());
    $signature = $component->effects['returns'][0]['plan']['signature'];

    $component->call('confirmTimeOffReassignment', dashboardLeavePayload(), $signature)
        ->assertDispatched('timeoff-saved');

    expect($component->effects['returns'][0]['status'])->toBe('saved')
        ->and($this->booking->fresh()->provider_id)->toBe($this->salon->hourlyLeave->id)
        ->and(ProviderTimeOff::where('user_id', $this->leaver->id)->exists())->toBeTrue();
});

it('returns the fresh plan when the calendar changed before confirming', function () {
    $component = dashboardAs($this->admin)->call('saveTimeOffFromAlpine', dashboardLeavePayload());
    $signature = $component->effects['returns'][0]['plan']['signature'];

    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');

    $component->call('confirmTimeOffReassignment', dashboardLeavePayload(), $signature);
    $result = $component->effects['returns'][0];

    expect($result['status'])->toBe('conflicts')
        ->and($result['changed'])->toBeTrue()
        ->and($result['plan']['cancellations'])->toBe(1)
        ->and($this->booking->fresh()->provider_id)->toBe($this->leaver->id);
});

it('needs the cancel permission to confirm a plan that cancels bookings', function () {
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('manager');

    $component = dashboardAs($manager)->call('saveTimeOffFromAlpine', dashboardLeavePayload());
    $signature = $component->effects['returns'][0]['plan']['signature'];

    $component->call('confirmTimeOffReassignment', dashboardLeavePayload(), $signature);

    expect($component->effects['returns'][0]['status'])->toBe('denied')
        ->and($this->booking->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and(ProviderTimeOff::where('user_id', $this->leaver->id)->exists())->toBeFalse();
});

it('builds a fresh plan when "try reassigning" is clicked, not the one from the first save', function () {
    $component = dashboardAs($this->admin)->call('saveTimeOffFromAlpine', dashboardLeavePayload());
    expect($component->effects['returns'][0]['plan']['moves'])->toBe(1);

    // The only free colleague gets booked while the warning is on screen.
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');

    $component->call('planTimeOffReassignment', dashboardLeavePayload());
    $result = $component->effects['returns'][0];

    expect($result['status'])->toBe('plan')
        ->and($result['plan']['rows'][0]['action'])->toBe('cancel')
        ->and($result['plan']['rows'][0]['number'])->toBe($this->booking->number);
});

it('refuses a planning request from a user who may not manage leaves', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->assignRole('provider');
    Permission::findOrCreate('StaffDashboard:access', 'web');
    $viewer->givePermissionTo('StaffDashboard:access');

    $component = dashboardAs($viewer)->call('planTimeOffReassignment', dashboardLeavePayload());

    expect($component->effects['returns'][0]['status'])->toBe('invalid');
});
