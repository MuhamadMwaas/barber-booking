<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::where('is_active', true)->get();

        $admin_role=Role::where('name','admin')->first();
        $admin=User::create([
            'first_name' => 'Ahmed',
            'last_name' => 'Al Maktoum',
            'email' => 'admin@elitebeauty.ae',
            'phone' => '+971-50-123-4567',
            'password' => Hash::make('password'),
            'user_type' => $admin_role->id,
            'address' => 'Dubai, UAE',
            'city' => 'Dubai',
            'locale' => 'en',
            'is_active' => true,
            'email_verified_at' => now(),
            'branch_id' => $branches->first()->id,
        ]);
        $admin->assignRole('admin');


        $admin_manager=Role::where('name','manager')->first();
        $manager = User::create([
            'first_name' => 'Fatima',
            'last_name' => 'Hassan',
            'email' => 'manager@elitebeauty.ae',
            'phone' => '+971-50-234-5678',
            'password' => Hash::make('password'),
            'user_type' => $admin_manager->id,
            'address' => 'Dubai Marina',
            'city' => 'Dubai',
            'locale' => 'ar',
            'is_active' => true,
            'email_verified_at' => now(),
            'branch_id' => $branches->first()->id,
        ]);
         $manager->assignRole('manager');
        // Create Providers (Stylists/Beauticians)
        $providers = [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah.johnson@elitebeauty.ae',
                'phone' => '+971-55-111-2222',
                'specialization' => 'Hair Specialist',
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Rodriguez',
                'email' => 'maria.rodriguez@elitebeauty.ae',
                'phone' => '+971-55-222-3333',
                'specialization' => 'Nail Artist',
            ],
            [
                'first_name' => 'Aisha',
                'last_name' => 'Al Zaabi',
                'email' => 'aisha.alzaabi@elitebeauty.ae',
                'phone' => '+971-55-333-4444',
                'specialization' => 'Makeup Artist',
            ],
            [
                'first_name' => 'Jasmine',
                'last_name' => 'Lee',
                'email' => 'jasmine.lee@elitebeauty.ae',
                'phone' => '+971-55-444-5555',
                'specialization' => 'Skin Care Specialist',
            ],
            [
                'first_name' => 'Noor',
                'last_name' => 'Ahmed',
                'email' => 'noor.ahmed@elitebeauty.ae',
                'phone' => '+971-55-555-6666',
                'specialization' => 'Hair Colorist',
            ],
            [
                'first_name' => 'Elena',
                'last_name' => 'Petrov',
                'email' => 'elena.petrov@elitebeauty.ae',
                'phone' => '+971-55-666-7777',
                'specialization' => 'Massage Therapist',
            ],
            [
                'first_name' => 'Layla',
                'last_name' => 'Ibrahim',
                'email' => 'layla.ibrahim@elitebeauty.ae',
                'phone' => '+971-55-777-8888',
                'specialization' => 'Henna Artist',
            ],
            [
                'first_name' => 'Sophie',
                'last_name' => 'Martin',
                'email' => 'sophie.martin@elitebeauty.ae',
                'phone' => '+971-55-888-9999',
                'specialization' => 'Bridal Specialist',
            ],
        ];

        $providers_role=Role::where('name','provider')->first();
        foreach ($providers as $index => $provider) {
             $providerUser = User::create([
                'first_name' => $provider['first_name'],
                'last_name' => $provider['last_name'],
                'email' => $provider['email'],
                'phone' => $provider['phone'],
                'password' => Hash::make('password'),
                'user_type' => $providers_role->id,
                'address' => 'Dubai, UAE',
                'city' => 'Dubai',
                'notes' => 'Specialization: ' . $provider['specialization'],
                'locale' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
                'branch_id' => $branches->random()->id,
            ]);
               $providerUser->assignRole('provider');
        }

        // Create Customers
        $customers = [
            [
                'first_name' => 'Hala',
                'last_name' => 'Al Hashimi',
                'email' => 'hala.alhashimi@gmail.com',
                'phone' => '+971-50-111-1111',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Maryam',
                'last_name' => 'Khalid',
                'email' => 'maryam.khalid@gmail.com',
                'phone' => '+971-50-222-2222',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Emma',
                'last_name' => 'Wilson',
                'email' => 'emma.wilson@gmail.com',
                'phone' => '+971-50-333-3333',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Reem',
                'last_name' => 'Mohammed',
                'email' => 'reem.mohammed@gmail.com',
                'phone' => '+971-50-444-4444',
                'city' => 'Abu Dhabi',
            ],
            [
                'first_name' => 'Jennifer',
                'last_name' => 'Taylor',
                'email' => 'jennifer.taylor@gmail.com',
                'phone' => '+971-50-555-5555',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Noura',
                'last_name' => 'Salem',
                'email' => 'noura.salem@gmail.com',
                'phone' => '+971-50-666-6666',
                'city' => 'Sharjah',
            ],
            [
                'first_name' => 'Sophia',
                'last_name' => 'Brown',
                'email' => 'sophia.brown@gmail.com',
                'phone' => '+971-50-777-7777',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Ayesha',
                'last_name' => 'Khan',
                'email' => 'ayesha.khan@gmail.com',
                'phone' => '+971-50-888-8888',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Olivia',
                'last_name' => 'Davis',
                'email' => 'olivia.davis@gmail.com',
                'phone' => '+971-50-999-9999',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Salma',
                'last_name' => 'Ali',
                'email' => 'salma.ali@gmail.com',
                'phone' => '+971-50-101-0101',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Isabella',
                'last_name' => 'Garcia',
                'email' => 'isabella.garcia@gmail.com',
                'phone' => '+971-50-202-0202',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Amira',
                'last_name' => 'Youssef',
                'email' => 'amira.youssef@gmail.com',
                'phone' => '+971-50-303-0303',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Charlotte',
                'last_name' => 'Miller',
                'email' => 'charlotte.miller@gmail.com',
                'phone' => '+971-50-404-0404',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Lina',
                'last_name' => 'Hassan',
                'email' => 'lina.hassan@gmail.com',
                'phone' => '+971-50-505-0505',
                'city' => 'Dubai',
            ],
            [
                'first_name' => 'Ava',
                'last_name' => 'Anderson',
                'email' => 'ava.anderson@gmail.com',
                'phone' => '+971-50-606-0606',
                'city' => 'Dubai',
            ],
        ];

        $admin_customer=Role::where('name','customer')->first();
        foreach ($customers as $customer) {
             $customerUser = User::create([
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'password' => Hash::make('password'),
                'user_type' => $admin_customer->id,
                'address' => $customer['city'] . ', UAE',
                'city' => $customer['city'],
                'locale' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
                'branch_id' => $branches->random()->id,
            ]);
             $customerUser->assignRole('customer');
        }

        $this->command->info(' Users seeded successfully');
        $this->command->info('2 Admins/Managers');
        $this->command->info('8 Providers');
        $this->command->info('15 Customers');
    }
}
