<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleSignInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Mockery;
use Tests\TestCase;

/**
 * POST /api/auth/google receives a Google ID token. Only the network call that
 * checks Google's signature is faked; the audience and email rules are real.
 */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const OUR_CLIENT = 'our-client.apps.googleusercontent.com';

    private const FIREBASE_PROJECT = 'app-test-2c25c';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => self::OUR_CLIENT,
            'services.google.allowed_audiences' => 'mobile-web-client.apps.googleusercontent.com',
            'services.google_mobile.android_client_id' => null,
            'services.google_mobile.ios_client_id' => null,
            'services.google.firebase_project_id' => self::FIREBASE_PROJECT,
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function fakeGooglePayload(array|false $payload): void
    {
        $service = Mockery::mock(GoogleSignInService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('decode')->andReturn($payload);

        $this->app->instance(GoogleSignInService::class, $service);
    }

    /**
     * A Firebase ID token: the routing step reads iss from it unverified, and
     * decodeFirebase() (the signature check) is faked to return $claims.
     */
    private function fakeFirebaseToken(array $claims): string
    {
        $service = Mockery::mock(GoogleSignInService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('decodeFirebase')->andReturn($claims);
        $service->shouldNotReceive('decode');

        $this->app->instance(GoogleSignInService::class, $service);

        $b64 = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        return $b64(['alg' => 'RS256']) . '.' . $b64($claims) . '.signature';
    }

    private function firebaseClaims(array $overrides = []): array
    {
        return array_replace_recursive([
            'iss' => 'https://securetoken.google.com/' . self::FIREBASE_PROJECT,
            'aud' => self::FIREBASE_PROJECT,
            'sub' => 'firebase-uid-xyz',
            'auth_time' => time() - 10,
            'email' => 'ali@example.com',
            'email_verified' => true,
            'name' => 'Ali Hasan',
            'firebase' => [
                'sign_in_provider' => 'google.com',
                'identities' => ['google.com' => ['1122334455'], 'email' => ['ali@example.com']],
            ],
        ], $overrides);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::OUR_CLIENT,
            'sub' => '1122334455',
            'email' => 'ali@example.com',
            'email_verified' => true,
            'given_name' => 'Ali',
            'family_name' => 'Hasan',
            'name' => 'Ali Hasan',
            'picture' => 'https://example.com/ali.png',
        ], $overrides);
    }

    public function test_valid_token_creates_the_user_and_returns_tokens(): void
    {
        $this->fakeGooglePayload($this->payload());

        $this->postJson('/api/auth/google', ['token' => 'id-token'])
            ->assertOk()
            ->assertJsonPath('user.email', 'ali@example.com')
            ->assertJsonPath('user.first_name', 'Ali')
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $user = User::where('email', 'ali@example.com')->firstOrFail();
        $this->assertSame('1122334455', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_extra_allowed_audience_is_accepted(): void
    {
        $this->fakeGooglePayload($this->payload(['aud' => 'mobile-web-client.apps.googleusercontent.com']));

        $this->postJson('/api/auth/google', ['token' => 'id-token'])->assertOk();
    }

    public function test_token_issued_to_another_app_is_rejected(): void
    {
        $this->fakeGooglePayload($this->payload(['aud' => 'someone-else.apps.googleusercontent.com']));

        $this->postJson('/api/auth/google', ['token' => 'id-token'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid Google token');

        $this->assertDatabaseMissing('users', ['email' => 'ali@example.com']);
    }

    public function test_unverified_email_is_rejected(): void
    {
        $this->fakeGooglePayload($this->payload(['email_verified' => false]));

        $this->postJson('/api/auth/google', ['token' => 'id-token'])->assertStatus(422);
    }

    public function test_bad_signature_is_rejected(): void
    {
        $this->fakeGooglePayload(false);

        $this->postJson('/api/auth/google', ['token' => 'not-a-real-token'])->assertStatus(422);
    }

    public function test_existing_account_is_linked_by_email(): void
    {
        $existing = User::create([
            'first_name' => 'Old',
            'last_name' => 'Account',
            'email' => 'ali@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $this->fakeGooglePayload($this->payload());

        $this->postJson('/api/auth/google', ['token' => 'id-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $existing->id);

        $this->assertSame('1122334455', $existing->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'ali@example.com')->count());
    }

    public function test_mobile_route_reaches_its_controller(): void
    {
        $this->fakeGooglePayload($this->payload());

        $this->postJson('/api/auth/google/mobile', ['id_token' => 'id-token', 'platform' => 'android'])
            ->assertOk()
            ->assertJsonPath('user.email', 'ali@example.com');
    }

    public function test_response_matches_password_login_shape(): void
    {
        $this->fakeGooglePayload($this->payload());

        $this->postJson('/api/auth/google', ['token' => 'id-token'])
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'first_name', 'last_name', 'full_name', 'email', 'avatar_url'],
                'access_token', 'access_expires_at', 'refresh_token', 'refresh_expires_at', 'token_type',
            ]);
    }

    public function test_firebase_google_token_is_accepted_and_uses_the_google_id(): void
    {
        $token = $this->fakeFirebaseToken($this->firebaseClaims());

        $this->postJson('/api/auth/google', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('user.first_name', 'Ali')
            ->assertJsonPath('user.last_name', 'Hasan');

        // Google's account id, not the Firebase uid: both token kinds reach the same user.
        $this->assertSame('1122334455', User::where('email', 'ali@example.com')->value('google_id'));
    }

    public function test_firebase_password_account_is_rejected(): void
    {
        $token = $this->fakeFirebaseToken($this->firebaseClaims([
            'firebase' => ['sign_in_provider' => 'password', 'identities' => ['google.com' => null]],
        ]));

        $this->postJson('/api/auth/google', ['token' => $token])->assertStatus(422);
    }

    public function test_firebase_token_for_another_project_is_rejected(): void
    {
        $token = $this->fakeFirebaseToken($this->firebaseClaims(['aud' => 'someone-elses-project']));

        $this->postJson('/api/auth/google', ['token' => $token])->assertStatus(422);
    }

    public function test_firebase_tokens_are_rejected_when_no_project_is_configured(): void
    {
        config(['services.google.firebase_project_id' => '']);

        $service = Mockery::mock(GoogleSignInService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldNotReceive('decodeFirebase');
        // The real Google verifier would reject a securetoken issuer; fake that outcome.
        $service->shouldReceive('decode')->andReturn(false);
        $this->app->instance(GoogleSignInService::class, $service);

        $b64 = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
        $token = $b64(['alg' => 'RS256']) . '.' . $b64($this->firebaseClaims()) . '.signature';

        $this->postJson('/api/auth/google', ['token' => $token])->assertStatus(422);
    }
}
