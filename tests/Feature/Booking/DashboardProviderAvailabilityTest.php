<?php

/**
 * The StaffDashboard booking modal must list providers by the same rules the
 * booking itself is validated with, and must say WHY a provider is unavailable
 * instead of silently dropping them.
 *
 * Reported case: a provider appears as working on the dashboard, but vanishes
 * from the modal for a service they are listed on — their provider_service link
 * was switched off (grey dot on the service page), and nothing on screen said so.
 */

use App\Enum\AppointmentStatus;
use App\Livewire\StaffDashboard;
use App\Models\Language;
use App\Models\User;
use App\Services\BookingValidationService;
use App\Services\DashboardService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Carbon::setTestNow(SalonFixture::NOW);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Modal rows for the fixture service, keyed by provider id. */
function modalRows(string $start = '10:00', int $minutes = 60, bool $bypass = false, ?string $date = null): array
{
    return collect(app(DashboardService::class)->getProviderAvailabilityForServiceAtTime(
        test()->salon->service->id,
        $date ?? SalonFixture::DATE,
        $start,
        $minutes,
        $bypass,
    ))->keyBy('id')->all();
}

function reasonFor(User $provider, string $start = '10:00', bool $bypass = false, ?string $date = null): ?string
{
    $rows = modalRows($start, 60, $bypass, $date);
    expect($rows)->toHaveKey($provider->id);

    return $rows[$provider->id]['reason'];
}

it('lists a switched-off provider_service link as unavailable with a reason instead of dropping it', function () {
    $row = modalRows()[$this->salon->inactivePivot->id];

    expect($row['available'])->toBeFalse()
        ->and($row['reason'])->toBe('service_disabled')
        ->and($row['reason_label'])->toBe(__('dashboard.booking_modal.unavailable_reason.service_disabled'));
});

it('gives each excluded provider the reason that excludes them', function () {
    $salon = $this->salon;

    expect(reasonFor($salon->available))->toBeNull()
        ->and(reasonFor($salon->notWorking))->toBe('not_working')
        ->and(reasonFor($salon->onLeave))->toBe('on_leave')
        ->and(reasonFor($salon->fullyBooked))->toBe('busy')
        ->and(reasonFor($salon->hourlyLeave, '10:00'))->toBeNull()
        ->and(reasonFor($salon->hourlyLeave, '11:00'))->toBe('on_leave')
        ->and(reasonFor($salon->available, '16:30'))->toBe('outside_hours');
});

it('never lists providers who are not linked to the service or whose account is disabled', function () {
    expect(modalRows())
        ->not->toHaveKey($this->salon->doesNotOffer->id)
        ->not->toHaveKey($this->salon->inactiveUser->id);
});

it('puts available providers first', function () {
    $flags = array_column(array_values(modalRows()), 'available');

    expect($flags)->toBe(array_values(array_merge(
        array_filter($flags),
        array_filter($flags, fn ($flag) => ! $flag),
    )));
});

it('keeps getAvailableProvidersForServiceAtTime returning only bookable providers in its old shape', function () {
    $rows = app(DashboardService::class)->getAvailableProvidersForServiceAtTime(
        $this->salon->service->id, SalonFixture::DATE, '10:00', 60,
    );

    expect(collect($rows)->pluck('id'))
        ->toContain($this->salon->available->id)
        ->not->toContain($this->salon->inactivePivot->id)
        ->not->toContain($this->salon->onLeave->id);

    expect(array_keys($rows[0]))->toBe(['id', 'first_name', 'last_name', 'name']);
});

it('reads a full-day leave with no end date as that single day (BOOK-04)', function () {
    $this->salon->giveFullDayLeave($this->salon->available, SalonFixture::DATE, null);

    expect(reasonFor($this->salon->available))->toBe('on_leave');

    $status = app(DashboardService::class)->getProvidersWithStatus(SalonFixture::DATE)
        ->firstWhere('id', $this->salon->available->id);
    expect($status['has_day_off'])->toBeTrue();
});

it('treats a multi-day hourly leave as one continuous absence', function () {
    $previousDay = Carbon::parse(SalonFixture::DATE)->subDay()->toDateString();
    $nextDay = Carbon::parse(SalonFixture::DATE)->addDay()->toDateString();
    $this->salon->giveHourlyLeave($this->salon->available, $previousDay, '15:00:00', '12:00:00', $nextDay);

    // The target day is the middle day: fully blocked, although 10:00 is outside
    // the 15:00-12:00 "times" of the row.
    expect(reasonFor($this->salon->available, '10:00'))->toBe('on_leave');

    $status = app(DashboardService::class)->getProvidersWithStatus(SalonFixture::DATE)
        ->firstWhere('id', $this->salon->available->id);
    expect($status['has_day_off'])->toBeTrue();
});

it('does not treat a no-show as blocking the chair, matching the booking rule', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00', status: AppointmentStatus::NO_SHOW);

    expect(reasonFor($this->salon->available))->toBeNull();
});

it('lets force booking bypass the availability window but not a disabled link or a clash', function () {
    $salon = $this->salon;

    expect(reasonFor($salon->onLeave, bypass: true))->toBeNull()
        ->and(reasonFor($salon->notWorking, bypass: true))->toBeNull()
        ->and(reasonFor($salon->inactivePivot, bypass: true))->toBe('service_disabled')
        ->and(reasonFor($salon->fullyBooked, bypass: true))->toBe('busy');
});

it('marks available exactly the providers the booking validation accepts', function () {
    $validator = app(BookingValidationService::class);
    $service = $this->salon->service;
    $start = Carbon::parse(SalonFixture::DATE.' 10:00');
    $end = $start->copy()->addMinutes(60);

    foreach (modalRows() as $id => $row) {
        $provider = User::find($id);

        try {
            $validator->validateProviderOffersService($provider, $service);
            $validator->validateTimeSlotAvailability($provider, $service, $start, $end);
            $accepted = true;
        } catch (Throwable) {
            $accepted = false;
        }

        expect($row['available'])->toBe($accepted, "{$provider->first_name}: dashboard and validation disagree");
    }
});

it('returns unavailable providers with reasons from the Livewire modal endpoint', function () {
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);
    Permission::findOrCreate('StaffDashboard:access', 'web');
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    \Spatie\Permission\Models\Role::findByName('admin', 'web')->givePermissionTo('StaffDashboard:access');

    $rows = Livewire::actingAs($admin)
        ->test(StaffDashboard::class)
        ->set('selectedDate', SalonFixture::DATE)
        ->instance()
        ->getAvailableProvidersForBooking($this->salon->service->id, '10:00', 60);

    $row = collect($rows)->firstWhere('id', $this->salon->inactivePivot->id);
    expect($row)->not->toBeNull()
        ->and($row['available'])->toBeFalse()
        ->and($row['reason'])->toBe('service_disabled');
});
