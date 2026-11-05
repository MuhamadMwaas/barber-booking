<?php

namespace Tests\Feature;

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Models\User;
use App\Services\GuestBookingClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_delete_account_and_related_personal_data(): void
    {
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $customer = User::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '123456789',
            'password' => 'Password@123',
            'registration_method' => 'email',
            'email_verified_at' => now(),
        ]);
        $customer->assignRole($customerRole);

        $provider = User::create([
            'first_name' => 'Test',
            'last_name' => 'Provider',
            'email' => 'provider@example.com',
            'password' => 'Password@123',
            'registration_method' => 'email',
        ]);

        $appointmentId = DB::table('appointments')->insertGetId([
            'number' => 'APT-DELETE-001',
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'customer_name' => 'Test Customer',
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'appointment_date' => now()->addDay(),
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'duration_minutes' => 60,
            'subtotal' => 10,
            'tax_amount' => 1.9,
            'total_amount' => 11.9,
            'status' => AppointmentStatus::PENDING->value,
            'payment_method' => 'cash',
            'payment_status' => 0,
            'created_status' => 1,
            'notes' => 'private notes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')->insert([
            'appointment_id' => $appointmentId,
            'customer_id' => $customer->id,
            'invoice_number' => null,
            'subtotal' => 10,
            'tax_amount' => 1.9,
            'tax_rate' => 19,
            'total_amount' => 11.9,
            'status' => InvoiceStatus::DRAFT->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('refresh_tokens')->insert([
            'user_id' => $customer->id,
            'token_hash' => 'hash',
            'expires_at' => now()->addDay(),
            'device' => 'android',
            'ip' => '127.0.0.1',
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_devices')->insert([
            'user_id' => $customer->id,
            'device_id' => 'device-1',
            'device_token' => 'token-1',
            'platform' => 'android',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('otps')->insert([
            'email' => $customer->email,
            'phone' => $customer->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
            'type' => 0,
            'used' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $customer->email,
            'token' => 'reset-token',
            'created_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'session-delete-account',
            'user_id' => $customer->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $customer->createToken('mobile');

        Sanctum::actingAs($customer);

        $response = $this->deleteJson('/api/profile', [
            'current_password' => 'Password@123',
            'confirmation' => true,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully',
            ]);

        // Gone from the table, not merely soft-deleted.
        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertNull(User::withTrashed()->find($customer->id));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'customer_id' => null,
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '123456789',
            'status' => AppointmentStatus::USER_CANCELLED->value,
            'created_status' => 0,
        ]);
        $this->assertNotNull(DB::table('appointments')->where('id', $appointmentId)->value('customer_account_deleted_at'));
        $this->assertDatabaseHas('invoices', [
            'appointment_id' => $appointmentId,
            'customer_id' => null,
        ]);
        $this->assertDatabaseMissing('refresh_tokens', ['user_id' => $customer->id]);
        $this->assertDatabaseMissing('user_devices', ['user_id' => $customer->id]);
        $this->assertDatabaseMissing('otps', ['email' => $customer->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $customer->email]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $customer->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $customer->id,
        ]);
        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => User::class,
            'model_id' => $customer->id,
        ]);
    }

    public function test_empty_booking_contact_is_filled_from_the_account_and_existing_values_are_kept(): void
    {
        $customer = $this->customer(['email' => 'ali@example.com', 'phone' => '+4917612345678']);
        $provider = $this->provider();

        // Past, completed, contact never copied onto the row.
        $empty = $this->appointment($customer, $provider, [
            'number' => 'APT-EMPTY',
            'start_time' => now()->subWeek(),
            'status' => AppointmentStatus::COMPLETED->value,
            'customer_name' => null,
            'customer_email' => '',
            'customer_phone' => null,
        ]);
        // Staff typed another number for this visit: that is what was recorded.
        $typed = $this->appointment($customer, $provider, [
            'number' => 'APT-TYPED',
            'start_time' => now()->subDays(3),
            'status' => AppointmentStatus::COMPLETED->value,
            'customer_name' => 'Ali (walk-in)',
            'customer_email' => null,
            'customer_phone' => '+4915299999999',
        ]);

        $paidInvoice = DB::table('invoices')->insertGetId([
            'appointment_id' => $empty,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-000001',
            'subtotal' => 10,
            'tax_amount' => 1.9,
            'tax_rate' => 19,
            'total_amount' => 11.9,
            'status' => InvoiceStatus::PAID->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/profile', ['current_password' => 'Password@123'])->assertOk();

        $this->assertDatabaseHas('appointments', [
            'id' => $empty,
            'customer_id' => null,
            'customer_name' => 'Ali Hasan',
            'customer_email' => 'ali@example.com',
            'customer_phone' => '+4917612345678',
            // Past bookings are history, not cancelled.
            'status' => AppointmentStatus::COMPLETED->value,
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $typed,
            'customer_name' => 'Ali (walk-in)',
            'customer_email' => 'ali@example.com',
            'customer_phone' => '+4915299999999',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $paidInvoice,
            'customer_id' => null,
            'invoice_number' => 'INV-2026-000001',
            'total_amount' => 11.9,
        ]);
    }

    public function test_phone_registered_account_without_email_can_be_deleted(): void
    {
        $customer = $this->customer(['email' => null, 'phone' => '+4917611112222', 'registration_method' => 'phone']);
        $appointment = $this->appointment($customer, $this->provider(), [
            'customer_name' => null,
            'customer_email' => null,
            'customer_phone' => null,
        ]);

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/profile', ['current_password' => 'Password@123'])->assertOk();

        $this->assertNull(User::withTrashed()->find($customer->id));
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment,
            'customer_name' => 'Ali Hasan',
            'customer_email' => null,
            'customer_phone' => '+4917611112222',
        ]);
    }

    public function test_bookings_of_a_deleted_account_are_not_claimed_by_the_next_owner_of_the_number(): void
    {
        $customer = $this->customer(['phone' => '+4917633334444']);
        $appointment = $this->appointment($customer, $this->provider());

        Sanctum::actingAs($customer);
        $this->deleteJson('/api/profile', ['current_password' => 'Password@123'])->assertOk();

        $newOwner = $this->customer([
            'email' => 'someone-else@example.com',
            'phone' => '+4917633334444',
            'phone_verified_at' => now(),
        ]);

        $this->assertSame(0, app(GuestBookingClaimService::class)->claimFor($newOwner));
        $this->assertDatabaseHas('appointments', ['id' => $appointment, 'customer_id' => null]);
    }

    public function test_manager_cannot_delete_account_via_customer_endpoint(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = $this->customer(['email' => 'manager@example.com']);
        $manager->syncRoles(['manager']);

        Sanctum::actingAs($manager);

        $this->deleteJson('/api/profile', ['current_password' => 'Password@123'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'deleted_at' => null]);
    }

    private function customer(array $attributes = []): User
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $user = User::create(array_merge([
            'first_name' => 'Ali',
            'last_name' => 'Hasan',
            'email' => 'ali-' . uniqid() . '@example.com',
            'phone' => null,
            'password' => 'Password@123',
            'registration_method' => 'email',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ], $attributes));
        $user->assignRole('customer');

        return $user;
    }

    private function provider(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'Provider',
            'email' => 'provider-' . uniqid() . '@example.com',
            'password' => 'Password@123',
            'registration_method' => 'email',
        ]);
    }

    private function appointment(User $customer, User $provider, array $attributes = []): int
    {
        $start = $attributes['start_time'] ?? now()->subDay();
        unset($attributes['start_time']);

        return DB::table('appointments')->insertGetId(array_merge([
            'number' => 'APT-' . uniqid(),
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
            'customer_name' => $customer->full_name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'appointment_date' => $start,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'duration_minutes' => 60,
            'subtotal' => 10,
            'tax_amount' => 1.9,
            'total_amount' => 11.9,
            'status' => AppointmentStatus::COMPLETED->value,
            'payment_method' => 'cash',
            'payment_status' => 0,
            'created_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    public function test_provider_cannot_delete_account_via_customer_endpoint(): void
    {
        $providerRole = Role::firstOrCreate(['name' => 'provider', 'guard_name' => 'web']);

        $provider = User::create([
            'first_name' => 'Test',
            'last_name' => 'Provider',
            'email' => 'provider-only@example.com',
            'password' => 'Password@123',
            'registration_method' => 'email',
        ]);
        $provider->assignRole($providerRole);

        Sanctum::actingAs($provider);

        $this->deleteJson('/api/profile', [
            'current_password' => 'Password@123',
            'confirmation' => true,
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This endpoint is available for customer accounts only.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $provider->id]);
    }
}
