<?php

/**
 * STEP 1 of the booking journey — the customer signs in and picks a service.
 */

use App\Models\Language;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture();
});

afterEach(function () {
    Illuminate\Support\Carbon::setTestNow();
});

// ── Login ────────────────────────────────────────────────────────────────────

it('logs the customer in with the real QA credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'registration_method' => 'email',
        'email' => 'lina.hassan@gmail.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('is_account_verified', true)
        ->assertJsonPath('requires_otp_verification', false)
        ->assertJsonStructure(['access_token', 'refresh_token', 'user' => ['id', 'email']]);

    expect($response->json('access_token'))->not->toBeEmpty();
});

it('rejects a wrong password', function () {
    $this->postJson('/api/auth/login', [
        'registration_method' => 'email',
        'email' => 'lina.hassan@gmail.com',
        'password' => 'not-the-password',
    ])->assertStatus(401);
});

it('refuses to create a booking without a token', function () {
    $this->postJson('/api/bookings', [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ]],
    ])->assertStatus(401);
});

// ── Service catalogue ────────────────────────────────────────────────────────

it('lists only active services', function () {
    $response = $this->getJson('/api/services')->assertOk();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Hair Cut')
        ->and($names)->not->toContain('Retired Service');
});

it('returns a single service with its providers', function () {
    $response = $this->getJson("/api/services/{$this->salon->service->id}")->assertOk();

    expect($response->json('data.id'))->toBe($this->salon->service->id)
        ->and($response->json('data.duration_minutes'))->toBe(60);
});

it('404s for a service that does not exist', function () {
    $this->getJson('/api/services/999999')->assertStatus(404);
});

/**
 * BUG — Service::translation() dereferences a null Language.
 *
 * Service.php:157 / ServiceCategory.php:87 do:
 *     $language = Language::where('code', $locale)->first()
 *             ?: Language::where('is_default', true)->first();
 *     ... ->where('language_id', $language->id)
 *
 * The second lookup can also return null, and nothing guards it. The whole
 * service catalogue then 500s. It is reachable from ProviderResource:39
 * (`$service->translated_name` on services loaded without their translations).
 *
 * Production seeds a default language, so this is a latent landmine rather than
 * a live outage: deleting the default language row, or having a locale with no
 * row while `is_default` is unset, takes the endpoint down.
 */
it('500s on the service endpoint when no default language row exists', function () {
    Language::query()->delete();

    $response = $this->getJson("/api/services/{$this->salon->service->id}");

    // Asserted as-is (not as the desired behaviour) so the suite records the
    // defect. Flip both expectations to 200 once the null-guard lands.
    expect($response->status())->toBe(500)
        ->and($response->json('error'))->toContain('Attempt to read property "id" on null');
});
