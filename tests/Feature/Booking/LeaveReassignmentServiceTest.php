<?php

/**
 * A leave is only granted over an empty calendar: the open bookings it covers
 * move to free colleagues (same time, same price), the ones nobody can take are
 * cancelled with the customer told why — LeaveReassignmentService.
 *
 * Candidates on SalonFixture::DATE for the 60-minute `service` (09:00-17:00):
 * only `available` and `hourlyLeave` (away 11:00-14:00) qualify — every other
 * fixture provider is excluded for a different reason.
 */

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\LeaveReassignmentRequiredException;
use App\Mail\BookingCancelledProviderUnavailableMail;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PhoneNotification;
use App\Services\LeaveReassignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Mail::fake();
    Notification::fake();

    // The provider going on leave: same branch and shift as the candidates.
    $this->leaver = User::factory()->create([
        'first_name' => 'Leaver',
        'last_name' => 'Provider',
        'branch_id' => $this->salon->branch->id,
        'is_active' => true,
    ]);
    $this->leaver->assignRole('provider');
    ProviderScheduledWork::create([
        'user_id' => $this->leaver->id,
        'day_of_week' => Carbon::parse(SalonFixture::DATE)->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'is_work_day' => true,
        'is_active' => true,
    ]);
    $this->salon->offerService($this->leaver, $this->salon->service);
    $this->salon->offerService($this->leaver, $this->salon->secondService);

    $this->service = app(LeaveReassignmentService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** An open booking of $provider with real service lines, as the booking flow stores it. */
function leaveBooking(User $provider, string $from, string $to, array $services = [], array $overrides = []): Appointment
{
    $appointment = Appointment::create($overrides + [
        'number' => 'APT-'.str()->random(10),
        'provider_id' => $provider->id,
        'customer_id' => test()->salon->customer->id,
        'appointment_date' => SalonFixture::DATE,
        'start_time' => SalonFixture::DATE." {$from}:00",
        'end_time' => SalonFixture::DATE." {$to}:00",
        'duration_minutes' => 60,
        'subtotal' => 84.03,
        'tax_amount' => 15.97,
        'total_amount' => 100.00,
        'status' => AppointmentStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'created_status' => 1,
    ]);

    foreach ($services ?: [test()->salon->service] as $order => $service) {
        /** @var Service $service */
        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => 60,
            'price' => 100.00,
            'sequence_order' => $order + 1,
        ]);
    }

    return $appointment;
}

function fullDayLeave(User $provider): ProviderTimeOff
{
    return new ProviderTimeOff([
        'user_id' => $provider->id,
        'type' => ProviderTimeOff::TYPE_FULL_DAY,
        'start_date' => SalonFixture::DATE,
        'end_date' => SalonFixture::DATE,
        'reason_id' => ReasonLeave::firstOrCreate(['name' => 'Sick'])->id,
    ]);
}

function hourlyLeave(User $provider, string $from, string $to): ProviderTimeOff
{
    return new ProviderTimeOff([
        'user_id' => $provider->id,
        'type' => ProviderTimeOff::TYPE_HOURLY,
        'start_date' => SalonFixture::DATE,
        'end_date' => SalonFixture::DATE,
        'start_time' => $from,
        'end_time' => $to,
        'reason_id' => ReasonLeave::firstOrCreate(['name' => 'Sick'])->id,
    ]);
}

function grantRequiresPlan(ProviderTimeOff $leave, ?string $signature = null): LeaveReassignmentRequiredException
{
    try {
        test()->service->grant($leave, $signature);
    } catch (LeaveReassignmentRequiredException $e) {
        return $e;
    }

    throw new RuntimeException('grant() saved the leave instead of asking for a plan.');
}

it('grants a leave over an empty calendar straight away', function () {
    $result = $this->service->grant(fullDayLeave($this->leaver));

    expect($result['time_off']->exists)->toBeTrue()
        ->and($result['moved'])->toBe(0)
        ->and($result['cancelled'])->toBe(0);
});

it('refuses to grant a leave over open bookings without a confirmed plan', function () {
    leaveBooking($this->leaver, '10:00', '11:00');

    $e = grantRequiresPlan(fullDayLeave($this->leaver));

    expect($e->changed)->toBeFalse()
        ->and($e->plan['rows'])->toHaveCount(1)
        ->and(ProviderTimeOff::where('user_id', $this->leaver->id)->exists())->toBeFalse();
});

it('ignores bookings that are not open or that the leave does not cover', function () {
    leaveBooking($this->leaver, '09:00', '10:00', overrides: ['status' => AppointmentStatus::COMPLETED]);
    leaveBooking($this->leaver, '10:00', '11:00', overrides: ['status' => AppointmentStatus::ADMIN_CANCELLED]);
    leaveBooking($this->leaver, '15:00', '16:00');   // outside the hourly window
    $inside = leaveBooking($this->leaver, '12:00', '13:00');

    $plan = $this->service->plan(hourlyLeave($this->leaver, '11:00', '14:00'));

    expect(collect($plan['rows'])->pluck('appointment_id')->all())->toBe([$inside->id]);
});

it('only hands a booking to a colleague who can really take it', function () {
    // 12:00 — hourlyLeave is away 11:00-14:00, so only `available` qualifies.
    $booking = leaveBooking($this->leaver, '12:00', '13:00');

    $plan = $this->service->plan(fullDayLeave($this->leaver));

    expect($plan['rows'][0])
        ->appointment_id->toBe($booking->id)
        ->action->toBe(LeaveReassignmentService::ACTION_MOVE)
        ->target_provider_id->toBe($this->salon->available->id);
});

it('requires the colleague to offer every service of the booking', function () {
    // Only `available` offers both services; doesNotOffer has the second only.
    leaveBooking($this->leaver, '10:00', '11:00', [$this->salon->service, $this->salon->secondService]);

    $plan = $this->service->plan(fullDayLeave($this->leaver));

    expect($plan['rows'][0]['target_provider_id'])->toBe($this->salon->available->id);
});

it('gives the booking to the colleague with the fewest bookings that day', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '16:00', '17:00');
    leaveBooking($this->leaver, '10:00', '11:00');

    $plan = $this->service->plan(fullDayLeave($this->leaver));

    expect($plan['rows'][0]['target_provider_id'])->toBe($this->salon->hourlyLeave->id);
});

it('counts the bookings the plan itself hands out', function () {
    // Two overlapping bookings (a forced double booking): they cannot both go
    // to `available`, and the second must not be planned on top of the first.
    $first = leaveBooking($this->leaver, '15:00', '16:00');
    $second = leaveBooking($this->leaver, '15:00', '16:00');

    $rows = collect($this->service->plan(fullDayLeave($this->leaver))['rows'])->keyBy('appointment_id');

    expect($rows[$first->id]['target_provider_id'])->toBe($this->salon->available->id)
        ->and($rows[$second->id]['target_provider_id'])->toBe($this->salon->hourlyLeave->id);
});

it('moves the bookings and grants the leave once the plan is confirmed', function () {
    $booking = leaveBooking($this->leaver, '12:00', '13:00');
    $leave = fullDayLeave($this->leaver);
    $plan = $this->service->plan($leave);

    $result = $this->service->grant($leave, $plan['signature']);

    $booking->refresh();
    expect($result['moved'])->toBe(1)
        ->and($result['time_off']->exists)->toBeTrue()
        ->and($booking->provider_id)->toBe($this->salon->available->id)
        ->and($booking->reassigned_from_provider_id)->toBe($this->leaver->id)
        ->and($booking->status)->toBe(AppointmentStatus::PENDING)
        ->and((string) $booking->total_amount)->toBe('100.00');

    // A move is silent: the customer is not told the provider changed.
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});

it('cancels the bookings nobody can take and tells the customer', function () {
    // Both candidates are busy at 10:00.
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');
    $booking = leaveBooking($this->leaver, '10:00', '11:00');
    $leave = fullDayLeave($this->leaver);
    $plan = $this->service->plan($leave);

    expect($plan['rows'][0]['action'])->toBe(LeaveReassignmentService::ACTION_CANCEL);

    $result = $this->service->grant($leave, $plan['signature']);

    expect($result['cancelled'])->toBe(1)
        ->and($booking->fresh()->status)->toBe(AppointmentStatus::ADMIN_CANCELLED)
        ->and($booking->fresh()->provider_id)->toBe($this->leaver->id);

    Mail::assertQueued(
        BookingCancelledProviderUnavailableMail::class,
        fn (BookingCancelledProviderUnavailableMail $mail) => $mail->hasTo($this->salon->customer->email)
            && $mail->locale === 'de'
            && $mail->appointment->is($booking),
    );
    Notification::assertSentTo(
        $this->salon->customer,
        PhoneNotification::class,
        fn (PhoneNotification $notification) => $notification->title === 'notification.booking_cancelled_provider_unavailable_title'
            && $notification->data['appointment_id'] === $booking->id,
    );
});

it('renders the cancellation email in the customer language with the reason', function () {
    $booking = leaveBooking($this->leaver, '10:00', '11:00');

    $html = (new BookingCancelledProviderUnavailableMail($booking, 'Look Up', '€', 'de'))->render();

    expect($html)->toContain('Ihr/e Mitarbeiter/in ist zu dieser Zeit nicht verfügbar')
        ->toContain($booking->number)
        ->not->toContain('100,00')
        ->not->toContain('100.00');
});

it('refuses a confirmed plan that went stale, and changes nothing', function () {
    $booking = leaveBooking($this->leaver, '12:00', '13:00');
    $leave = fullDayLeave($this->leaver);
    $plan = $this->service->plan($leave);

    // The planned colleague gets booked before the user clicks "confirm".
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '12:00', '13:00');

    $e = grantRequiresPlan($leave, $plan['signature']);

    expect($e->changed)->toBeTrue()
        ->and($e->plan['rows'][0]['action'])->toBe(LeaveReassignmentService::ACTION_CANCEL)
        ->and($booking->fresh()->provider_id)->toBe($this->leaver->id)
        ->and(ProviderTimeOff::where('user_id', $this->leaver->id)->exists())->toBeFalse();
});

it('blocks the leave when a paid booking cannot be moved', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $this->salon->bookSlot($this->salon->hourlyLeave, SalonFixture::DATE, '10:00', '11:00');
    leaveBooking($this->leaver, '10:00', '11:00', overrides: ['payment_status' => PaymentStatus::PAID_ONSTIE_CASH]);
    $leave = fullDayLeave($this->leaver);
    $plan = $this->service->plan($leave);

    expect($plan['blocked'])->toBe(1);

    grantRequiresPlan($leave, $plan['signature']);

    expect(ProviderTimeOff::where('user_id', $this->leaver->id)->exists())->toBeFalse();
});

it('checks an edited leave against the bookings its new dates cover', function () {
    $leave = $this->service->grant(fullDayLeave($this->leaver))['time_off'];
    leaveBooking($this->leaver, '10:00', '11:00', overrides: [
        'appointment_date' => '2026-09-10',
        'start_time' => '2026-09-10 10:00:00',
        'end_time' => '2026-09-10 11:00:00',
    ]);

    $leave->fill(['end_date' => '2026-09-10']);

    $e = grantRequiresPlan($leave);

    expect($e->plan['rows'])->toHaveCount(1)
        ->and(Carbon::parse($leave->fresh()->end_date)->format('Y-m-d'))->toBe(SalonFixture::DATE);
});
