<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AUTH-07 — guards the phone number format rule on every path that WRITES
 * users.phone: PATCH-style profile update, phone re-verification, and register.
 *
 * The regression these tests exist to prevent is not only "junk gets stored".
 * It is also the opposite mistake: a stricter-looking rule applied to the raw
 * string would reject the human-formatted numbers this database actually holds
 * ("+971-50-101-0101"), locking every existing user out of their own profile
 * form. Both directions are pinned below.
 */
class PhoneNumberValidationTest extends TestCase
{
    use RefreshDatabase;

    /** Numbers that are the same real number written in different ways. */
    public static function acceptedFormats(): array
    {
        return [
            'bare E.164' => ['+971501010101'],
            'human separators (the form already in the database)' => ['+971-50-101-0101'],
            'international access prefix' => ['00971501010101'],
            'international without the plus' => ['971501010101'],
            'spaces and brackets' => ['+49 30 1234567'],
        ];
    }

    public static function rejectedFormats(): array
    {
        return [
            'not a number at all' => ['not-a-phone'],
            'markup' => ['<script>alert(1)</script>'],
            'too short for E.164' => ['+12345'],
            'country code starting with zero' => ['+0123456789'],
            'more than 15 digits' => ['+12345678901234567'],
            'national format with no country code to resolve it' => ['0501010101'],
            'empty-ish' => ['   '],
        ];
    }

    protected function actingAsCustomer(?string $phone = null): User
    {
        $user = User::create([
            'first_name' => 'Ava',
            'last_name' => 'Tester',
            'email' => 'phone-' . uniqid() . '@example.com',
            'phone' => $phone,
            'password' => 'Password@123',
            'registration_method' => 'email',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    protected function updatePhone(string $phone)
    {
        return $this->postJson('/api/profile', ['phone' => $phone]);
    }

    /**
     * @dataProvider acceptedFormats
     */
    public function test_a_real_international_number_is_accepted(string $phone): void
    {
        $this->actingAsCustomer();

        $this->updatePhone($phone)->assertOk()->assertJsonPath('data.phone', $phone);
    }

    /**
     * @dataProvider rejectedFormats
     */
    public function test_a_number_that_is_not_reachable_is_rejected(string $phone): void
    {
        $user = $this->actingAsCustomer('+971501010101');

        $this->updatePhone($phone)->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertSame('+971501010101', $user->fresh()->phone);
    }

    /**
     * A rejected update must not be a half-update: the fields alongside the bad
     * phone are part of the same request and go nowhere either.
     */
    public function test_a_rejected_phone_rolls_back_the_whole_profile_update(): void
    {
        $user = $this->actingAsCustomer('+971501010101');

        $this->postJson('/api/profile', [
            'city' => 'Berlin',
            'phone' => 'not-a-phone',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertNull($user->fresh()->city);
    }

    /**
     * The other endpoint that writes users.phone. A format rule enforced on one
     * of two write paths is not a format rule.
     */
    public function test_the_phone_verification_endpoint_rejects_a_malformed_number(): void
    {
        $user = $this->actingAsCustomer('+971501010101');

        $this->postJson('/api/profile/phone/send-otp', ['phone' => 'not-a-phone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->assertSame('+971501010101', $user->fresh()->phone);
    }

    public function test_registering_with_a_malformed_phone_is_rejected(): void
    {
        Queue::fake();
        Role::findOrCreate('customer', 'web');

        $this->postJson('/api/auth/register', [
            'first_name' => 'Phone',
            'last_name' => 'Customer',
            'registration_method' => 'phone',
            'phone' => 'not-a-phone',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registering_with_a_valid_phone_still_works(): void
    {
        Queue::fake();
        Role::findOrCreate('customer', 'web');

        $this->postJson('/api/auth/register', [
            'first_name' => 'Phone',
            'last_name' => 'Customer',
            'registration_method' => 'phone',
            'phone' => '+491234567890',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['phone' => '+491234567890']);
    }

    /**
     * The canonical form is what makes two spellings of one number comparable.
     * Pinned here because the uniqueness check does NOT yet use it — see the
     * note in docs/fixes/AUTH-07_phone_number_format.md.
     */
    public function test_equivalent_spellings_share_one_canonical_form(): void
    {
        foreach (['+971-50-101-0101', '00971501010101', '971501010101', '+971 50 101 0101'] as $spelling) {
            $this->assertSame('+971501010101', PhoneNumber::normalize($spelling), $spelling);
        }
    }
}
