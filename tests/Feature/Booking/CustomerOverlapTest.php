<?php

/**
 * BOOK-06 — the parts of the customer-overlap rule the HTTP path cannot reach:
 * guest identity, phone normalisation, and the staff override.
 */

use App\Models\User;
use App\Services\BookingService;
use App\Services\BookingValidationService;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Carbon::setTestNow(SalonFixture::NOW);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Book the fixture service at $start for a guest identified only by phone. */
function bookGuest(string $phone, string $start = '10:00'): void
{
    app(BookingService::class)->createBooking(null, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'customer_name' => 'Walk In',
        'customer_phone' => $phone,
        'services' => [[
            'service_id' => test()->salon->service->id,
            'provider_id' => test()->salon->available->id,
            'start_time' => $start,
        ]],
    ]);
}

it('recognises the same guest across different phone formats', function () {
    bookGuest('+49 176 1234567');

    // Same person, written three other ways, booking a different provider.
    foreach (['0049 176 1234567', '0176 1234567', '01761234567'] as $variant) {
        expect(fn () => app(BookingValidationService::class)->assertCustomerIsFree(
            null,
            $variant,
            Carbon::parse(SalonFixture::DATE.' 10:00'),
            Carbon::parse(SalonFixture::DATE.' 11:00'),
        ))->toThrow(InvalidArgumentException::class);
    }
});

it('does not confuse two different guests', function () {
    bookGuest('+49 176 1234567');

    expect(fn () => app(BookingValidationService::class)->assertCustomerIsFree(
        null,
        '+49 176 7654321',
        Carbon::parse(SalonFixture::DATE.' 10:00'),
        Carbon::parse(SalonFixture::DATE.' 11:00'),
    ))->not->toThrow(InvalidArgumentException::class);
});

it('never matches two guests who both left no phone number', function () {
    // Two anonymous walk-ins are not the same person.
    expect(fn () => app(BookingValidationService::class)->assertCustomerIsFree(
        null,
        null,
        Carbon::parse(SalonFixture::DATE.' 10:00'),
        Carbon::parse(SalonFixture::DATE.' 11:00'),
    ))->not->toThrow(InvalidArgumentException::class);
});

it('ties a registered customer to a booking they made as a guest', function () {
    $customer = $this->salon->customer;
    $customer->update(['phone' => '+49 176 1234567']);

    // Booked earlier at the counter as a walk-in, with no account attached.
    bookGuest('0176 1234567');

    expect(fn () => app(BookingValidationService::class)->assertCustomerIsFree(
        $customer->fresh(),
        null,
        Carbon::parse(SalonFixture::DATE.' 10:00'),
        Carbon::parse(SalonFixture::DATE.' 11:00'),
    ))->toThrow(InvalidArgumentException::class);
});

it('lets a booking ignore its own row when it is being moved', function () {
    bookGuest('+49 176 1234567');

    $existing = App\Models\Appointment::firstWhere('customer_phone', '+49 176 1234567');

    expect(fn () => app(BookingValidationService::class)->assertCustomerIsFree(
        null,
        '+49 176 1234567',
        Carbon::parse(SalonFixture::DATE.' 10:00'),
        Carbon::parse(SalonFixture::DATE.' 11:00'),
        $existing->id,
    ))->not->toThrow(InvalidArgumentException::class);
});

it('lets authorised staff book a deliberate overlap', function () {
    bookGuest('+49 176 1234567');

    // Same phone, same hour, different provider — refused for a normal booking…
    $overlapping = [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'customer_name' => 'Walk In',
        'customer_phone' => '+49 176 1234567',
        'services' => [[
            'service_id' => $this->salon->secondService->id,
            'provider_id' => $this->salon->doesNotOffer->id,
            'start_time' => '10:00',
        ]],
    ];

    expect(fn () => app(BookingService::class)->createBooking(null, $overlapping))
        ->toThrow(InvalidArgumentException::class);

    // …and allowed once the force-booking flag is raised (a manicure while the
    // colour develops is a real thing; the counter can see it, the rule cannot).
    $appointment = app(BookingService::class)->createBooking(
        null,
        $overlapping + ['allow_customer_overlap' => true],
    );

    expect($appointment->exists)->toBeTrue();
});

it('builds a comparison key from the significant tail of a number', function () {
    expect(PhoneNumber::key('+49 176 1234567'))->toBe('761234567')
        ->and(PhoneNumber::sameNumber('+491761234567', '01761234567'))->toBeTrue()
        ->and(PhoneNumber::sameNumber('+491761234567', '+491767654321'))->toBeFalse()
        // Too short to identify anybody, and blanks never match each other.
        ->and(PhoneNumber::key('1234'))->toBeNull()
        ->and(PhoneNumber::key(''))->toBeNull()
        ->and(PhoneNumber::sameNumber(null, null))->toBeFalse();
});
