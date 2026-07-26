<?php

namespace Tests\Feature;

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use App\Jobs\SendOtpDeliveryJob;
use App\Models\Otp;
use App\Models\PasswordResetGrant;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('customer', 'web');

        Mail::fake();
        Http::fake();
        // Delivery itself is covered by AuthVerificationFlowTest; here we only
        // care that the right job is queued for the right channel.
        Queue::fake();
    }

    private function phoneUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'first_name' => 'Sms',
            'last_name' => 'Customer',
            'phone' => '+491701111111',
            'registration_method' => 'phone',
            'password' => Hash::make('OldPassword@123'),
            'is_active' => true,
        ], $overrides));

        $user->assignRole('customer');

        return $user;
    }

    private function emailUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'first_name' => 'Mail',
            'last_name' => 'Customer',
            'email' => 'mail-customer@example.com',
            'registration_method' => 'email',
            'password' => Hash::make('OldPassword@123'),
            'is_active' => true,
        ], $overrides));

        $user->assignRole('customer');

        return $user;
    }

    // ---------------------------------------------------------------- step 1

    public function test_forgot_password_sends_sms_otp_for_a_phone_account(): void
    {
        $user = $this->phoneUser();

        $response = $this->postJson('/api/auth/forgot-password', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('registration_method', 'phone')
            ->assertJsonStructure(['masked_destination', 'expires_in', 'resend_after']);

        $this->assertStringNotContainsString('1701111111', $response->json('masked_destination'));

        $this->assertDatabaseHas('otps', [
            'phone' => '+491701111111',
            'email' => null,
            'type' => OtpType::SMS_OTP->value,
            'purpose' => OtpPurpose::PASSWORD_RESET->value,
            'used' => false,
        ]);

        Queue::assertPushed(
            SendOtpDeliveryJob::class,
            fn (SendOtpDeliveryJob $job) => $job->userId === $user->id && $job->type === OtpType::SMS_OTP
        );
    }

    public function test_forgot_password_sends_email_otp_for_an_email_account(): void
    {
        $this->emailUser();

        $this->postJson('/api/auth/forgot-password', [
            'registration_method' => 'email',
            'email' => 'mail-customer@example.com',
        ])->assertOk()->assertJsonPath('registration_method', 'email');

        $this->assertDatabaseHas('otps', [
            'email' => 'mail-customer@example.com',
            'type' => OtpType::EMAIL_OTP->value,
            'purpose' => OtpPurpose::PASSWORD_RESET->value,
        ]);
    }

    public function test_forgot_password_returns_404_for_an_unknown_destination(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'registration_method' => 'phone',
            'phone' => '+490000000000',
        ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'USER_NOT_FOUND');

        $this->assertDatabaseCount('otps', 0);
    }

    public function test_forgot_password_is_rejected_for_a_disabled_account(): void
    {
        $this->phoneUser(['is_active' => false]);

        $this->postJson('/api/auth/forgot-password', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
        ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCOUNT_DISABLED');

        $this->assertDatabaseCount('otps', 0);
    }

    public function test_second_request_within_the_cooldown_is_throttled(): void
    {
        $this->phoneUser();

        $payload = ['registration_method' => 'phone', 'phone' => '+491701111111'];

        $this->postJson('/api/auth/forgot-password', $payload)->assertOk();

        $this->postJson('/api/auth/forgot-password', $payload)
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'OTP_COOLDOWN');

        $this->assertDatabaseCount('otps', 1);
    }

    // ---------------------------------------------------------------- step 2

    public function test_verify_otp_returns_a_reset_token(): void
    {
        $user = $this->phoneUser();
        $otp = $this->seedResetOtp($user, OtpType::SMS_OTP, '111222');

        $response = $this->postJson('/api/auth/password/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'otp' => $otp,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['reset_token', 'expires_at']);

        $this->assertDatabaseHas('password_reset_grants', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $response->json('reset_token')),
            'used_at' => null,
        ]);

        // The code is single-use even before the password is changed.
        $this->assertDatabaseHas('otps', ['otp' => '111222', 'used' => true]);
    }

    public function test_an_account_verification_code_cannot_be_used_to_reset_the_password(): void
    {
        $this->phoneUser();

        Otp::create([
            'phone' => '+491701111111',
            'otp' => '999888',
            'type' => OtpType::SMS_OTP->value,
            'purpose' => OtpPurpose::ACCOUNT_VERIFICATION->value,
            'expires_at' => now()->addMinutes(10),
            'used' => false,
        ]);

        $this->postJson('/api/auth/password/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'otp' => '999888',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_OTP');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $user = $this->phoneUser();
        $this->seedResetOtp($user, OtpType::SMS_OTP, '111222', expiresAt: now()->subMinute());

        $this->postJson('/api/auth/password/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'otp' => '111222',
        ])->assertStatus(422);
    }

    public function test_the_code_is_burned_after_too_many_wrong_attempts(): void
    {
        $user = $this->phoneUser();
        $this->seedResetOtp($user, OtpType::SMS_OTP, '111222');

        $max = (int) config('otp.max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/api/auth/password/verify-otp', [
                'registration_method' => 'phone',
                'phone' => '+491701111111',
                'otp' => '000000',
            ])->assertStatus(422);
        }

        // Even the correct code no longer works once the budget is spent.
        $this->postJson('/api/auth/password/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'otp' => '111222',
        ])->assertStatus(422);

        $this->assertDatabaseHas('otps', ['otp' => '111222', 'used' => true]);
    }

    // ---------------------------------------------------------------- step 3

    public function test_reset_with_a_grant_token_changes_the_password_and_kills_every_session(): void
    {
        $user = $this->phoneUser();
        $user->createToken('mobile');
        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'refresh-token-plain'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $otp = $this->seedResetOtp($user, OtpType::SMS_OTP, '111222');

        $token = $this->postJson('/api/auth/password/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'otp' => $otp,
        ])->json('reset_token');

        $this->postJson('/api/auth/reset-password', [
            'reset_token' => $token,
            'password' => 'BrandNew@123',
            'password_confirmation' => 'BrandNew@123',
        ])->assertOk()->assertJsonPath('success', true);

        $user->refresh();

        $this->assertTrue(Hash::check('BrandNew@123', $user->password));
        $this->assertNotNull($user->phone_verified_at, 'A successful SMS reset proves ownership of the number.');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, $user->refreshTokens()->where('revoked', false)->count());

        // The grant is spent.
        $this->assertNotNull(PasswordResetGrant::query()->where('user_id', $user->id)->first()->used_at);
    }

    public function test_a_grant_token_cannot_be_replayed(): void
    {
        $user = $this->phoneUser();
        $otp = $this->seedResetOtp($user, OtpType::SMS_OTP, '111222');

        $token = $this->postJson('/api/auth/password/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'otp' => $otp,
        ])->json('reset_token');

        $payload = [
            'reset_token' => $token,
            'password' => 'BrandNew@123',
            'password_confirmation' => 'BrandNew@123',
        ];

        $this->postJson('/api/auth/reset-password', $payload)->assertOk();

        $this->postJson('/api/auth/reset-password', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_RESET_TOKEN');
    }

    public function test_legacy_single_request_reset_still_works(): void
    {
        $user = $this->emailUser();
        $this->seedResetOtp($user, OtpType::EMAIL_OTP, '445566');

        $this->postJson('/api/auth/reset-password', [
            'registration_method' => 'email',
            'email' => 'mail-customer@example.com',
            'otp' => '445566',
            'password' => 'BrandNew@123',
            'password_confirmation' => 'BrandNew@123',
        ])->assertOk();

        $this->assertTrue(Hash::check('BrandNew@123', $user->refresh()->password));
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $user = $this->emailUser();
        $this->seedResetOtp($user, OtpType::EMAIL_OTP, '445566');

        $this->postJson('/api/auth/reset-password', [
            'registration_method' => 'email',
            'email' => 'mail-customer@example.com',
            'otp' => '445566',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('OldPassword@123', $user->refresh()->password));
    }

    public function test_the_user_can_log_in_with_the_new_password_afterwards(): void
    {
        $user = $this->phoneUser();
        $otp = $this->seedResetOtp($user, OtpType::SMS_OTP, '111222');

        $token = $this->postJson('/api/auth/password/verify-otp', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'otp' => $otp,
        ])->json('reset_token');

        $this->postJson('/api/auth/reset-password', [
            'reset_token' => $token,
            'password' => 'BrandNew@123',
            'password_confirmation' => 'BrandNew@123',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'password' => 'BrandNew@123',
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);

        $this->postJson('/api/auth/login', [
            'registration_method' => 'phone',
            'phone' => '+491701111111',
            'password' => 'OldPassword@123',
        ])->assertStatus(401);
    }

    private function seedResetOtp(User $user, OtpType $type, string $code, $expiresAt = null): string
    {
        Otp::create([
            'email' => $type === OtpType::EMAIL_OTP ? $user->email : null,
            'phone' => $type === OtpType::SMS_OTP ? $user->phone : null,
            'otp' => $code,
            'type' => $type->value,
            'purpose' => OtpPurpose::PASSWORD_RESET->value,
            'expires_at' => $expiresAt ?: now()->addMinutes(10),
            'used' => false,
        ]);

        return $code;
    }
}
