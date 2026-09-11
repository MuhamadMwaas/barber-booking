<?php

/**
 * BOOK-09 — a missing service or provider must never reach a typed parameter.
 *
 * The root cause was a disagreement about soft deletes: `exists:` validation
 * rules query the table raw and happily match a deleted row, while the service
 * layer loads the same id through Eloquent, whose global scope hides it. The null
 * that came back hit a typed parameter and raised a TypeError — an Error, not an
 * Exception, so it escaped every catch and surfaced as a bare 500.
 *
 * Guarded on both sides: the rules exclude deleted rows, and the boundary itself
 * accepts null and rejects it like any other unavailable pair.
 */

use App\Models\Service;
use App\Models\User;
use App\Services\BookingValidationService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->token = $this->salon->customerToken();
    Carbon::setTestNow(SalonFixture::NOW);
});

afterEach(function () {
    Carbon::setTestNow();
});

function attemptBooking(int $serviceId, int $providerId): TestResponse
{
    return test()->withToken(test()->token)->postJson('/api/bookings', [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $serviceId,
            'provider_id' => $providerId,
            'start_time' => '10:00',
        ]],
    ]);
}

it('refuses a soft-deleted service with a validation error, not a 500', function () {
    $this->salon->service->delete();

    $response = attemptBooking($this->salon->service->id, $this->salon->available->id)
        ->assertStatus(422);

    // The MessageBag keys are literal dotted strings ("services.0.service_id"),
    // not a nested structure, so read the bag rather than a JSON path.
    expect($response->json('errors')['services.0.service_id'][0])
        ->toBe(__('booking.provider_unavailable_for_service'));
});

it('refuses a soft-deleted provider with a validation error, not a 500', function () {
    $this->salon->available->delete();

    attemptBooking($this->salon->service->id, $this->salon->available->id)
        ->assertStatus(422);
});

it('still refuses ids that never existed', function () {
    attemptBooking(999999, 999999)->assertStatus(422);
});

// ── The service boundary itself, which internal callers reach directly ───────
//
// StaffDashboard builds its payload by hand and calls BookingService without any
// Form Request, so the rules above never run for it. These pin the second layer.

it('rejects a null service at the boundary instead of raising a TypeError', function () {
    expect(fn () => app(BookingValidationService::class)->validateProviderOffersService(
        $this->salon->available,
        null,
        $this->salon->available->id,
        999999,
    ))->toThrow(InvalidArgumentException::class, __('booking.provider_unavailable_for_service'));
});

it('rejects a null provider at the boundary instead of raising a TypeError', function () {
    expect(fn () => app(BookingValidationService::class)->validateProviderOffersService(
        null,
        $this->salon->service,
        999999,
        $this->salon->service->id,
    ))->toThrow(InvalidArgumentException::class, __('booking.provider_unavailable_for_service'));
});

it('gives a missing row the same message as an unavailable pair', function () {
    // Non-enumeration: "does not exist", "is inactive" and "is not offered" must
    // be indistinguishable from outside, or the endpoint becomes a way to probe
    // which service and user ids are real.
    $missing = null;
    $inactive = Service::withoutGlobalScopes()->find($this->salon->inactiveService->id);

    $validator = app(BookingValidationService::class);

    $messages = [];

    foreach ([[$missing, 999999], [$inactive, $inactive->id]] as [$service, $id]) {
        try {
            $validator->validateProviderOffersService(
                $this->salon->available,
                $service,
                $this->salon->available->id,
                $id,
            );
        } catch (InvalidArgumentException $e) {
            $messages[] = $e->getMessage();
        }
    }

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBe($messages[1]);
});

it('does not name the missing service or provider in the response', function () {
    $this->salon->service->delete();

    $body = json_encode(attemptBooking($this->salon->service->id, $this->salon->available->id)->json());

    expect($body)->not->toContain($this->salon->service->name)
        ->and($body)->not->toContain($this->salon->available->full_name);
});
