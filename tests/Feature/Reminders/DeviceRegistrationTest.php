<?php

/**
 * POST /api/register-device and /api/deregister-device — the link between a
 * customer and the OneSignal Subscription ID their push reminders go to.
 *
 * device_id IS the OneSignal Subscription ID (NotificationService targets that
 * column, not device_token), and it belongs to the phone, not the account. These
 * tests pin the hand-over when a second account signs in on the same phone.
 */

use App\Models\User;
use App\Models\UserDevice;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->subscriptionId = '1dd608f2-c6a1-11e3-851d-000c2940e62c';
});

function makeSecondCustomer(): User
{
    $user = User::factory()->create([
        'email' => 'second.customer@gmail.com',
        'registration_method' => 'email',
        'email_verified_at' => now(),
        'email_verified_via_otp_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole('customer');

    return $user;
}

it('registers the subscription id as the push target', function () {
    $this->withToken($this->salon->customerToken())
        ->postJson('/api/register-device', [
            'device_id' => $this->subscriptionId,
            'platform' => 'android',
        ])
        ->assertCreated();

    expect(UserDevice::where('device_id', $this->subscriptionId)->first())
        ->user_id->toBe($this->salon->customer->id)
        ->is_active->toBeTrue();
});

it('hands the phone over to a second account instead of failing on the unique index', function () {
    $first = $this->salon->customer;
    $second = makeSecondCustomer();

    $this->withToken($first->createToken('t')->plainTextToken)
        ->postJson('/api/register-device', ['device_id' => $this->subscriptionId])
        ->assertCreated();

    // The first account never deregistered (token expired, app killed, ...).
    // forgetGuards(): the test client reuses one app instance, and the sanctum
    // guard would otherwise keep answering with the first user.
    $this->app['auth']->forgetGuards();
    $this->withToken($second->createToken('t')->plainTextToken)
        ->postJson('/api/register-device', ['device_id' => $this->subscriptionId])
        ->assertCreated();

    expect(UserDevice::where('device_id', $this->subscriptionId)->count())->toBe(1);
    expect(UserDevice::where('device_id', $this->subscriptionId)->first()->user_id)->toBe($second->id);

    // The previous owner no longer reaches this phone.
    expect($first->devices()->where('is_active', true)->exists())->toBeFalse();
});

it('re-activates a device after deregister on the next login', function () {
    $token = $this->salon->customerToken();

    $this->withToken($token)->postJson('/api/register-device', ['device_id' => $this->subscriptionId])->assertCreated();
    $this->withToken($token)->postJson('/api/deregister-device', ['device_id' => $this->subscriptionId])->assertOk();

    expect(UserDevice::where('device_id', $this->subscriptionId)->first()->is_active)->toBeFalse();

    $this->withToken($token)->postJson('/api/register-device', ['device_id' => $this->subscriptionId])->assertCreated();

    expect(UserDevice::where('device_id', $this->subscriptionId)->first()->is_active)->toBeTrue();
});

it('does not let one account deregister another account\'s phone', function () {
    $second = makeSecondCustomer();

    $this->withToken($second->createToken('t')->plainTextToken)
        ->postJson('/api/register-device', ['device_id' => $this->subscriptionId])
        ->assertCreated();

    $this->app['auth']->forgetGuards();
    $this->withToken($this->salon->customerToken())
        ->postJson('/api/deregister-device', ['device_id' => $this->subscriptionId])
        ->assertNotFound();

    expect(UserDevice::where('device_id', $this->subscriptionId)->first()->is_active)->toBeTrue();
});
