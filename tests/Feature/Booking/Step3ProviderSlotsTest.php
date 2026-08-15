<?php

/**
 * STEP 3 — the customer picked a provider; the slots shown must be real.
 *
 * This is the endpoint the app hits as
 *   /api/availability/provider?service_id=..&provider_id=..&date=..&branch_id=..
 * and every slot it returns has to survive a later booking attempt.
 */

use App\Enum\AppointmentStatus;
use Illuminate\Support\Carbon;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture();
});

afterEach(function () {
    Carbon::setTestNow();
});

function slots(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->getJson('/api/availability/provider?' . http_build_query($overrides + [
        'service_id' => test()->salon->service->id,
        'provider_id' => test()->salon->available->id,
        'date' => SalonFixture::DATE,
    ]));
}

function startTimes(Illuminate\Testing\TestResponse $response): array
{
    return collect($response->json('data.available_slots'))->pluck('start_time')->all();
}

// ── The happy shift ──────────────────────────────────────────────────────────

it('produces one slot per service duration across the shift', function () {
    $response = slots()->assertOk();

    expect($response->json('data.is_available'))->toBeTrue()
        ->and($response->json('data.reason_code'))->toBe('available')
        ->and($response->json('data.total_slots'))->toBe(8)
        ->and(startTimes($response))->toBe([
            '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00',
        ]);
});

it('never emits a slot that ends after the shift ends', function () {
    $last = collect(slots()->json('data.available_slots'))->last();

    expect($last['end_time'])->toBe('17:00');
});

it('returns each slot with both machine and display formats', function () {
    $slot = collect(slots()->json('data.available_slots'))->first();

    expect($slot)->toHaveKeys([
        'start_time', 'end_time', 'start_time_formatted', 'end_time_formatted',
        'display_time', 'duration_minutes',
    ])
        ->and($slot['duration_minutes'])->toBe(60)
        ->and($slot['start_time_formatted'])->toBe('09:00 AM');
});

// ── Nothing from the past ────────────────────────────────────────────────────

it('drops slots that already started today', function () {
    // Freeze mid-shift on the target day: 12:30.
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 12:30:00'));

    $starts = startTimes(slots());

    expect($starts)->toBe(['13:00', '14:00', '15:00', '16:00'])
        ->and($starts)->not->toContain('09:00');
});

it('reports a finished day as unavailable rather than erroring', function () {
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 18:00:00'));

    $response = slots()->assertOk();

    expect($response->json('data.is_available'))->toBeFalse()
        ->and($response->json('data.total_slots'))->toBe(0)
        ->and($response->json('data.reason_code'))->toBe('fully_booked');
});

it('rejects a past date outright', function () {
    slots(['date' => Carbon::parse(SalonFixture::NOW)->subDay()->format('Y-m-d')])
        ->assertStatus(422);
});

// ── book_buffer ──────────────────────────────────────────────────────────────

it('hides slots inside the minimum-advance window', function () {
    $this->salon->setSetting('book_buffer', '90');
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 09:00:00'));

    // Cutoff is 10:30, so the 10:00 slot must go but 11:00 stays.
    expect(startTimes(slots()))->toBe(['11:00', '12:00', '13:00', '14:00', '15:00', '16:00']);
});

it('applies the buffer across midnight into the next day', function () {
    // A 24h buffer from 08:00 on the 7th pushes the cutoff to 08:00 on the 8th,
    // which must not disturb a shift on the 9th.
    $this->salon->setSetting('book_buffer', (string) (60 * 24));

    expect(startTimes(slots()))->toHaveCount(8);
});

// ── Leave ────────────────────────────────────────────────────────────────────

it('returns no slots and a leave reason for a full-day leave', function () {
    $response = slots(['provider_id' => $this->salon->onLeave->id])->assertOk();

    expect($response->json('data.is_available'))->toBeFalse()
        ->and($response->json('data.reason_code'))->toBe('on_leave')
        ->and($response->json('data.available_slots'))->toBe([])
        ->and($response->json('data.leave_start_date'))->toBe(SalonFixture::DATE);
});

it('carves only the overlapping hours out of an hourly leave', function () {
    $response = slots(['provider_id' => $this->salon->hourlyLeave->id])->assertOk();

    expect($response->json('data.is_available'))->toBeTrue()
        ->and(startTimes($response))->toBe(['09:00', '10:00', '14:00', '15:00', '16:00']);
});

it('flags a non-working weekday', function () {
    $response = slots(['provider_id' => $this->salon->notWorking->id])->assertOk();

    expect($response->json('data.is_available'))->toBeFalse()
        ->and($response->json('data.reason_code'))->toBe('not_working_day');
});

// ── Existing bookings ────────────────────────────────────────────────────────

it('removes a slot taken by a confirmed appointment', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '11:00', '12:00');

    expect(startTimes(slots()))->not->toContain('11:00');
});

it('removes slots that merely overlap an appointment', function () {
    // 10:30-11:30 covers neither slot exactly but collides with both.
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:30', '11:30');

    $starts = startTimes(slots());

    expect($starts)->not->toContain('10:00')
        ->and($starts)->not->toContain('11:00')
        ->and($starts)->toContain('09:00')
        ->and($starts)->toContain('12:00');
});

it('keeps the slot free once an appointment is cancelled', function () {
    $this->salon->bookSlot(
        $this->salon->available,
        SalonFixture::DATE,
        '11:00',
        '12:00',
        status: AppointmentStatus::USER_CANCELLED,
    );

    expect(startTimes(slots()))->toContain('11:00');
});

/**
 * MISMATCH — availability and booking disagree about unpaid online bookings.
 *
 * BookingValidationService only treats `created_status = 1` rows as conflicts
 * (BookingValidationService.php:129), but ServiceAvailabilityService blocks on
 * ANY pending appointment. So an abandoned online booking hides the slot from
 * the customer while still allowing someone else to book it.
 *
 * Availability is the stricter side here, which is the safe direction — this
 * test pins the current behaviour so the discrepancy is visible.
 */
it('hides a slot held by an unconfirmed online booking', function () {
    $this->salon->bookSlot(
        $this->salon->available,
        SalonFixture::DATE,
        '11:00',
        '12:00',
        createdStatus: 0,
    );

    expect(startTimes(slots()))->not->toContain('11:00');
});

// ── Booking window ───────────────────────────────────────────────────────────

it('marks a date past max_booking_days as outside the booking window', function () {
    $beyond = Carbon::parse(SalonFixture::NOW)->addDays(11)->format('Y-m-d');

    // Give the provider a shift on that weekday so the only reason to refuse is
    // the window itself.
    App\Models\ProviderScheduledWork::create([
        'user_id' => $this->salon->available->id,
        'day_of_week' => Carbon::parse($beyond)->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'is_work_day' => true,
        'is_active' => true,
    ]);

    $response = slots(['date' => $beyond])->assertOk();

    expect($response->json('data.is_available'))->toBeFalse()
        ->and($response->json('data.reason_code'))->toBe('outside_booking_window');
});

// ── Validation & authorisation ───────────────────────────────────────────────

it('refuses a provider who does not offer the service', function () {
    slots(['provider_id' => $this->salon->doesNotOffer->id])->assertStatus(400);
});

it('rejects an unknown provider_id', function () {
    slots(['provider_id' => 999999])->assertStatus(422);
});

it('rejects a malformed date', function () {
    slots(['date' => '09-09-2026'])->assertStatus(422);
});

it('requires both service_id and provider_id', function () {
    test()->getJson('/api/availability/provider?date=' . SalonFixture::DATE)
        ->assertStatus(422);
});
