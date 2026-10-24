<?php

/**
 * The month views of availability:
 *
 *   /api/availability/days?service_id=..&provider_id=..&month=YYYY-MM
 *       → which days one provider still has a free slot on
 *   /api/availability/days/providers?service_id=..&month=YYYY-MM&branch_id=..
 *       → which days anybody does, and who
 *
 * Calendar under the fixture (now = Mon 2026-09-07 08:00, max_booking_days = 10):
 *
 *   1 … 6     past
 *   7 … 17    evaluated — every fixture provider works Wednesdays only, so the
 *             9th (SalonFixture::DATE, where the fixture's leaves and bookings
 *             sit) and the 16th (a clean Wednesday) are the only candidates
 *   18 … 30   outside_booking_window
 *
 * The contract that matters most is the same one as the per-day endpoints: a day
 * reported as available must be bookable. The parity tests at the bottom book the
 * advertised first slot through the real booking call.
 */

use App\Models\SalonSchedule;
use App\Models\User;
use App\Services\BookingValidationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\SalonFixture;

const CLEAN_WEDNESDAY = '2026-09-16';

beforeEach(function () {
    $this->salon = new SalonFixture();
});

afterEach(function () {
    Carbon::setTestNow();
});

function providerDays(array $overrides = []): TestResponse
{
    return test()->getJson('/api/availability/days?' . http_build_query($overrides + [
        'service_id' => test()->salon->service->id,
        'provider_id' => test()->salon->available->id,
        'month' => '2026-09',
    ]));
}

function serviceDays(array $overrides = []): TestResponse
{
    return test()->getJson('/api/availability/days/providers?' . http_build_query($overrides + [
        'service_id' => test()->salon->service->id,
        'month' => '2026-09',
    ]));
}

function day(TestResponse $response, string $date): array
{
    return collect($response->json('data.days'))->firstWhere('date', $date);
}

function providerIdsOn(TestResponse $response, string $date): array
{
    return collect(day($response, $date)['providers'])->pluck('provider_id')->sort()->values()->all();
}

function closeSalonOnWednesdays(?int $branchId, bool $isOpen = false): void
{
    SalonSchedule::create([
        'branch_id' => $branchId,
        'day_of_week' => Carbon::WEDNESDAY,
        'open_time' => '09:00',
        'close_time' => '18:00',
        'is_open' => $isOpen,
    ]);
}

// ── API 1: one provider ──────────────────────────────────────────────────────

it('lists the days on which the provider still has a free slot', function () {
    $response = providerDays()->assertOk();

    expect($response->json('data.available_dates'))->toBe([SalonFixture::DATE, CLEAN_WEDNESDAY])
        ->and($response->json('data.days'))->toHaveCount(30)
        ->and($response->json('data.provider.id'))->toBe($this->salon->available->id)
        ->and($response->json('data.pricing.effective_price'))->toEqual(100.00);
});

it('returns every day of the month with the reason it is or is not bookable', function () {
    $response = providerDays();

    expect(day($response, '2026-09-01')['reason_code'])->toBe('past')
        ->and(day($response, '2026-09-06')['reason_code'])->toBe('past')
        ->and(day($response, '2026-09-08')['reason_code'])->toBe('not_working_day')
        ->and(day($response, '2026-09-17')['reason_code'])->toBe('not_working_day')
        ->and(day($response, '2026-09-23')['reason_code'])->toBe('outside_booking_window')
        ->and(day($response, '2026-09-30')['reason_code'])->toBe('outside_booking_window');
});

it('summarises an open day with its slot count and first slot', function () {
    $day = day(providerDays(), SalonFixture::DATE);

    expect($day)->toMatchArray([
        'day_name' => 'Wed',
        'day_number' => 9,
        'is_today' => false,
        'is_available' => true,
        'reason_code' => 'available',
        'available_slots_count' => 8,
        'first_available_slot' => '09:00',
    ]);
});

it('defaults to the current month', function () {
    $response = test()->getJson('/api/availability/days?' . http_build_query([
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
    ]))->assertOk();

    expect($response->json('data.month'))->toBe('2026-09')
        ->and($response->json('data.month_name'))->toBe('September 2026');
});

it('marks a full-day leave as on_leave and leaves the other days alone', function () {
    $response = providerDays(['provider_id' => $this->salon->onLeave->id]);

    expect(day($response, SalonFixture::DATE)['reason_code'])->toBe('on_leave')
        ->and($response->json('data.available_dates'))->toBe([CLEAN_WEDNESDAY]);
});

it('blocks a day covered by an open-ended full-day leave', function () {
    // end_date = NULL means the start day only (COALESCE rule, BOOK-04).
    $this->salon->giveFullDayLeave($this->salon->available, CLEAN_WEDNESDAY, null);

    $response = providerDays();

    expect(day($response, CLEAN_WEDNESDAY)['reason_code'])->toBe('on_leave')
        ->and($response->json('data.available_dates'))->toBe([SalonFixture::DATE]);
});

it('blocks the middle day of a multi-day hourly leave entirely', function () {
    $this->salon->giveHourlyLeave($this->salon->available, '2026-09-15', '12:00:00', '13:00:00', '2026-09-17');

    expect(day(providerDays(), CLEAN_WEDNESDAY)['reason_code'])->toBe('fully_booked');
});

it('counts only the slots an hourly leave leaves free', function () {
    $day = day(providerDays(['provider_id' => $this->salon->hourlyLeave->id]), SalonFixture::DATE);

    expect($day['available_slots_count'])->toBe(5)
        ->and($day['first_available_slot'])->toBe('09:00');
});

it('marks a day whose every slot is taken as fully_booked', function () {
    $response = providerDays(['provider_id' => $this->salon->fullyBooked->id]);

    expect(day($response, SalonFixture::DATE)['reason_code'])->toBe('fully_booked')
        ->and($response->json('data.available_dates'))->toBe([CLEAN_WEDNESDAY]);
});

it('starts the first slot after the minimum-advance buffer', function () {
    $this->salon->setSetting('book_buffer', '90');
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 09:00:00'));

    expect(day(providerDays(), SalonFixture::DATE)['first_available_slot'])->toBe('11:00');
});

it('treats today as open while slots remain and as fully booked once they are gone', function () {
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 18:00:00'));

    $day = day(providerDays(), SalonFixture::DATE);

    expect($day['is_today'])->toBeTrue()
        ->and($day['reason_code'])->toBe('fully_booked')
        ->and(day(providerDays(), '2026-09-08')['reason_code'])->toBe('past');
});

// ── Salon closed days (salon_schedules.is_open) ─────────────────────────────

it('marks a weekday the provider\'s branch is closed as salon_closed', function () {
    closeSalonOnWednesdays($this->salon->branch->id);

    $response = providerDays();

    expect($response->json('data.available_dates'))->toBe([])
        ->and(day($response, SalonFixture::DATE)['reason_code'])->toBe('salon_closed')
        ->and(day($response, CLEAN_WEDNESDAY)['reason_code'])->toBe('salon_closed');
});

it('applies a salon-wide closed row to every branch', function () {
    closeSalonOnWednesdays(null);

    expect(providerDays()->json('data.available_dates'))->toBe([])
        ->and(providerDays(['provider_id' => $this->salon->otherBranchProvider->id])->json('data.available_dates'))->toBe([]);
});

it('lets a branch row override the salon-wide default', function () {
    closeSalonOnWednesdays(null);
    closeSalonOnWednesdays($this->salon->branch->id, isOpen: true);

    expect(providerDays()->json('data.available_dates'))->toBe([SalonFixture::DATE, CLEAN_WEDNESDAY])
        ->and(providerDays(['provider_id' => $this->salon->otherBranchProvider->id])->json('data.available_dates'))->toBe([]);
});

it('ignores another branch\'s closed day', function () {
    closeSalonOnWednesdays($this->salon->otherBranch->id);

    expect(providerDays()->json('data.available_dates'))->toBe([SalonFixture::DATE, CLEAN_WEDNESDAY]);
});

// ── Month navigation and the booking window ─────────────────────────────────

it('offers no neighbouring month when the whole window fits in this one', function () {
    $response = providerDays();

    expect($response->json('data.previous_month'))->toBeNull()
        ->and($response->json('data.next_month'))->toBeNull()
        ->and($response->json('data.last_bookable_date'))->toBe('2026-09-17');
});

it('walks forward month by month up to the end of the booking window', function () {
    $this->salon->setSetting('max_booking_days', '60'); // last bookable: 2026-11-06

    $september = providerDays();
    $october = providerDays(['month' => '2026-10'])->assertOk();
    $november = providerDays(['month' => '2026-11'])->assertOk();

    expect($september->json('data.next_month'))->toBe('2026-10')
        ->and($october->json('data.previous_month'))->toBe('2026-09')
        ->and($october->json('data.next_month'))->toBe('2026-11')
        ->and($october->json('data.available_dates'))->toBe(['2026-10-07', '2026-10-14', '2026-10-21', '2026-10-28'])
        ->and($november->json('data.next_month'))->toBeNull()
        ->and($november->json('data.available_dates'))->toBe(['2026-11-04'])
        ->and(day($november, '2026-11-11')['reason_code'])->toBe('outside_booking_window');
});

it('refuses a month past the booking window', function () {
    providerDays(['month' => '2026-10'])->assertStatus(422)
        ->assertJson(['success' => false, 'message' => __('booking.month_outside_booking_window')]);
});

it('refuses a month that is already over', function () {
    providerDays(['month' => '2026-08'])->assertStatus(422);
});

it('rejects a malformed month', function () {
    providerDays(['month' => '2026-9-1'])->assertStatus(422);
    providerDays(['month' => '09-2026'])->assertStatus(422);
});

// ── Who may be asked about ──────────────────────────────────────────────────

it('answers every unbookable provider with the same message', function () {
    $customer = $this->salon->customer;

    $messages = collect([
        $this->salon->doesNotOffer->id,
        $this->salon->inactiveUser->id,
        $this->salon->inactivePivot->id,
        $customer->id,
        999999,
    ])->map(fn (int $id) => providerDays(['provider_id' => $id])->assertStatus(422)->json('message'));

    expect($messages->unique()->all())->toBe([__('booking.provider_unavailable_for_service')]);
});

it('refuses an inactive service', function () {
    providerDays(['service_id' => $this->salon->inactiveService->id])->assertStatus(422);
    serviceDays(['service_id' => $this->salon->inactiveService->id])->assertStatus(422);
});

it('refuses a soft-deleted service with a 422, not a 500', function () {
    $this->salon->service->delete();

    providerDays()->assertStatus(422);
    serviceDays()->assertStatus(422);
});

it('requires service_id and provider_id', function () {
    test()->getJson('/api/availability/days?month=2026-09')->assertStatus(422)
        ->assertJsonValidationErrors(['service_id', 'provider_id']);
});

// ── API 2: every provider of a service ──────────────────────────────────────

it('lists who is free on each open day', function () {
    $response = serviceDays()->assertOk();

    expect($response->json('data.available_dates'))->toBe([SalonFixture::DATE, CLEAN_WEDNESDAY])
        ->and(providerIdsOn($response, SalonFixture::DATE))->toBe(collect([
            $this->salon->available->id,
            $this->salon->hourlyLeave->id,
            $this->salon->otherBranchProvider->id,
        ])->sort()->values()->all())
        ->and(providerIdsOn($response, CLEAN_WEDNESDAY))->toBe(collect([
            $this->salon->available->id,
            $this->salon->onLeave->id,
            $this->salon->fullyBooked->id,
            $this->salon->hourlyLeave->id,
            $this->salon->otherBranchProvider->id,
        ])->sort()->values()->all());
});

it('summarises each provider without listing their slots', function () {
    $entry = collect(day(serviceDays(), SalonFixture::DATE)['providers'])
        ->firstWhere('provider_id', $this->salon->hourlyLeave->id);

    expect($entry)->toHaveKeys(['provider_id', 'provider_name', 'provider_avatar', 'service_pricing', 'available_slots_count', 'first_available_slot'])
        ->and($entry)->not->toHaveKey('available_slots')
        ->and($entry['available_slots_count'])->toBe(5)
        ->and($entry['service_pricing']['effective_price'])->toEqual(100.00);
});

it('reports the provider count of every day', function () {
    $response = serviceDays();

    expect(day($response, SalonFixture::DATE)['providers_count'])->toBe(3)
        ->and(day($response, '2026-09-08'))->toMatchArray([
            'is_available' => false,
            'reason_code' => 'no_availability',
            'providers_count' => 0,
            'providers' => [],
        ]);
});

it('narrows to one branch', function () {
    $response = serviceDays(['branch_id' => $this->salon->branch->id])->assertOk();

    expect(providerIdsOn($response, SalonFixture::DATE))
        ->not->toContain($this->salon->otherBranchProvider->id)
        ->and($response->json('data.branch_id'))->toBe($this->salon->branch->id);
});

it('never lists a user without the provider role', function () {
    // Every other condition is met — linked, scheduled, active — but the booking
    // call requires the role, so offering this person would be a lie.
    $impostor = User::factory()->create(['branch_id' => $this->salon->branch->id, 'is_active' => true]);
    $impostor->assignRole('customer');
    $this->salon->offerService($impostor, $this->salon->service);
    DB::table('provider_scheduled_works')->insert([
        'user_id' => $impostor->id,
        'day_of_week' => Carbon::WEDNESDAY,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'is_work_day' => true,
        'is_active' => true,
    ]);

    expect(providerIdsOn(serviceDays(), CLEAN_WEDNESDAY))->not->toContain($impostor->id);
});

it('names a day salon_closed only when the salon is closed for everyone', function () {
    closeSalonOnWednesdays($this->salon->branch->id);

    $all = serviceDays();
    $mainBranch = serviceDays(['branch_id' => $this->salon->branch->id]);

    // The other branch is still open, so the day is available — through it.
    expect(providerIdsOn($all, SalonFixture::DATE))->toBe([$this->salon->otherBranchProvider->id])
        ->and(day($mainBranch, SalonFixture::DATE)['reason_code'])->toBe('salon_closed');
});

it('loads a month in a fixed number of queries however many providers there are', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        serviceDays()->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $before = $count();

    foreach (range(1, 5) as $i) {
        $extra = User::factory()->create(['branch_id' => $this->salon->branch->id, 'is_active' => true]);
        $extra->assignRole('provider');
        $this->salon->offerService($extra, $this->salon->service);
    }

    expect($count())->toBe($before)
        ->and($before)->toBeLessThan(20);
});

// ── Parity with the booking call ────────────────────────────────────────────

it('accepts the first slot the provider month view advertised', function () {
    $token = $this->salon->customerToken();

    foreach (providerDays()->json('data.days') as $day) {
        if (! $day['is_available']) {
            continue;
        }

        test()->withToken($token)->postJson('/api/bookings', [
            'date' => $day['date'],
            'payment_method' => 'cash',
            'services' => [[
                'service_id' => $this->salon->service->id,
                'provider_id' => $this->salon->available->id,
                'start_time' => $day['first_available_slot'],
            ]],
        ])->assertStatus(201);
    }
});

it('accepts the first slot of every provider the service month view advertised', function () {
    $token = $this->salon->customerToken();

    // One booking per open day: every provider here starts at 09:00, and one
    // customer cannot hold two of those at once (assertCustomerIsFree).
    $response = serviceDays(['branch_id' => $this->salon->branch->id]);

    foreach ($response->json('data.available_dates') as $date) {
        $entry = day($response, $date)['providers'][0];

        test()->withToken($token)->postJson('/api/bookings', [
            'date' => $date,
            'payment_method' => 'cash',
            'services' => [[
                'service_id' => $this->salon->service->id,
                'provider_id' => $entry['provider_id'],
                'start_time' => $entry['first_available_slot'],
            ]],
        ])->assertStatus(201);
    }
});

it('refuses a booking on a day the salon is closed', function () {
    closeSalonOnWednesdays($this->salon->branch->id);

    test()->withToken($this->salon->customerToken())->postJson('/api/bookings', [
        'date' => CLEAN_WEDNESDAY,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ]],
    ])->assertStatus(422)->assertJsonFragment(['message' => __('booking.salon_closed')]);
});

it('lets force booking open a closed salon day, like any other availability window', function () {
    closeSalonOnWednesdays($this->salon->branch->id);

    $validator = app(BookingValidationService::class);
    $start = Carbon::parse(CLEAN_WEDNESDAY . ' 10:00');

    expect(fn () => $validator->validateTimeSlotAvailability(
        $this->salon->available, $this->salon->service, $start, $start->copy()->addHour(),
    ))->toThrow(InvalidArgumentException::class, __('booking.salon_closed'))
        ->and(fn () => $validator->validateTimeSlotAvailability(
            $this->salon->available, $this->salon->service, $start, $start->copy()->addHour(), bypassAvailability: true,
        ))->not->toThrow(InvalidArgumentException::class);
});
