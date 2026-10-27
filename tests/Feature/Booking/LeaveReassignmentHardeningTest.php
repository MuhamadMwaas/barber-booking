<?php

/**
 * Regressions for the review of the leave-reassignment feature
 * (docs/06-booking-flow/time-off-implementation-review-ar.md), service level.
 * Each test names the finding it closes.
 *
 * `available` and `hourlyLeave` are the only fixture providers who can take a
 * 60-minute `service` booking on SalonFixture::DATE (see SalonFixture).
 */

use App\Enum\AppointmentStatus;
use App\Exceptions\LeaveReassignmentRequiredException;
use App\Mail\BookingCancelledProviderUnavailableMail;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\User;
use App\Notifications\PhoneNotification;
use App\Services\LeaveReassignmentService;
use App\Services\NotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->service = app(LeaveReassignmentService::class);
    $this->reasonId = ReasonLeave::firstOrCreate(['name' => 'Sick'])->id;
});

afterEach(fn () => Carbon::setTestNow());

function hardeningProvider(string $name, ?int $branchId): User
{
    $provider = User::factory()->create(['first_name' => $name, 'branch_id' => $branchId, 'is_active' => true]);
    $provider->assignRole('provider');
    ProviderScheduledWork::create([
        'user_id' => $provider->id,
        'day_of_week' => Carbon::parse(SalonFixture::DATE)->dayOfWeek,
        'start_time' => '09:00', 'end_time' => '17:00',
        'is_work_day' => true, 'is_active' => true,
    ]);

    return $provider;
}

function hardeningBooking(User $provider, string $from, string $to, $service = null): Appointment
{
    $appointment = test()->salon->bookSlot($provider, SalonFixture::DATE, $from, $to);
    $service ??= test()->salon->service;
    AppointmentService::create([
        'appointment_id' => $appointment->id, 'service_id' => $service->id, 'service_name' => $service->name,
        'duration_minutes' => 60, 'price' => 100, 'sequence_order' => 1,
    ]);

    return $appointment;
}

function hardeningLeave(User $provider, array $overrides = []): ProviderTimeOff
{
    return new ProviderTimeOff($overrides + [
        'user_id' => $provider->id,
        'type' => ProviderTimeOff::TYPE_FULL_DAY,
        'start_date' => SalonFixture::DATE,
        'end_date' => SalonFixture::DATE,
        'reason_id' => test()->reasonId,
    ]);
}

/**
 * `doesNotOffer` going on leave with a secondService booking at 10:00, and
 * `available` (the only other provider of it) busy then: the booking can only
 * be cancelled.
 */
function hardeningCancelOnly(): array
{
    test()->salon->bookSlot(test()->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $leaver = test()->salon->doesNotOffer;
    $booking = hardeningBooking($leaver, '10:00', '11:00', test()->salon->secondService);

    return [$leaver, $booking];
}

// ── TO-03: the service validates the leave itself ───────────────────────────

it('TO-03: refuses a backwards hourly leave whatever the caller', function () {
    $leave = hardeningLeave($this->salon->notWorking, [
        'type' => ProviderTimeOff::TYPE_HOURLY, 'start_time' => '22:00', 'end_time' => '02:00',
    ]);

    expect(fn () => $this->service->grant($leave))->toThrow(InvalidArgumentException::class);
    expect(ProviderTimeOff::where('user_id', $this->salon->notWorking->id)->exists())->toBeFalse();
});

it('TO-03: refuses an hourly leave without times, a missing reason and an inverted range', function (array $overrides) {
    $leave = hardeningLeave($this->salon->notWorking, $overrides);

    expect(fn () => $this->service->grant($leave))->toThrow(InvalidArgumentException::class);
})->with([
    'no times' => [['type' => ProviderTimeOff::TYPE_HOURLY]],
    'no reason' => [['reason_id' => null]],
    'unknown reason' => [['reason_id' => 999999]],
    'end before start' => [['end_date' => '2026-09-08']],
    'unknown type' => [['type' => 7]],
]);

// ── TO-04: the signature covers what the user saw ───────────────────────────

it('TO-04: a booking whose time changed voids the confirmation, even with the same colleague', function () {
    $leaver = $this->salon->available;
    $booking = hardeningBooking($leaver, '09:00', '10:00');
    $leave = hardeningLeave($leaver);
    $plan = $this->service->plan($leave);

    $booking->update(['start_time' => SalonFixture::DATE.' 10:00:00', 'end_time' => SalonFixture::DATE.' 11:00:00']);

    expect(fn () => $this->service->grant($leave, $plan['signature']))
        ->toThrow(fn (LeaveReassignmentRequiredException $e) => expect($e->changed)->toBeTrue()
            ->and($e->plan['rows'][0]['start_time'])->toBe('10:00'));

    expect($booking->fresh()->provider_id)->toBe($leaver->id);
});

it('TO-04: a confirmation does not carry over to different leave dates', function () {
    $leaver = $this->salon->available;
    hardeningBooking($leaver, '10:00', '11:00');
    $plan = $this->service->plan(hardeningLeave($leaver));

    $longer = hardeningLeave($leaver, ['end_date' => '2026-09-10']);

    expect(fn () => $this->service->grant($longer, $plan['signature']))
        ->toThrow(LeaveReassignmentRequiredException::class);
});

// ── TO-05: keep bookings first, balance second ──────────────────────────────

it('TO-05: finds the hand-out that saves both overlapping bookings', function () {
    // Own branch, so none of the fixture providers are candidates.
    $branch = $this->salon->otherBranch->id;
    $leaver = hardeningProvider('Leaver', $branch);
    $flexible = hardeningProvider('Flexible', $branch);     // offers A + B, no bookings
    $restricted = hardeningProvider('Restricted', $branch); // offers A only, one booking at 16:00
    $this->salon->bookSlot($this->salon->otherBranchProvider, SalonFixture::DATE, '09:00', '17:00');
    $a = $this->salon->service;
    $b = $this->salon->secondService;
    foreach ([[$leaver, $a], [$leaver, $b], [$flexible, $a], [$flexible, $b], [$restricted, $a]] as [$provider, $service]) {
        $this->salon->offerService($provider, $service);
    }
    $this->salon->bookSlot($restricted, SalonFixture::DATE, '16:00', '17:00');

    $first = hardeningBooking($leaver, '10:00', '11:00', $a);
    $second = hardeningBooking($leaver, '10:00', '11:00', $b);   // forced overlap

    $plan = $this->service->plan(hardeningLeave($leaver));
    $rows = collect($plan['rows'])->keyBy('appointment_id');

    expect($plan['cancellations'])->toBe(0)
        ->and($rows[$first->id]['target_provider_id'])->toBe($restricted->id)
        ->and($rows[$second->id]['target_provider_id'])->toBe($flexible->id);
});

// ── TO-06: notices only after the outermost commit ──────────────────────────

it('TO-06: sends nothing when an enclosing transaction rolls back', function () {
    Mail::fake();
    Notification::fake();
    [$leaver, $booking] = hardeningCancelOnly();
    $leave = hardeningLeave($leaver);
    $plan = $this->service->plan($leave);

    DB::beginTransaction();
    $this->service->grant($leave, $plan['signature']);
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    DB::rollBack();

    expect($booking->fresh()->status)->toBe(AppointmentStatus::PENDING);
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});

it('TO-06: sends the notices once the enclosing transaction commits', function () {
    Mail::fake();
    Notification::fake();
    [$leaver] = hardeningCancelOnly();
    $leave = hardeningLeave($leaver);
    $plan = $this->service->plan($leave);

    DB::transaction(fn () => $this->service->grant($leave, $plan['signature']));

    Mail::assertQueuedCount(1);
    Notification::assertSentTo($this->salon->filler, PhoneNotification::class);
});

// ── TO-07: each channel on its own ──────────────────────────────────────────

it('TO-07: the app notice still goes out when the mail cannot be queued', function () {
    Notification::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('mail transport down'));
    [$leaver, $booking] = hardeningCancelOnly();
    $leave = hardeningLeave($leaver);

    $this->service->grant($leave, $this->service->plan($leave)['signature']);

    expect($booking->fresh()->status)->toBe(AppointmentStatus::ADMIN_CANCELLED);
    Notification::assertSentTo(
        $this->salon->filler,
        PhoneNotification::class,
        fn (PhoneNotification $notification) => $notification->data['appointment_id'] === $booking->id,
    );
});

it('TO-07: the mail still goes out when the app notice fails', function () {
    Mail::fake();
    $this->mock(NotificationService::class)
        ->shouldReceive('sendNotificationToUser')->andThrow(new RuntimeException('push down'));
    [$leaver] = hardeningCancelOnly();
    $leave = hardeningLeave($leaver);

    $this->service->grant($leave, $this->service->plan($leave)['signature']);

    Mail::assertQueued(BookingCancelledProviderUnavailableMail::class);
});

// ── The cancel permission is judged under the lock ──────────────────────────

it('refuses a plan that cancels when the caller may not cancel, and writes nothing', function () {
    [$leaver, $booking] = hardeningCancelOnly();
    $leave = hardeningLeave($leaver);
    $plan = $this->service->plan($leave);

    expect(fn () => $this->service->grant($leave, $plan['signature'], mayCancel: false))
        ->toThrow(AuthorizationException::class);

    expect($booking->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and(ProviderTimeOff::where('user_id', $leaver->id)->exists())->toBeFalse();
});

it('lets a caller without the cancel permission grant a leave that only moves bookings', function () {
    $leaver = $this->salon->available;
    hardeningBooking($leaver, '10:00', '11:00');
    $leave = hardeningLeave($leaver);

    $result = $this->service->grant($leave, $this->service->plan($leave)['signature'], mayCancel: false);

    expect($result['moved'])->toBe(1);
});

// ── 6.1: the booking is re-checked under its own lock ───────────────────────

it('6.1: does not move a booking that was paid between the plan and the write', function () {
    $leaver = $this->salon->available;
    $booking = hardeningBooking($leaver, '10:00', '11:00');
    $leave = hardeningLeave($leaver);
    $plan = $this->service->plan($leave);

    // Stand-in for a concurrent payment: it lands right after grant() has
    // rebuilt its plan, when the booking's group lock is first asked for.
    $paid = false;
    DB::listen(function ($query) use (&$paid, $booking) {
        if (! $paid && str_contains($query->sql, 'COALESCE(parent_appointment_id, id)')) {
            $paid = true;
            DB::table('appointments')->where('id', $booking->id)->update(['status' => AppointmentStatus::COMPLETED->value]);
        }
    });

    expect(fn () => $this->service->grant($leave, $plan['signature']))
        ->toThrow(fn (LeaveReassignmentRequiredException $e) => expect($e->changed)->toBeTrue());

    expect($booking->fresh()->provider_id)->toBe($leaver->id)
        ->and(ProviderTimeOff::where('user_id', $leaver->id)->exists())->toBeFalse();
});

// ── Past-due bookings: ignored ──────────────────────────────────────────────

it('ignores open bookings whose start time has already come, and tells nobody', function () {
    Mail::fake();
    Notification::fake();
    Carbon::setTestNow(SalonFixture::DATE.' 11:00:00');
    $leaver = $this->salon->available;
    // Nobody can take 09:00 or 10:30 either way — they must simply not count.
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '09:00', '12:00');
    $noShow = hardeningBooking($leaver, '09:00', '10:00');
    $inProgress = hardeningBooking($leaver, '10:30', '11:30');
    $upcoming = hardeningBooking($leaver, '15:00', '16:00');   // hourlyLeave is away 11:00-14:00

    $plan = $this->service->plan(hardeningLeave($leaver));
    expect(collect($plan['rows'])->pluck('appointment_id')->all())->toBe([$upcoming->id]);

    $this->service->grant(hardeningLeave($leaver), $plan['signature']);

    expect($noShow->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and($noShow->fresh()->provider_id)->toBe($leaver->id)
        ->and($inProgress->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and($upcoming->fresh()->provider_id)->toBe($this->salon->hourlyLeave->id);
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});

it('grants a leave straight away when its only open bookings are already past', function () {
    Carbon::setTestNow(SalonFixture::DATE.' 11:00:00');
    $leaver = $this->salon->available;
    hardeningBooking($leaver, '09:00', '10:00');

    $result = $this->service->grant(hardeningLeave($leaver));

    expect($result['time_off']->exists)->toBeTrue();
});

// ── 6.2: the same branch, strictly ──────────────────────────────────────────

it('6.2: a provider without a branch never draws colleagues from a branch', function () {
    $leaver = hardeningProvider('NoBranch', null);
    $this->salon->offerService($leaver, $this->salon->service);
    hardeningBooking($leaver, '10:00', '11:00');

    $plan = $this->service->plan(hardeningLeave($leaver));

    expect($plan['rows'][0]['action'])->toBe(LeaveReassignmentService::ACTION_CANCEL);
});

it('6.2: colleagues of another branch are never candidates', function () {
    // Both own-branch candidates busy; otherBranchProvider is free but elsewhere.
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');
    $leaver = hardeningProvider('MainBranch', $this->salon->branch->id);
    $this->salon->offerService($leaver, $this->salon->service);
    hardeningBooking($leaver, '10:00', '11:00');

    $plan = $this->service->plan(hardeningLeave($leaver));

    expect($plan['rows'][0]['action'])->toBe(LeaveReassignmentService::ACTION_CANCEL);
});
