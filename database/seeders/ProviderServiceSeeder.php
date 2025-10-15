<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProviderServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = User::role('provider')->get();
        $services = Service::all();

        if ($providers->isEmpty() || $services->isEmpty()) {
            $this->command->warn('Skipping provider-service seeding  Missing providers or services ');
            return;
        }


        $providerSpecializations = [];

        foreach ($providers as $provider) {

            $numberOfServices = rand(3, 5);
            $providerServices = $services->random($numberOfServices);

            foreach ($providerServices as $service) {

                $isActive = rand(1, 10) <= 9;


                $hasCustomPrice = false;
                $customPrice = $hasCustomPrice ? $service->price + rand(-50, 100) : null;


                $hasCustomDuration = rand(1, 10) <= 2;
                $customDuration = $hasCustomDuration ? max(15, $service->duration_minutes + rand(-15, 30)) : null;


                $notes = null;
                if (rand(1, 10) <= 3) {
                    $notesOptions = [
                        'Special expertise in this service',
                        'Premium service offering',
                        'Newly trained in this technique',
                        'Exclusive service for this provider',
                        null
                    ];
                    $notes = $notesOptions[array_rand($notesOptions)];
                }

                DB::table('provider_service')->insert([
                    'service_id' => $service->id,
                    'provider_id' => $provider->id,
                    'is_active' => $isActive,
                    'custom_price' => $customPrice,
                    'custom_duration' => $customDuration,
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }


        foreach ($services as $service) {
            $existingLink = DB::table('provider_service')
                ->where('service_id', $service->id)
                ->where('is_active', true)
                ->exists();

            if (!$existingLink) {

                $provider = $providers->random();

                DB::table('provider_service')->insert([
                    'service_id' => $service->id,
                    'provider_id' => $provider->id,
                    'is_active' => true,
                    'custom_price' => null,
                    'custom_duration' => null,
                    'notes' => 'Primary provider for this service',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Provider-service relationships seeded successfully');
    }
}
