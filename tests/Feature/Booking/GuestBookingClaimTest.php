<?php

/**
 * A guest booked at the counter by phone; once that person verifies the same
 * number on their own account, the bookings (and invoices) become theirs.
 */

use App\Enum\OtpType;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Carbon::setTestNow(SalonFixture::NOW);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Book the fixture service at $start as a counter guest identified by phone. */
function bookCounterGuest(string $phone, string $start = '10:00'): Appointment
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

    return Appointment::query()->latest('id')->firstOrFail();
}

function newAccount(string $phone): User
{
    return User::create([
        'first_name' => 'New',
        'last_name' => 'Customer',
        'email' => uniqid('c').'@example.com',
        'phone' => $phone,
        'password' => bcrypt('Password@123'),
        'registration_method' => 'phone',
    ]);
}

it('links guest bookings and their invoices once the phone is verified', function () {
    $first = bookCounterGuest('0176 1234567', '10:00');
    $second = bookCounterGuest('0176-123 4567', '12:00');
    // Booking creates the draft invoice; a guest invoice has no owner yet.
    expect(Invoice::firstWhere('appointment_id', $first->id)->customer_id)->toBeNull();

    $user = newAccount('+49 176 1234567');

    // Registered but not verified yet: nothing moves.
    expect($first->fresh()->customer_id)->toBeNull();

    app(AccountVerificationService::class)->markVerified($user, OtpType::SMS_OTP);

    expect($first->fresh()->customer_id)->toBe($user->id)
        ->and($second->fresh()->customer_id)->toBe($user->id)
        ->and(Invoice::firstWhere('appointment_id', $first->id)->customer_id)->toBe($user->id)
        // What the staff typed is kept.
        ->and($first->fresh()->getRawOriginal('customer_phone'))->toBe('0176 1234567');
});

it('does not link on an email verification', function () {
    $guest = bookCounterGuest('0176 1234567');
    $user = newAccount('+49 176 1234567');

    app(AccountVerificationService::class)->markVerified($user, OtpType::EMAIL_OTP);

    expect($guest->fresh()->customer_id)->toBeNull();
});

it('leaves other numbers and other accounts alone', function () {
    $stranger = bookCounterGuest('0176 7654321', '10:00');
    $owned = bookCounterGuest('0176 1234567', '12:00');
    $owned->update(['customer_id' => $this->salon->customer->id]);

    $user = newAccount('+49 176 1234567');
    app(AccountVerificationService::class)->markVerified($user, OtpType::SMS_OTP);

    expect($stranger->fresh()->customer_id)->toBeNull()
        ->and($owned->fresh()->customer_id)->toBe($this->salon->customer->id);
});

it('backfills accounts verified before the feature existed', function () {
    $guest = bookCounterGuest('0176 1234567');
    $user = newAccount('+49 176 1234567');
    $user->forceFill(['phone_verified_at' => now()])->save();

    $this->artisan('appointments:claim-guest-bookings')->assertSuccessful();

    expect($guest->fresh()->customer_id)->toBe($user->id);
});
