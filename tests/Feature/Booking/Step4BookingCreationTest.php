<?php

/**
 * STEP 4 — committing the booking, and every rule that can refuse it.
 *
 * The contract that matters: anything `/availability/provider` offered in step 3
 * must be accepted here. Tests at the bottom assert that end to end.
 */

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\ProviderScheduledWork;
use Illuminate\Support\Carbon;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture();
    $this->token = $this->salon->customerToken();
});

afterEach(function () {
    Carbon::setTestNow();
});

function book(array $overrides = []): Illuminate\Testing\TestResponse
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

it('creates a cash booking and confirms it immediately', function () {
    $response = book()->assertStatus(201);

    $appointment = Appointment::firstWhere('id', $response->json('data.id'));

    expect($appointment->created_status)->toBe(1)
        ->and($appointment->status)->toBe(AppointmentStatus::PENDING)
        ->and($appointment->payment_status)->toBe(PaymentStatus::PAID_ONSTIE_CASH)
        ->and($appointment->provider_id)->toBe($this->salon->available->id)
        ->and($appointment->customer_id)->toBe($this->salon->customer->id);
});

it('leaves an online booking unconfirmed pending payment', function () {
    $response = book(['payment_method' => 'online'])->assertStatus(201);

    $appointment = Appointment::find($response->json('data.id'));

    expect($appointment->created_status)->toBe(0)
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
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 14:00:00'));

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
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE . ' 09:30:00'));

    // 10:00 is only 30 minutes away, under the 120 minute buffer.
    book()->assertStatus(422);
});

// ── Provider rules ───────────────────────────────────────────────────────────

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
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->notWorking->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);
});

it('refuses a provider on full-day leave', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->onLeave->id,
        'start_time' => '10:00',
    ]]])->assertStatus(422);
});

/**
 * BUG — an open-ended full-day leave does not block booking.
 *
 * ServiceAvailabilityService::getFullDayTimeOff() treats a missing end_date as a
 * one-day leave:  COALESCE(end_date, start_date) >= :date
 * BookingValidationService:200 does not:  end_date >= :date
 *
 * In SQL, `NULL >= '2026-09-09'` is NULL, so the row never matches and the guard
 * is skipped entirely. Verified against the live MySQL database, not just the
 * SQLite test connection.
 *
 * Effect: the provider is correctly hidden from the customer's list, but a
 * crafted request — or the app replaying a slot it fetched a moment earlier —
 * books straight through the leave.
 */
it('lets a booking through an open-ended full-day leave', function () {
    $this->salon->giveFullDayLeave($this->salon->available, SalonFixture::DATE, null);

    // Availability agrees the provider is off…
    $availability = $this->getJson('/api/availability/provider?' . http_build_query([
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'date' => SalonFixture::DATE,
    ]))->json('data');

    expect($availability['is_available'])->toBeFalse()
        ->and($availability['reason_code'])->toBe('on_leave');

    // …but the booking is accepted anyway. Change to 422 once the validator
    // COALESCEs end_date like the availability service does.
    book()->assertStatus(201);
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

it('refuses a slot already taken', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');

    book()->assertStatus(422);
});

it('refuses a slot that merely overlaps an existing booking', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:30', '11:30');

    book()->assertStatus(422);
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
    book()->assertStatus(422);
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

    $intruder = App\Models\User::factory()->create();
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
    $slots = $this->getJson('/api/availability/provider?' . http_build_query([
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'date' => SalonFixture::DATE,
    ]))->json('data.available_slots');

    expect($slots)->not->toBeEmpty();

    foreach ($slots as $slot) {
        // Fresh customer per slot so the duplicate/daily-limit rules do not fire.
        $customer = App\Models\User::factory()->create([
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
                . ($response->json('message') ?? ''),
        );
    }
});

it('stops advertising a slot once it has been booked', function () {
    book(['services' => [[
        'service_id' => $this->salon->service->id,
        'provider_id' => $this->salon->available->id,
        'start_time' => '10:00',
    ]]])->assertStatus(201);

    $starts = collect($this->getJson('/api/availability/provider?' . http_build_query([
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

    $ids = collect($this->getJson('/api/availability/service?' . http_build_query([
        'service_id' => $this->salon->service->id,
        'date' => SalonFixture::DATE,
    ]))->json('data.providers'))->pluck('provider_id');

    expect($ids)->not->toContain($this->salon->available->id);
});
