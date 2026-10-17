<?php

/**
 * BOOK-02 — every path that writes a provider's calendar must decide "is this
 * slot free?" AFTER taking the provider lock, inside the same transaction.
 *
 * How the race is reproduced on a single connection
 * -------------------------------------------------
 * A real race needs two connections, and SQLite (the test driver) ignores
 * FOR UPDATE anyway. So the competitor is injected instead: a spy around
 * BookingLockService::lockUsers() commits a competing booking at the exact
 * moment the lock is requested — i.e. "the other request finished just before
 * we got the lock". Every check made BEFORE that moment saw a free slot. Only
 * a re-check made AFTER the lock can see the competitor, so a path that still
 * trusts its pre-lock answer double-books here, and a fixed one refuses.
 *
 * The real two-connection race on MySQL is exercised separately by
 * tests/Concurrency/book02_mysql_race.php.
 */

use App\Enum\AppointmentStatus;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Models\Appointment;
use App\Models\User;
use App\Services\BookingLockService;
use App\Services\BookingService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Run $competitor the first time any path asks for the provider lock.
 * Returns the spy so a test can also assert that the lock was (not) taken.
 */
function competitorOnLock(?callable $competitor = null): BookingLockService
{
    $spy = new class($competitor) extends BookingLockService {
        public array $calls = [];

        public function __construct(private $competitor) {}

        public function lockUsers(array $userIds): void
        {
            if ($this->calls === [] && $this->competitor) {
                ($this->competitor)();
            }

            $this->calls[] = $userIds;

            parent::lockUsers($userIds);
        }
    };

    app()->instance(BookingLockService::class, $spy);

    return $spy;
}

/** The invariant itself: no two time-blocking appointments of one provider overlap. */
function overlappingPairs(User $provider): int
{
    $rows = Appointment::where('provider_id', $provider->id)
        ->blocksProviderTime()
        ->orderBy('start_time')
        ->get();

    $pairs = 0;
    foreach ($rows as $i => $a) {
        foreach ($rows->slice($i + 1) as $b) {
            if ($a->start_time < $b->end_time && $b->start_time < $a->end_time) {
                $pairs++;
            }
        }
    }

    return $pairs;
}

function raceBooking(string $start = '10:00'): Appointment
{
    $salon = test()->salon;

    return app(BookingService::class)->createBooking($salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => $start,
        ]],
    ]);
}

/** Named apart from AppointmentDeletionTest's panelUser(): Pest shares one global scope. */
function raceAdmin(array $extraPermissions = []): User
{
    $permissions = ['Appointment:access', 'Appointment:view', 'Appointment:create', 'Appointment:edit', ...$extraPermissions];

    Role::findOrCreate('admin', 'web');
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::create([
        'first_name' => 'Race',
        'last_name' => 'Admin',
        'email' => 'race-admin-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $user->assignRole('admin');
    $user->givePermissionTo($permissions);

    return $user->fresh();
}

// ── 1. API / StaffDashboard: BookingService::createBooking ───────────────────

it('refuses an API booking when a competitor takes the slot just before the lock', function () {
    competitorOnLock(fn () => $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00'));

    $this->withToken($this->salon->customerToken())->postJson('/api/bookings', [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ]],
    ])->assertStatus(409)->assertJsonPath('error_type', 'slot_conflict');

    // The 409 is the proof: every check before the lock saw a free slot.
    // (The competitor itself was written on our connection inside our
    // transaction, so the rollback removes it too — on MySQL it would be a
    // separate, committed request. What matters: the customer got nothing.)
    expect(Appointment::where('customer_id', $this->salon->customer->id)->count())->toBe(0);
});

// ── 2. Filament: create page ─────────────────────────────────────────────────

it('refuses a Filament-created appointment when a competitor takes the slot just before the lock', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(raceAdmin());
    $undoRepeaterFake = Repeater::fake();

    $spy = competitorOnLock(fn () => $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00'));

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'customer_type' => 'registered',
            'customer_id' => $this->salon->customer->id,
            'services_record' => [[
                'service_id' => $this->salon->service->id,
                'service_name' => $this->salon->service->name,
                'duration_minutes' => 60,
                'price' => 100,
                'sequence_order' => 1,
            ]],
            'appointment_date' => SalonFixture::DATE,
        ])
        // The price field's hook zeroes its own row's duration (it calls
        // calculateTotals() with paths relative to the row) — put it back,
        // then pick the slot, which derives the total duration from the rows.
        ->set('data.services_record.0.duration_minutes', 60)
        ->fillForm([
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
            'payment_method' => 'cash',
        ])
        ->call('create')
        ->assertNotified(__('messages.appointment.validation_error'));

    $undoRepeaterFake();

    // beforeCreate() saw a free slot; only the re-check under the lock caught
    // it. (The competitor rolls back with our transaction — see the API test.)
    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0])->toContain($this->salon->available->id)
        ->and($spy->calls[0])->toContain($this->salon->customer->id)
        ->and(Appointment::where('customer_id', $this->salon->customer->id)->count())->toBe(0);
});

// ── 3. Filament: edit page ───────────────────────────────────────────────────

it('refuses moving an appointment onto a taken slot from the Filament edit page', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(raceAdmin());

    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $moving = raceBooking('14:00');

    Livewire::test(EditAppointment::class, ['record' => $moving->getRouteKey()])
        ->fillForm(['start_time' => '10:30'])
        ->call('save')
        ->assertNotified(__('messages.appointment.validation_error'));

    expect($moving->fresh()->start_time->format('Y-m-d H:i'))->toBe(SalonFixture::DATE.' 14:00')
        ->and(overlappingPairs($this->salon->available))->toBe(0);
});

it('re-checks an edit-page move under the lock', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(raceAdmin());

    $moving = raceBooking('14:00');
    $spy = competitorOnLock(fn () => $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00'));

    Livewire::test(EditAppointment::class, ['record' => $moving->getRouteKey()])
        ->fillForm(['start_time' => '10:00'])
        ->call('save')
        ->assertNotified(__('messages.appointment.validation_error'));

    expect($spy->calls)->toHaveCount(1)
        ->and($moving->fresh()->start_time->format('H:i'))->toBe('14:00')
        ->and(overlappingPairs($this->salon->available))->toBe(0);
});

it('refuses reviving a cancelled appointment onto a slot someone else now holds', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(raceAdmin());

    $cancelled = raceBooking('10:00');
    $cancelled->update(['status' => AppointmentStatus::ADMIN_CANCELLED]);

    // The freed slot was rebooked by someone else.
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');

    Livewire::test(EditAppointment::class, ['record' => $cancelled->getRouteKey()])
        ->fillForm(['status' => AppointmentStatus::PENDING->value])
        ->call('save')
        ->assertNotified(__('messages.appointment.validation_error'));

    expect($cancelled->fresh()->status)->toBe(AppointmentStatus::ADMIN_CANCELLED)
        ->and(overlappingPairs($this->salon->available))->toBe(0);
});

it('moves to a free slot and keeps the appointment on its own date', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(raceAdmin());

    $moving = raceBooking('14:00');

    Livewire::test(EditAppointment::class, ['record' => $moving->getRouteKey()])
        ->fillForm(['start_time' => '15:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    // The form sends a bare hour; it used to land on TODAY's date
    // (2026-09-07) instead of the appointment's own (2026-09-09).
    $moved = $moving->fresh();
    expect($moved->start_time->format('Y-m-d H:i'))->toBe(SalonFixture::DATE.' 15:00')
        ->and($moved->end_time->format('Y-m-d H:i'))->toBe(SalonFixture::DATE.' 16:00');
});

it('saves a notes-only edit without locking, even on a deliberately overlapped appointment', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(raceAdmin());

    $appointment = raceBooking('10:00');
    // A force booking put a second appointment on top of it on purpose.
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');

    $spy = competitorOnLock();

    Livewire::test(EditAppointment::class, ['record' => $appointment->getRouteKey()])
        ->fillForm(['notes' => 'Prefers the window seat'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($appointment->fresh()->notes)->toBe('Prefers the window seat')
        ->and($spy->calls)->toBe([]);
});

it('lets a force_booking holder overlap on purpose from the edit page', function () {
    Filament::setCurrentPanel('admin');
    Permission::findOrCreate('StaffDashboard:force_booking', 'web');
    $this->actingAs(raceAdmin(['StaffDashboard:force_booking']));

    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $moving = raceBooking('14:00');

    Livewire::test(EditAppointment::class, ['record' => $moving->getRouteKey()])
        ->fillForm(['start_time' => '10:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($moving->fresh()->start_time->format('H:i'))->toBe('10:00');
});

// ── 4. StaffDashboard: add a service with a push ─────────────────────────────

it('re-plans a push under the lock instead of executing a stale plan', function () {
    // 10:00 anchor, 11:00 next booking. Adding a 60-minute service after the
    // anchor needs the 11:00 booking pushed to 12:00 — that is the plan made
    // before the lock. Then a competitor lands at 12:00 before we get the lock.
    $anchor = raceBooking('10:00');
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '11:00', '12:00');

    competitorOnLock(fn () => $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '12:00', '13:00'));

    $result = app(BookingService::class)->addServiceToBooking($anchor, [
        'service_id' => $this->salon->secondService->id,
        'provider_id' => $this->salon->available->id,
        'placement' => 'after',
        'apply_push' => true,
    ]);

    // The stale plan pushed 11:00 → 12:00 straight onto the competitor. The
    // plan made under the lock pushes the competitor along too.
    expect($result['pushed_appointments'])->toHaveCount(2)
        ->and(overlappingPairs($this->salon->available))->toBe(0);
});

