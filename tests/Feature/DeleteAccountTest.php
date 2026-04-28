<?php

namespace Tests\Feature;

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Models\User;
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
        ]);
        $customer->assignRole($customerRole);

        $provider = User::create([
            'first_name' => 'Test',
            'last_name' => 'Provider',
            'email' => 'provider@example.com',
            'password' => 'Password@123',
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

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'customer_id' => null,
            'customer_name' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'notes' => null,
            'status' => AppointmentStatus::USER_CANCELLED->value,
            'created_status' => 0,
        ]);
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

    public function test_provider_cannot_delete_account_via_customer_endpoint(): void
    {
        $providerRole = Role::firstOrCreate(['name' => 'provider', 'guard_name' => 'web']);

        $provider = User::create([
            'first_name' => 'Test',
            'last_name' => 'Provider',
            'email' => 'provider-only@example.com',
            'password' => 'Password@123',
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
