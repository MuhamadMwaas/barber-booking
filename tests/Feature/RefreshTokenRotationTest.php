<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AUTH-04 — refresh token rotation with reuse detection.
 *
 * The property under test is that a refresh token is a ONE-TIME credential.
 * Everything else here follows from that: if a token can only be spent once,
 * then a second appearance of a spent token is proof that a copy of it exists,
 * and the only safe reading of that is theft.
 */
class RefreshTokenRotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('customer', 'web');

        // The refresh endpoint is rate limited (AUTH-01). These tests exercise
        // the rotation logic, not the limiter, so the middleware is stood down.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function verifiedUser(string $email = 'rotation@example.com'): User
    {
        $user = User::create([
            'first_name' => 'Rot',
            'last_name' => 'Ation',
            'email' => $email,
            'registration_method' => 'email',
            'password' => bcrypt('Password@123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('customer');

        return $user;
    }

    /**
     * Seed a refresh token directly, so a test can control its exact state.
     *
     * @return array{0: string, 1: RefreshToken} the plaintext and its row
     */
    private function seedRefreshToken(User $user, array $attributes = []): array
    {
        $plain = 'plain-refresh-' . bin2hex(random_bytes(8));

        $token = RefreshToken::create(array_merge([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'device' => 'PHPUnit',
            'ip' => '127.0.0.1',
            'expires_at' => now()->addDays(30),
        ], $attributes));

        return [$plain, $token];
    }

    // ------------------------------------------------------------- rotation

    public function test_refresh_returns_a_new_refresh_token_and_kills_the_old_one(): void
    {
        $user = $this->verifiedUser();
        [$plain, $token] = $this->seedRefreshToken($user);

        $response = $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'access_expires_at', 'refresh_token', 'refresh_expires_at']);

        $rotated = $response->json('refresh_token');

        $this->assertNotSame($plain, $rotated, 'Rotation must hand back a different token.');

        $token->refresh();
        $this->assertTrue($token->revoked);
        $this->assertSame(RefreshToken::REASON_ROTATED, $token->revoked_reason);
        $this->assertNotNull($token->revoked_at);

        // The successor is linked, and it is the one now live.
        $successor = RefreshToken::find($token->replaced_by_id);
        $this->assertNotNull($successor, 'The spent token must point at its replacement.');
        $this->assertSame(hash('sha256', $rotated), $successor->token_hash);
        $this->assertFalse($successor->revoked);
    }

    public function test_the_new_refresh_token_works_and_rotates_again(): void
    {
        $user = $this->verifiedUser();
        [$plain] = $this->seedRefreshToken($user);

        $second = $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])
            ->assertOk()->json('refresh_token');

        $third = $this->postJson('/api/auth/refresh', ['refresh_token' => $second])
            ->assertOk()->json('refresh_token');

        $this->assertNotSame($second, $third);

        // One live token at a time, no matter how many refreshes have happened.
        $this->assertSame(1, $user->refreshTokens()->where('revoked', false)->count());
    }

    public function test_an_expired_refresh_token_is_rejected_without_raising_an_alarm(): void
    {
        $user = $this->verifiedUser();
        [$plain] = $this->seedRefreshToken($user, ['expires_at' => now()->subMinute()]);
        $user->createToken('mobile');

        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])->assertStatus(401);

        // Expiry is ordinary. It must not be mistaken for theft and must not
        // take the account's other sessions down with it.
        $this->assertSame(1, $user->tokens()->count());
    }

    // ------------------------------------------------------- reuse detection

    public function test_replaying_a_rotated_token_revokes_every_session_for_the_account(): void
    {
        Log::spy();

        $user = $this->verifiedUser();
        [$stolen] = $this->seedRefreshToken($user);

        // The legitimate holder refreshes. `$stolen` is now spent — but the
        // attacker copied it before that happened.
        $legitimate = $this->postJson('/api/auth/refresh', ['refresh_token' => $stolen])
            ->assertOk()->json('refresh_token');

        // Past the grace window, so this is read as a leak rather than a retry.
        $this->travel(2)->minutes();

        $this->postJson('/api/auth/refresh', ['refresh_token' => $stolen])->assertStatus(401);

        // Every refresh token for the account is dead — including the good one
        // the legitimate user is holding. That is the point: the server cannot
        // tell victim from thief, so neither is allowed to continue.
        $this->assertSame(0, $user->refreshTokens()->where('revoked', false)->count());
        $this->assertSame(0, $user->tokens()->count());

        $this->postJson('/api/auth/refresh', ['refresh_token' => $legitimate])->assertStatus(401);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'Refresh token reuse detected'))
            ->once();
    }

    public function test_a_retry_inside_the_grace_window_is_rejected_but_spares_other_sessions(): void
    {
        $user = $this->verifiedUser();
        [$plain] = $this->seedRefreshToken($user);

        // The client refreshed successfully but never received the reply, so it
        // still holds the old token and honestly retries with it.
        $survivor = $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])
            ->assertOk()->json('refresh_token');

        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])->assertStatus(401);

        // No alarm: the replacement issued moments ago is still usable.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $survivor])->assertOk();
    }

    public function test_replaying_a_token_revoked_by_logout_does_not_raise_an_alarm(): void
    {
        $user = $this->verifiedUser();
        [$plain] = $this->seedRefreshToken($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/logout')
            ->assertOk();

        // A second device logs in after the logout.
        [$other] = $this->seedRefreshToken($user);

        $this->travel(2)->minutes();

        // A stale client replays the logged-out token. This is not theft, so
        // the other live session must survive it.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])->assertStatus(401);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $other])->assertOk();
    }

    public function test_an_unknown_token_is_rejected_without_touching_anything(): void
    {
        $user = $this->verifiedUser();
        [$live] = $this->seedRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => 'never-issued-by-us'])
            ->assertStatus(401);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $live])->assertOk();
    }

    // -------------------------------------------------------------- hashing

    public function test_a_token_hashed_under_the_legacy_app_key_scheme_still_works(): void
    {
        $user = $this->verifiedUser();

        // How tokens were stored before AUTH-04.
        $plain = 'legacy-refresh-token';
        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain . config('app.key')),
            'expires_at' => now()->addDays(30),
        ]);

        $rotated = $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])
            ->assertOk()->json('refresh_token');

        // Rotation retires the legacy scheme: the replacement is stored under
        // the bare digest, so old-format rows drain out as clients come back.
        $this->assertDatabaseHas('refresh_tokens', [
            'token_hash' => hash('sha256', $rotated),
            'revoked' => false,
        ]);
    }

    public function test_rotating_a_token_does_not_depend_on_the_app_key_staying_put(): void
    {
        $user = $this->verifiedUser();
        [$plain] = $this->seedRefreshToken($user);

        // Rotating APP_KEY is standard practice after a suspected key leak. It
        // must not sign every customer out as a side effect.
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])->assertOk();
    }

    // ------------------------------------------------- session invalidation

    public function test_changing_the_password_ends_every_session(): void
    {
        $user = $this->verifiedUser();
        [$plain] = $this->seedRefreshToken($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/change-password', [
                'current_password' => 'Password@123',
                'password' => 'BrandNew@123',
                'password_confirmation' => 'BrandNew@123',
            ])
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, $user->refreshTokens()->where('revoked', false)->count());

        // The refresh token an attacker may be holding is dead too, so the
        // password change cannot be outlived by the session it was meant to end.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])->assertStatus(401);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'revoked_reason' => RefreshToken::REASON_PASSWORD_CHANGE,
        ]);
    }

    // ------------------------------------------------------- rotation switch

    public function test_rotation_can_be_switched_off_for_clients_that_cannot_store_the_new_token(): void
    {
        config(['auth_tokens.rotate_refresh_tokens' => false]);

        $user = $this->verifiedUser();
        [$plain, $token] = $this->seedRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])
            ->assertOk()
            ->assertJsonMissingPath('refresh_token');

        $this->assertFalse($token->fresh()->revoked, 'With rotation off the token survives its use.');

        $this->postJson('/api/auth/refresh', ['refresh_token' => $plain])->assertOk();
    }
}
