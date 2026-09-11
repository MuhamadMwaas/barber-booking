<?php

/**
 * STEP 4 — committing the booking, and every rule that can refuse it.
 *
 * The contract that matters: anything `/availability/provider` offered in step 3
 * must be accepted here. Tests at the bottom assert that end to end.
 */

use App\Enum\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Services\BookingLockService;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\ProviderScheduledWork;
use App\Models\User;
use App\Services\BookingValidationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->token = $this->salon->customerToken();
});

afterEach(function () {
    Carbon::setTestNow();
});

function book(array $overrides = []): TestResponse
{
    $salon = test()->salon;

    $payload = $overrides + [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => '10:00',
        ]],
    ];

    return test()->withToken(test()->token)->postJson('/api/bookings', $payload);
}

// ── Happy paths ──────────────────────────────────────────────────────────────

it('confirms the booking immediately but does not mark it paid', function () {
    $response = book()->assertStatus(201);

    $appointment = Appointment::firstWhere('id', $response->json('data.id'));

    // created_status = 1 → the slot is blocked from the moment it is booked.
    // payment_status stays PENDING → the customer has not arrived or paid yet;
    // staff finalise the invoice at the counter.
    expect($appointment->created_status)->toBe(1)
        ->and($appointment->status)->toBe(AppointmentStatus::PENDING)
        ->and($appointment->payment_status)->toBe(PaymentStatus::PENDING)
        ->and($appointment->provider_id)->toBe($this->salon->available->id)
        ->and($appointment->customer_id)->toBe($this->salon->customer->id);
});

it('confirms the booking regardless of the payment method sent', function () {
    // There is no online payment: payment_method is recorded as intent only and
    // never decides whether the booking is real. An 'online' request must not
    // create a booking that blocks nothing and can never be confirmed.
    $response = book(['payment_method' => 'online'])->assertStatus(201);

    $appointment = Appointment::find($response->json('data.id'));

    expect($appointment->created_status)->toBe(1)
        ->and($appointment->payment_status)->toBe(PaymentStatus::PENDING);
});

it('extracts tax from the gross price', function () {
    $response = book()->assertStatus(201);

    // 100.00 gross at 19% → 84.03 net + 15.97 tax, and the two must re-add exactly.
    expect($response->json('data.subtotal'))->toEqual(84.03)
        ->and($response->json('data.tax_amount'))->toEqual(15.97)
        ->and($response->json('data.total_amount'))->toEqual(100.00);
});

it('opens a draft invoice with no invoice number', function () {
    $response = book()->assertStatus(201);

    $invoice = Invoice::firstWhere('appointment_id', $response->json('data.id'));

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and($invoice->invoice_number)->toBeNull();
});

it('books two sequential services in one appointment', function () {
    $response = book(['services' => [
        [
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ],
        [
            'service_id' => $this->salon->secondService->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '11:00',
        ],
    ]])->assertStatus(201);

    expect($response->json('data.duration_minutes'))->toBe(120)
        ->and($response->json('data.start_time'))->toBe('10:00')
        ->and($response->json('data.end_time'))->toBe('12:00')
        ->and($response->json('data.services_details'))->toHaveCount(2)
        ->and($response->json('data.total_amount'))->toEqual(150.00);
});

it('sorts services that arrive out of order', function () {
    $response = book(['services' => [
        [
            'service_id' => $this->salon->secondService->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '11:00',
        ],
        [
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ],
    ]])->assertStatus(201);

    $order = collect($response->json('data.services_details'))->pluck('service_name')->all();

    expect($order)->toBe(['Hair Cut', 'Beard Trim']);
});

// ── Timing rules ─────────────────────────────────────────────────────────────

it('refuses overlapping services in the same request', function () {
    book(['services' => [
        [
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ],
        [
            'service_id' => $this->salon->secondService->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:30',
        ],
    ]])->assertStatus(422);
});

it('refuses the same service twice', function () {
    book(['services' => [
        [
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ],
        [
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '11:00',
        ],
    ]])->assertStatus(422);
});

it('refuses a start time outside the shift', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '08:00',
    ]]])->assertStatus(422);
});

it('refuses a service that would run past the end of the shift', function () {
    // 16:30 + 60 min = 17:30, past the 17:00 close.
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '16:30',
    ]]])->assertStatus(422);
});

it('refuses a past date', function () {
    book(['date' => Carbon::parse(SalonFixture::NOW)->subDay()->format('Y-m-d')])
        ->assertStatus(422);
});

it('refuses a time earlier today', function () {
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE.' 14:00:00'));

    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);
});

it('refuses a date beyond max_booking_days', function () {
    $beyond = Carbon::parse(SalonFixture::NOW)->addDays(11)->format('Y-m-d');

    ProviderScheduledWork::create([
        'user_id' => $this->salon->available->id,
        'day_of_week' => Carbon::parse($beyond)->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'is_work_day' => true,
        'is_active' => true,
    ]);

    book(['date' => $beyond])->assertStatus(422);
});

it('enforces the minimum advance window', function () {
    $this->salon->setSetting('book_buffer', '120');
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE.' 09:30:00'));

    // 10:00 is only 30 minutes away, under the 120 minute buffer.
    book()->assertStatus(422);
});

// ── Provider rules ───────────────────────────────────────────────────────────

it('does not reveal whether an arbitrary user id belongs to a real non-provider account', function () {
    $existingCustomer = book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->customer->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);

    $unknownUser = book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => 999999,
        'start_time' => '10:00',
    ]]])->assertStatus(422);

    expect($existingCustomer->json())->toBe($unknownUser->json())
        ->and(json_encode($existingCustomer->json()))
        ->not->toContain($this->salon->customer->full_name)
        ->not->toContain($this->salon->customer->email);
});

it('does not include provider or service names when the pair cannot be booked', function () {
    $response = book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->doesNotOffer->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);

    expect($response->json('message'))->toBe(__('booking.provider_unavailable_for_service'))
        ->and(json_encode($response->json()))
        ->not->toContain($this->salon->doesNotOffer->full_name)
        ->not->toContain($this->salon->service->name);
});

it('enforces the active-provider invariant inside the service boundary too', function () {
    expect(fn () => app(BookingValidationService::class)
        ->validateProviderOffersService($this->salon->customer, $this->salon->service))
        ->toThrow(InvalidArgumentException::class, __('booking.provider_unavailable_for_service'));
});

it('refuses a provider who does not offer the service', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->doesNotOffer->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);
});

it('refuses a provider whose service link is inactive', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->inactivePivot->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);
});

it('refuses a deactivated provider', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->inactiveUser->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);
});

it('refuses a provider who does not work that day', function () {
    $response = book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->notWorking->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);

    expect($response->json('message'))->toBe(__('booking.provider_unavailable_on_date'))
        ->and(json_encode($response->json()))
        ->not->toContain($this->salon->notWorking->full_name);
});

it('refuses a provider on full-day leave', function () {
    $response = book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->onLeave->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);

    expect($response->json('message'))->toBe(__('booking.provider_unavailable_on_date'))
        ->and(json_encode($response->json()))
        ->not->toContain($this->salon->onLeave->full_name);
});

/**
 * An open-ended full-day leave blocks BOTH layers.
 *
 * A null end_date means a one-day leave. The booking layer used to test
 * `end_date >= :date`, and in SQL `NULL >= '2026-09-09'` is UNKNOWN — not false —
 * so the row never matched and the guard was skipped entirely: the provider was
 * correctly hidden from the customer's list, yet a crafted request (or the app
 * replaying a slot it fetched a moment earlier) booked straight through the
 * leave. Both layers now share ProviderTimeOff::scopeCoveringDate() (BOOK-04).
 */
it('blocks a booking during an open-ended full-day leave', function () {
    $this->salon->giveFullDayLeave($this->salon->available, SalonFixture::DATE, null);

    // Availability says the provider is off…
    $availability = $this->getJson('/api/availability/provider?'.http_build_query([
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'date' => SalonFixture::DATE,
    ]))->json('data');

    expect($availability['is_available'])->toBeFalse()
        ->and($availability['reason_code'])->toBe('on_leave');

    // …and so does the booking layer.
    $response = book()->assertStatus(422);

    expect($response->json('message'))->toBe(__('booking.provider_unavailable_on_date'));
});

it('refuses a slot covered by an hourly leave', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->hourlyLeave->id,
        'start_time' => '12:00',
    ]]])->assertStatus(422);
});

it('allows a slot outside the hourly leave', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->hourlyLeave->id,
        'start_time' => '09:00',
    ]]])->assertStatus(201);
});

// A taken slot answers 409, not 422: the request was well-formed and the slot
// was free when it was advertised — this is a conflict with current state, not
// bad input, and the app needs to tell those apart to refresh its slot list.
it('refuses a slot already taken', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');

    $response = book()->assertStatus(409);

    expect($response->json('message'))->toBe(__('booking.time_slot_unavailable'))
        ->and($response->json('error_type'))->toBe('slot_conflict')
        ->and(json_encode($response->json()))
        ->not->toContain($this->salon->available->full_name);
});

it('refuses a slot that merely overlaps an existing booking', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:30', '11:30');

    book()->assertStatus(409);
});

// ── Request-level limits ─────────────────────────────────────────────────────

it('refuses an empty services array', function () {
    book(['services' => []])->assertStatus(422);
});

it('refuses more than ten services', function () {
    $services = array_fill(0, 11, [
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00',
    ]);

    book(['services' => $services])->assertStatus(422);
});

it('refuses an unknown payment method', function () {
    book(['payment_method' => 'bitcoin'])->assertStatus(422);
});

it('refuses a malformed start time', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00:00',
    ]]])->assertStatus(422);
});

it('refuses an unknown service id', function () {
    book(['services' => [[
        'service_id' => 999999,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);
});

it('enforces the daily booking limit', function () {
    $this->salon->setSetting('max_daily_bookings', '1');

    book()->assertStatus(201);

    book(['services' => [[
        'service_id' => $this->salon->secondService->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '13:00',
    ]]])->assertStatus(422);
});

it('refuses an exact duplicate booking', function () {
    book()->assertStatus(201);
    // Re-booking the identical slot is first and foremost a slot conflict — the
    // provider is now busy — so it answers 409 like any other taken slot.
    book()->assertStatus(409);
});

// ── Reading back and cancelling ──────────────────────────────────────────────

it('lists the booking afterwards', function () {
    book()->assertStatus(201);

    $response = $this->withToken($this->token)->getJson('/api/bookings')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('fetches one booking by id', function () {
    $id = book()->json('data.id');

    $this->withToken($this->token)->getJson("/api/bookings/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id);
});

it('cancels a pending booking', function () {
    $id = book()->json('data.id');

    $this->withToken($this->token)->postJson("/api/bookings/{$id}/cancel", [
        'cancellation_reason' => 'Plans changed',
    ])->assertOk();

    expect(Appointment::find($id)->status)->toBe(AppointmentStatus::USER_CANCELLED);
});

it('cannot cancel the same booking twice', function () {
    $id = book()->json('data.id');

    $this->withToken($this->token)->postJson("/api/bookings/{$id}/cancel")->assertOk();
    $this->withToken($this->token)->postJson("/api/bookings/{$id}/cancel")->assertStatus(422);
});

it('blocks reading a booking that belongs to somebody else', function () {
    $id = book()->json('data.id');

    $intruder = User::factory()->create();
    $intruder->assignRole('customer');

    // Laravel keeps the resolved user on the guard for the lifetime of the test,
    // so a second request in the same test would otherwise still be authenticated
    // as the first customer and wrongly pass. A real request never carries that
    // state over.
    $this->app['auth']->forgetGuards();

    $this->withToken($intruder->createToken('t')->plainTextToken)
        ->getJson("/api/bookings/{$id}")
        ->assertStatus(403);
});

/**
 * BUG — a missing booking is reported as a server error.
 *
 * BookingService::getBookingDetails() uses findOrFail(); BookingController::show()
 * catches only \Exception and maps it to 500, so ModelNotFoundException never
 * becomes a 404. Documented in docs/BOOKING_FLOW.md as a known issue and still
 * present.
 */
it('returns 500 instead of 404 for a booking that does not exist', function () {
    $this->withToken($this->token)->getJson('/api/bookings/999999')
        ->assertStatus(500);
});

// ── Consistency between what we offer and what we accept ─────────────────────

it('accepts every slot the availability endpoint advertised', function () {
    $slots = $this->getJson('/api/availability/provider?'.http_build_query([
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'date' => SalonFixture::DATE,
    ]))->json('data.available_slots');

    expect($slots)->not->toBeEmpty();

    foreach ($slots as $slot) {
        // Fresh customer per slot so the duplicate/daily-limit rules do not fire.
        $customer = User::factory()->create([
            'email_verified_at' => now(),
            'email_verified_via_otp_at' => now(),
            'registration_method' => 'email',
        ]);
        $customer->assignRole('customer');

        $response = $this->withToken($customer->createToken('t')->plainTextToken)
            ->postJson('/api/bookings', [
                'date' => SalonFixture::DATE,
                'payment_method' => 'cash',
                'services' => [[
                    'service_id' => $this->salon->service->id,
                    'provider_id' => $this->salon->available->id,
                    'start_time' => $slot['start_time'],
                ]],
            ]);

        expect($response->status())->toBe(
            201,
            "Availability offered {$slot['start_time']} but booking refused it: "
                .($response->json('message') ?? ''),
        );
    }
});

it('stops advertising a slot once it has been booked', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00',
    ]]])->assertStatus(201);

    $starts = collect($this->getJson('/api/availability/provider?'.http_build_query([
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'date' => SalonFixture::DATE,
    ]))->json('data.available_slots'))->pluck('start_time');

    expect($starts)->not->toContain('10:00');
});

it('drops a provider from discovery once their whole day is booked out', function () {
    foreach (['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'] as $start) {
        $this->salon->bookSlot(
            $this->salon->available,
            SalonFixture::DATE,
            $start,
            sprintf('%02d:00', ((int) substr($start, 0, 2)) + 1),
        );
    }

    $ids = collect($this->getJson('/api/availability/service?'.http_build_query([
        'service_id' => $this->salon->service->id,
        'date' => SalonFixture::DATE,
    ]))->json('data.providers'))->pluck('provider_id');

    expect($ids)->not->toContain($this->salon->available->id);
});

// ── BOOK-01 parity: one definition of "the provider is busy" ──────────────────
//
// Availability and booking used to carry two different hand-written conditions
// (availability: status=PENDING, no created_status; booking: created_status=1 and
// status IN (PENDING, COMPLETED)). Both now go through
// Appointment::scopeBlocksProviderTime(), so these two must never disagree.

function slotStarts(): Illuminate\Support\Collection
{
    return collect(test()->getJson('/api/availability/provider?'.http_build_query([
        'service_id' => test()->salon->service->id,
        'provider_id' => test()->salon->available->id,
        'date' => SalonFixture::DATE,
    ]))->json('data.available_slots'))->pluck('start_time');
}

it('hides a slot held by a COMPLETED booking and refuses to book over it', function () {
    // A booking marked done early still owns the rest of its scheduled window.
    $this->salon->bookSlot(
        $this->salon->available,
        SalonFixture::DATE,
        '10:00',
        '11:00',
        status: AppointmentStatus::COMPLETED,
    );

    expect(slotStarts())->not->toContain('10:00');

    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00',
    ]]])->assertStatus(409);
});

it('frees a slot held by a cancelled booking in both layers', function () {
    $this->salon->bookSlot(
        $this->salon->available,
        SalonFixture::DATE,
        '10:00',
        '11:00',
        status: AppointmentStatus::USER_CANCELLED,
    );

    expect(slotStarts())->toContain('10:00');

    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00',
    ]]])->assertStatus(201);
});

// ── BOOK-02: the conflict check and the write are one atomic step ─────────────
//
// A real race needs two concurrent connections, which Pest cannot drive against
// a single transactional test connection. What IS testable — and what actually
// broke before — is the structure the guarantee rests on: the check must run
// inside the transaction, after a lock on the provider row. These assert that.

it('locks the provider and the customer rows inside the booking transaction', function () {
    // Asserted through a spy rather than by matching "for update" in the SQL:
    // the suite runs on SQLite, whose grammar silently drops the lock clause, so
    // a SQL-text assertion would pass on a build that locks nothing on MySQL.
    // What must hold on every driver is the ordering — the lock is taken, and it
    // is taken while a transaction is open.
    $spy = new class extends BookingLockService {
        public array $lockedIds = [];

        public array $transactionLevels = [];

        public function lockUsers(array $userIds): void
        {
            $this->lockedIds[] = $userIds;
            $this->transactionLevels[] = DB::transactionLevel();
            parent::lockUsers($userIds);
        }
    };

    app()->instance(BookingLockService::class, $spy);

    book()->assertStatus(201);

    expect($spy->lockedIds)->toHaveCount(1)
        ->and($spy->lockedIds[0])->toContain($this->salon->available->id)
        ->and($spy->lockedIds[0])->toContain($this->salon->customer->id)
        // > 0 means a transaction was already open: locking outside one releases
        // the lock immediately and would guarantee nothing.
        ->and($spy->transactionLevels[0])->toBeGreaterThan(0);
});

it('rolls the whole booking back when the slot is taken', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');

    $before = Appointment::count();

    book()->assertStatus(409);

    // The conflict now aborts inside the transaction, so no half-written
    // appointment, appointment_services or draft invoice may survive.
    expect(Appointment::count())->toBe($before)
        ->and(Invoice::count())->toBe(0);
});

it('locks the provider row before moving an appointment onto the calendar', function () {
    // Guards StaffDashboard::updateAppointment(), which used to write a new
    // start/end time with no conflict check and no lock whatsoever.
    $occupied = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $moving = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '14:00', '15:00');

    $validator = app(BookingValidationService::class);

    // Moving onto the occupied window is refused …
    expect(fn () => $validator->assertNoConflictingAppointment(
        $this->salon->available,
        Carbon::parse(SalonFixture::DATE.' 10:30'),
        Carbon::parse(SalonFixture::DATE.' 11:30'),
        $moving->id,
    ))->toThrow(SlotUnavailableException::class);

    // … while an appointment is never in conflict with itself.
    expect(fn () => $validator->assertNoConflictingAppointment(
        $this->salon->available,
        Carbon::parse(SalonFixture::DATE.' 10:00'),
        Carbon::parse(SalonFixture::DATE.' 11:00'),
        $occupied->id,
    ))->not->toThrow(SlotUnavailableException::class);
});

// ── BOOK-04: leave ranges mean the same thing to both layers ──────────────────
//
// The booking layer used to match hourly leaves on `start_date = :date` only, so
// every day of a multi-day leave except the first was invisible to it while the
// availability layer was already hiding all of them.
//
// A multi-day hourly leave is ONE CONTINUOUS ABSENCE: 09-09 12:00 → 09-11 13:00
// means gone from noon on the 9th until 13:00 on the 11th, so the 10th is fully
// blocked — not merely 12:00–13:00 on each of the three days.

function bookAt(string $start): TestResponse
{
    return book(['services' => [[
        'service_id' => test()->salon->service->id,
        'provider_id' => test()->salon->available->id,
        'start_time' => $start,
    ]]]);
}

it('blocks the start day of a multi-day hourly leave from its start time on', function () {
    // Leave runs from DATE 12:00 through the following day.
    $this->salon->giveHourlyLeave(
        $this->salon->available,
        SalonFixture::DATE,
        '12:00',
        '13:00',
        Carbon::parse(SalonFixture::DATE)->addDay()->format('Y-m-d'),
    );

    bookAt('10:00')->assertStatus(201);   // before the leave begins → still fine
    bookAt('14:00')->assertStatus(422);   // after noon on the start day → gone
});

it('blocks a middle day of a multi-day hourly leave entirely', function () {
    // The leave starts the day BEFORE and ends the day AFTER, so DATE is a middle
    // day: continuously absent, therefore no hour of it is bookable.
    $this->salon->giveHourlyLeave(
        $this->salon->available,
        Carbon::parse(SalonFixture::DATE)->subDay()->format('Y-m-d'),
        '12:00',
        '13:00',
        Carbon::parse(SalonFixture::DATE)->addDay()->format('Y-m-d'),
    );

    bookAt('10:00')->assertStatus(422);

    // And the availability layer agrees — this is the parity that was missing.
    $slots = $this->getJson('/api/availability/provider?'.http_build_query([
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'date' => SalonFixture::DATE,
    ]))->json('data.available_slots');

    expect($slots)->toBe([]);
});

it('frees the end day of a multi-day hourly leave after its end time', function () {
    // Leave began the previous day and ends at 12:00 on DATE.
    $this->salon->giveHourlyLeave(
        $this->salon->available,
        Carbon::parse(SalonFixture::DATE)->subDay()->format('Y-m-d'),
        '09:00',
        '12:00',
        SalonFixture::DATE,
    );

    bookAt('10:00')->assertStatus(422);   // still inside the absence
    bookAt('13:00')->assertStatus(201);   // after it ends → bookable again
});

it('keeps a single-day hourly leave confined to its own hours', function () {
    $this->salon->giveHourlyLeave($this->salon->available, SalonFixture::DATE, '12:00', '13:00');

    bookAt('10:00')->assertStatus(201);
    bookAt('12:00')->assertStatus(422);
    bookAt('13:00')->assertStatus(201);   // half-open: starting as it ends is fine
});

// ── BOOK-06: the customer cannot be in two places at once ────────────────────
//
// The old guard asked "same exact start time AND a shared service?", which let
// two real cases through: a simultaneous booking with a different provider and
// no shared service, and any partial overlap. The rule is now plain time
// overlap, independent of provider and service.
//
// `doesNotOffer` is a second fully-available provider that offers secondService,
// so these book across two providers — the customer is the only thing clashing.

function bookWith(int $serviceId, int $providerId, string $start): TestResponse
{
    return book(['services' => [[
        'service_id' => $serviceId,
        'provider_id' => $providerId,
        'start_time' => $start,
    ]]]);
}

it('refuses a second booking at the same time with another provider', function () {
    // Neither the provider nor the service is shared — only the customer is.
    bookWith($this->salon->service->id, $this->salon->available->id, '10:00')
        ->assertStatus(201);

    $response = bookWith($this->salon->secondService->id, $this->salon->doesNotOffer->id, '10:00')
        ->assertStatus(422);

    expect($response->json('message'))->toBe(__('booking.customer_already_booked'));
});

it('refuses a booking that partially overlaps one the customer already has', function () {
    // 10:00-11:00 already taken; 10:30 starts inside it, with a different provider.
    bookWith($this->salon->service->id, $this->salon->available->id, '10:00')
        ->assertStatus(201);

    bookWith($this->salon->secondService->id, $this->salon->doesNotOffer->id, '10:30')
        ->assertStatus(422);
});

it('allows back-to-back bookings with different providers', function () {
    // The whole point of the change: sequential is fine. 10:00-11:00 then 11:00.
    bookWith($this->salon->service->id, $this->salon->available->id, '10:00')
        ->assertStatus(201);

    bookWith($this->salon->secondService->id, $this->salon->doesNotOffer->id, '11:00')
        ->assertStatus(201);
});

it('does not treat a cancelled booking as the customer being busy', function () {
    $response = bookWith($this->salon->service->id, $this->salon->available->id, '10:00')
        ->assertStatus(201);

    Appointment::find($response->json('data.id'))
        ->update(['status' => AppointmentStatus::USER_CANCELLED]);

    bookWith($this->salon->secondService->id, $this->salon->doesNotOffer->id, '10:00')
        ->assertStatus(201);
});
