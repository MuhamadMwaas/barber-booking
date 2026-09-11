<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Guards AUTH-01.
 *
 * The auth endpoints were unthrottled, which made credential stuffing and OTP
 * brute force a matter of bandwidth rather than difficulty. These tests fail if
 * a limiter is removed, renamed, or silently narrowed back to a single dimension.
 *
 * The load-bearing case is `login is limited per account even when the source IP
 * changes on every request`: an attacker picks their address but never the
 * account they are attacking, so a per-IP-only limit protects nobody in
 * particular. If that test starts passing with an IP-only limiter, the limiter
 * is not doing its job.
 */
class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limiter buckets live in the cache and would otherwise leak between
        // tests, making order-dependent failures that are miserable to chase.
        RateLimiter::clear('auth-login');
        $this->app['cache']->flush();
    }

    /**
     * Post to an auth route from a specific source address.
     *
     * Named `postFromIp` rather than `from` because Illuminate's TestCase already
     * defines a public `from()` (it sets the previous URL) and redeclaring it
     * private is a fatal error.
     */
    private function postFromIp(string $ip, string $uri, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson($uri, $body);
    }

    public function test_login_is_limited_per_ip_across_different_accounts(): void
    {
        $limit = (int) config('rate_limits.login.per_ip');

        // Each attempt targets a different account, so only the IP dimension can
        // trip. Anything else passing here would mean the per-IP limit is absent.
        for ($i = 1; $i <= $limit; $i++) {
            $this->postFromIp('203.0.113.10', '/api/auth/login', [
                'registration_method' => 'email',
                'email' => "user{$i}@example.com",
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postFromIp('203.0.113.10', '/api/auth/login', [
            'registration_method' => 'email',
            'email' => 'user-over-the-limit@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_login_is_limited_per_account_even_when_the_source_ip_changes(): void
    {
        $limit = (int) config('rate_limits.login.per_account');

        // Every request comes from a fresh address, so the per-IP bucket is never
        // spent. Only the account dimension can stop this.
        for ($i = 1; $i <= $limit; $i++) {
            $this->postFromIp("198.51.100.{$i}", '/api/auth/login', [
                'registration_method' => 'email',
                'email' => 'victim@example.com',
                'password' => "guess-{$i}",
            ])->assertStatus(401);
        }

        $this->postFromIp('198.51.100.200', '/api/auth/login', [
            'registration_method' => 'email',
            'email' => 'victim@example.com',
            'password' => 'guess-final',
        ])->assertStatus(429);
    }

    public function test_account_bucket_ignores_email_casing_and_surrounding_space(): void
    {
        $limit = (int) config('rate_limits.login.per_account');

        for ($i = 1; $i <= $limit; $i++) {
            $this->postFromIp("198.51.100.{$i}", '/api/auth/login', [
                'registration_method' => 'email',
                'email' => 'victim@example.com',
                'password' => 'x',
            ]);
        }

        // Without normalisation this lands in a fresh bucket and the limit is
        // doubled by simply varying the case.
        $this->postFromIp('198.51.100.201', '/api/auth/login', [
            'registration_method' => 'email',
            'email' => '  VICTIM@Example.COM  ',
            'password' => 'x',
        ])->assertStatus(429);
    }

    public function test_otp_verification_is_limited_per_destination_across_ips(): void
    {
        $limit = (int) config('rate_limits.otp_verify.per_destination');

        for ($i = 1; $i <= $limit; $i++) {
            $this->postFromIp("192.0.2.{$i}", '/api/auth/verify-otp', [
                'registration_method' => 'email',
                'email' => 'victim@example.com',
                'otp' => str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            ]);
        }

        $this->postFromIp('192.0.2.200', '/api/auth/verify-otp', [
            'registration_method' => 'email',
            'email' => 'victim@example.com',
            'otp' => '999999',
        ])->assertStatus(429);
    }

    public function test_verify_email_otp_shares_the_verification_budget(): void
    {
        // verifyEmailViaOtp() delegates to verifyOtp(); if it carried its own
        // (or no) limiter it would be a second, unmetered door to the same
        // brute-force surface.
        $limit = (int) config('rate_limits.otp_verify.per_destination');

        for ($i = 1; $i <= $limit; $i++) {
            $this->postFromIp("192.0.2.{$i}", '/api/auth/verify-otp', [
                'registration_method' => 'email',
                'email' => 'victim@example.com',
                'otp' => '111111',
            ]);
        }

        $this->postFromIp('192.0.2.201', '/api/auth/verify-email-otp', [
            'email' => 'victim@example.com',
            'otp' => '222222',
        ])->assertStatus(429);
    }

    public function test_registration_is_limited_per_ip(): void
    {
        $limit = (int) config('rate_limits.register.per_ip_hour');

        for ($i = 1; $i <= $limit; $i++) {
            $this->postFromIp('203.0.113.50', '/api/auth/register', [
                'registration_method' => 'email',
                'email' => "signup{$i}@example.com",
                'password' => 'x',
            ]);
        }

        $this->postFromIp('203.0.113.50', '/api/auth/register', [
            'registration_method' => 'email',
            'email' => 'signup-over@example.com',
            'password' => 'x',
        ])->assertStatus(429);
    }

    public function test_throttled_response_uses_the_api_error_envelope(): void
    {
        $limit = (int) config('rate_limits.login.per_account');

        for ($i = 1; $i <= $limit; $i++) {
            $this->postFromIp("198.51.100.{$i}", '/api/auth/login', [
                'registration_method' => 'email',
                'email' => 'envelope@example.com',
                'password' => 'x',
            ]);
        }

        // The mobile app parses `success`/`message`; Laravel's stock 429 body is a
        // bare English `{"message": "Too Many Attempts."}` it cannot render.
        $this->postFromIp('198.51.100.202', '/api/auth/login', [
            'registration_method' => 'email',
            'email' => 'envelope@example.com',
            'password' => 'x',
        ])
            ->assertStatus(429)
            ->assertJsonStructure(['success', 'message', 'error_type', 'retry_after'])
            ->assertJson(['success' => false, 'error_type' => 'rate_limited'])
            ->assertHeader('Retry-After');
    }

    /**
     * A limiter must never be the thing that breaks a request. `email[]=x` used to
     * reach a string cast and return 500 from an unauthenticated endpoint.
     */
    public function test_malformed_identifiers_are_rejected_without_a_server_error(): void
    {
        $bodies = [
            'array email' => ['registration_method' => 'email', 'email' => ['a@b.c'], 'password' => 'x'],
            'array method' => ['registration_method' => ['email'], 'email' => 'a@b.c', 'password' => 'x'],
            'integer method' => ['registration_method' => 123, 'email' => 'a@b.c', 'password' => 'x'],
            'missing identifier' => ['registration_method' => 'email', 'password' => 'x'],
            'blank identifier' => ['registration_method' => 'email', 'email' => '   ', 'password' => 'x'],
        ];

        foreach ($bodies as $label => $body) {
            $status = $this->postFromIp('198.18.0.' . rand(2, 250), '/api/auth/login', $body)->getStatusCode();

            $this->assertLessThan(500, $status, "[{$label}] returned a server error");
        }
    }
}
