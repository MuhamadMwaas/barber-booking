<?php

/**
 * STEP 2 — the customer picked a service; now show who can actually do it.
 *
 * `GET /api/availability/service` must return ONLY bookable providers. The
 * fixture puts one provider in each excluded state, so a broken filter shows up
 * as a specific name leaking into the list.
 */

use Illuminate\Support\Carbon;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture();
});

afterEach(function () {
    Carbon::setTestNow();
});

function providerIds(array $json): array
{
    return collect($json['data']['providers'])->pluck('provider_id')->sort()->values()->all();
}

function discover(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->getJson('/api/availability/service?' . http_build_query($overrides + [
        'service_id' => test()->salon->service->id,
        'date' => SalonFixture::DATE,
    ]));
}

it('returns exactly the bookable providers and nobody else', function () {
    $response = discover()->assertOk();

    // Three providers are genuinely bookable on this day:
    //   available   — clean shift
    //   hourlyLeave — shift minus an 11:00-14:00 carve-out, still has slots
    //   otherBranch — clean shift, excluded only when branch_id is passed
    $expected = collect([
        $this->salon->available->id,
        $this->salon->hourlyLeave->id,
        $this->salon->otherBranchProvider->id,
    ])->sort()->values()->all();

    expect(providerIds($response->json()))->toBe($expected);
});

it('excludes a provider on full-day leave', function () {
    expect(providerIds(discover()->json()))
        ->not->toContain($this->salon->onLeave->id);
});

it('excludes a provider who does not work that weekday', function () {
    expect(providerIds(discover()->json()))
        ->not->toContain($this->salon->notWorking->id);
});

it('excludes a provider whose day is fully booked', function () {
    expect(providerIds(discover()->json()))
        ->not->toContain($this->salon->fullyBooked->id);
});

it('excludes a deactivated provider account', function () {
    expect(providerIds(discover()->json()))
        ->not->toContain($this->salon->inactiveUser->id);
});

it('excludes a provider whose service link is switched off', function () {
    expect(providerIds(discover()->json()))
        ->not->toContain($this->salon->inactivePivot->id);
});

it('excludes a provider who does not offer the service', function () {
    expect(providerIds(discover()->json()))
        ->not->toContain($this->salon->doesNotOffer->id);
});

it('excludes other branches when branch_id is given', function () {
    $ids = providerIds(discover(['branch_id' => $this->salon->branch->id])->json());

    expect($ids)->not->toContain($this->salon->otherBranchProvider->id)
        ->and($ids)->toContain($this->salon->available->id);
});

it('includes providers from every branch when branch_id is omitted', function () {
    // The other-branch provider is fully available, so with no branch filter it
    // must be offered alongside the main one.
    expect(providerIds(discover()->json()))
        ->toContain($this->salon->otherBranchProvider->id);
});

it('keeps a provider whose hourly leave still leaves free slots', function () {
    $providers = collect(discover()->json('data.providers'));
    $hourly = $providers->firstWhere('provider_id', $this->salon->hourlyLeave->id);

    expect($hourly)->not->toBeNull();

    $starts = collect($hourly['available_slots'])->pluck('start_time')->all();

    // 11:00, 12:00 and 13:00 are inside the 11:00-14:00 leave.
    expect($starts)->toBe(['09:00', '10:00', '14:00', '15:00', '16:00']);
});

it('returns each provider with slots, pricing and branch', function () {
    $provider = collect(discover()->json('data.providers'))
        ->firstWhere('provider_id', $this->salon->available->id);

    expect($provider)->toHaveKeys([
        'provider_id', 'provider_name', 'branch', 'service_pricing',
        'is_available', 'reason_code', 'available_slots',
    ])
        ->and($provider['is_available'])->toBeTrue()
        ->and($provider['reason_code'])->toBe('available')
        // toEqual, not toBe: json_encode renders the 100.0 float as `100`.
        ->and($provider['service_pricing']['effective_price'])->toEqual(100)
        ->and($provider['service_pricing']['formatted_price'])->toBe('100.00 EUR');
});

it('reports the service and the booking window in the envelope', function () {
    $data = discover()->json('data');

    expect($data['service']['duration_minutes'])->toBe(60)
        ->and($data['date'])->toBe(SalonFixture::DATE)
        ->and($data['last_bookable_date'])
        ->toBe(Carbon::parse(SalonFixture::NOW)->addDays(10)->format('Y-m-d'));
});

it('returns nobody for a date beyond the booking window', function () {
    $beyond = Carbon::parse(SalonFixture::NOW)->addDays(11)->format('Y-m-d');

    $data = discover(['date' => $beyond])->assertOk()->json('data');

    expect($data['total_providers'])->toBe(0)
        ->and($data['providers'])->toBe([]);
});

// ── Validation ───────────────────────────────────────────────────────────────

it('rejects a past date', function () {
    discover(['date' => Carbon::parse(SalonFixture::NOW)->subDay()->format('Y-m-d')])
        ->assertStatus(422);
});

it('rejects a missing service_id', function () {
    test()->getJson('/api/availability/service?date=' . SalonFixture::DATE)
        ->assertStatus(422);
});

it('rejects an unknown service_id', function () {
    discover(['service_id' => 999999])->assertStatus(422);
});

it('rejects an unknown branch_id', function () {
    discover(['branch_id' => 999999])->assertStatus(422);
});
