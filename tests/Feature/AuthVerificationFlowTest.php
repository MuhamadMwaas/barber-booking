<?php

namespace Tests\Feature;

use App\Enum\OtpType;
use App\Jobs\SendOtpDeliveryJob;
use App\Models\Otp;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('customer', 'web');
        Role::findOrCreate('provider', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('manager', 'web');

        Mail::fake();
        Http::fake();
    }

    public function test_register_with_email_creates_unverified_user_without_tokens(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Customer',
            'registration_method' => 'email',
            'email' => 'new-customer@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonPath('requires_otp_verification', true)
            ->assertJsonPath('registration_method', 'email')
            ->assertJsonPath('user.email', 'new-customer@example.com');

        $user = User::query()->where('email', 'new-customer@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('customer'));
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('otps', [
            'email' => 'new-customer@example.com',
            'type' => OtpType::EMAIL_OTP->value,
            'used' => false,
        ]);

        Queue::assertPushed(SendOtpDeliveryJob::class, function (SendOtpDeliveryJob $job) use ($user) {
            return $job->userId === $user->id
                && $job->type === OtpType::EMAIL_OTP
                && strlen($job->otp) === 6;
        });
    }

    public function test_register_with_phone_creates_unverified_user_without_tokens(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Phone',
            'last_name' => 'Customer',
            'registration_method' => 'phone',
            'phone' => '+491234567890',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonPath('requires_otp_verification', true)
            ->assertJsonPath('registration_method', 'phone')
            ->assertJsonPath('user.phone', '+491234567890');

        $this->assertDatabaseHas('otps', [
            'phone' => '+491234567890',
            'type' => OtpType::SMS_OTP->value,
            'used' => false,
        ]);

        Queue::assertPushed(SendOtpDeliveryJob::class, function (SendOtpDeliveryJob $job) {
            return $job->type === OtpType::SMS_OTP
                && strlen($job->otp) === 6;
        });
    }

    public function test_unverified_login_returns_verification_challenge_without_tokens(): void
    {
        $user = User::create([
            'first_name' => 'Pending',
            'last_name' => 'Customer',
            'email' => 'pending@example.com',
            'registration_method' => 'email',
            'password' => 'Password@123',
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        $response = $this->postJson('/api/auth/login', [
            'registration_method' => 'email',
            'email' => 'pending@example.com',
            'password' => 'Password@123',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonPath('requires_otp_verification', true)
            ->assertJsonPath('registration_method', 'email');

        $this->assertDatabaseHas('otps', [
            'email' => 'pending@example.com',
            'type' => OtpType::EMAIL_OTP->value,
            'used' => false,
        ]);
    }

    public function test_verify_email_otp_marks_account_verified_and_returns_tokens(): void
    {
        $user = User::create([
            'first_name' => 'Verify',
            'last_name' => 'Email',
            'email' => 'verify@example.com',
            'registration_method' => 'email',
            'password' => 'Password@123',
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        Otp::create([
            'email' => 'verify@example.com',
            'otp' => '123456',
            'type' => OtpType::EMAIL_OTP->value,
            'expires_at' => now()->addMinutes(10),
            'used' => false,
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'registration_method' => 'email',
            'email' => 'verify@example.com',
            'otp' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('is_account_verified', true)
            ->assertJsonPath('requires_otp_verification', false)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'user',
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('otps', [
            'email' => 'verify@example.com',
            'otp' => '123456',
            'used' => true,
        ]);
    }

    public function test_verify_phone_otp_marks_account_verified_and_returns_tokens(): void
    {
        $user = User::create([
            'first_name' => 'Verify',
            'last_name' => 'Phone',
            'phone' => '+491111111111',
            'registration_method' => 'phone',
            'password' => 'Password@123',
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        Otp::create([
            'phone' => '+491111111111',
            'otp' => '654321',
            'type' => OtpType::SMS_OTP->value,
            'expires_at' => now()->addMinutes(10),
            'used' => false,
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491111111111',
            'otp' => '654321',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('registration_method', 'phone')
            ->assertJsonPath('is_account_verified', true)
            ->assertJsonPath('requires_otp_verification', false)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'user',
            ]);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_refresh_is_blocked_for_unverified_customer_accounts(): void
    {
        $user = User::create([
            'first_name' => 'Refresh',
            'last_name' => 'Pending',
            'email' => 'refresh-pending@example.com',
            'registration_method' => 'email',
            'password' => 'Password@123',
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'plain-refresh-token' . config('app.key')),
            'device' => 'PHPUnit',
            'ip' => '127.0.0.1',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'plain-refresh-token',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonMissingPath('access_token')
            ->assertJsonPath('requires_otp_verification', true);

        $this->assertNotNull($refreshToken->fresh());
    }

    public function test_verified_customer_can_access_protected_route(): void
    {
        $user = User::create([
            'first_name' => 'Verified',
            'last_name' => 'Customer',
            'email' => 'verified@example.com',
            'registration_method' => 'email',
            'password' => 'Password@123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/profile', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
    }

    public function test_unverified_customer_is_blocked_from_protected_route_even_with_existing_token(): void
    {
        $user = User::create([
            'first_name' => 'Blocked',
            'last_name' => 'Customer',
            'email' => 'blocked@example.com',
            'registration_method' => 'email',
            'password' => 'Password@123',
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/profile', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('requires_otp_verification', true);
    }

    public function test_provider_login_is_not_blocked_by_customer_verification_gate(): void
    {
        $user = User::create([
            'first_name' => 'Verified',
            'last_name' => 'Provider',
            'email' => 'provider@example.com',
            'registration_method' => 'email',
            'password' => 'Password@123',
            'is_active' => true,
        ]);
        $user->assignRole('provider');

        $response = $this->postJson('/api/auth/login', [
            'registration_method' => 'email',
            'email' => 'provider@example.com',
            'password' => 'Password@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'user',
            ])
            ->assertJsonPath('requires_otp_verification', false);
    }
}