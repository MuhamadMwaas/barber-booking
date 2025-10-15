<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalonSetting;
use App\Models\Branch;

class SalonSettingSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        $globalSettings = [
            [
                'key' => 'tax_rate',
                'value' => '5',
                'type' => 'decimal',
                'description' => 'VAT tax rate percentage',
                'setting_group' => 'payment',
            ],
            [
                'key' => 'currency',
                'value' => 'USD',
                'type' => 'string',
                'description' => 'Default currency',
                'setting_group' => 'payment',
            ],
            [
                'key' => 'cancellation_hours',
                'value' => '24',
                'type' => 'integer',
                'description' => 'Minimum hours before appointment for cancellation',
                'setting_group' => 'booking',
            ],
            [
                'key' => 'max_advance_booking_days',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Maximum days in advance for booking',
                'setting_group' => 'booking',
            ],
            [
                'key' => 'booking_buffer_minutes',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Buffer time between appointments',
                'setting_group' => 'booking',
            ],
            [
                'key' => 'auto_confirm_appointments',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Automatically confirm appointments',
                'setting_group' => 'booking',
            ],
            [
                'key' => 'enable_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable email/SMS notifications',
                'setting_group' => 'notifications',
            ],
            [
                'key' => 'reminder_hours_before',
                'value' => '24',
                'type' => 'integer',
                'description' => 'Send reminder X hours before appointment',
                'setting_group' => 'notifications',
            ],
            [
                'key' => 'loyalty_points_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable loyalty points system',
                'setting_group' => 'loyalty',
            ],
            [
                'key' => 'points_per_aed',
                'value' => '1',
                'type' => 'integer',
                'description' => 'Points earned per AED spent',
                'setting_group' => 'loyalty',
            ],
            [
                'key' => 'enable_online_payment',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable online payment options',
                'setting_group' => 'payment',
            ],
            [
                'key' => 'payment_methods',
                'value' => '["cash", "card", "online"]',
                'type' => 'json',
                'description' => 'Available payment methods',
                'setting_group' => 'payment',
            ],
        ];

        foreach ($branches as $branch) {
            foreach ($globalSettings as $setting) {
                SalonSetting::create(array_merge($setting, ['branch_id' => $branch->id]));
            }

            SalonSetting::create([
                'key' => 'branch_phone',
                'value' => $branch->phone,
                'type' => 'string',
                'description' => 'Branch contact phone',
                'branch_id' => $branch->id,
                'setting_group' => 'contact',
            ]);

            SalonSetting::create([
                'key' => 'branch_email',
                'value' => $branch->email,
                'type' => 'string',
                'description' => 'Branch contact email',
                'branch_id' => $branch->id,
                'setting_group' => 'contact',
            ]);

        }

        $this->command->info('Salon settings seeded successfully');
    }
}
