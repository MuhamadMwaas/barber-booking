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

                // updateOrInsert, not insert: `provider_service` now carries a
                // UNIQUE (provider_id, service_id) constraint (MON-03 / DB-01),
                // because two rows for one pair mean an UNDEFINED price — both
                // getEffectivePrice() and getProviderServicePricing() use
                // ->first(), so the app could quote 30 and charge 45. A plain
                // insert also made this seeder non-rerunnable.
                DB::table('provider_service')->updateOrInsert(
                    [
                        'service_id' => $service->id,
                        'provider_id' => $provider->id,
                    ],
                    [
                        'is_active' => $isActive,
                        'custom_price' => $customPrice,
                        'custom_duration' => $customDuration,
                        'notes' => $notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }


        // Guarantee every service has at least one ACTIVE provider.
        //
        // This loop used to pick a provider at random and plain-insert. The
        // random pick could easily land on a provider that the loop above had
        // already linked to this service with is_active = false — producing a
        // DUPLICATE (provider_id, service_id) pair on a **fresh** run, not just
        // a re-run. With the pair now unique that is a hard failure, so the
        // existing row is reactivated instead, and a provider without a row is
        // preferred when one is available.
        foreach ($services as $service) {
            $existingLink = DB::table('provider_service')
                ->where('service_id', $service->id)
                ->where('is_active', true)
                ->exists();

            if ($existingLink) {
                continue;
            }

            $linkedProviderIds = DB::table('provider_service')
                ->where('service_id', $service->id)
                ->pluck('provider_id')
                ->all();

            $unlinked = $providers->whereNotIn('id', $linkedProviderIds);
            $provider = $unlinked->isNotEmpty() ? $unlinked->random() : $providers->random();

            DB::table('provider_service')->updateOrInsert(
                [
                    'service_id' => $service->id,
                    'provider_id' => $provider->id,
                ],
                [
                    'is_active' => true,
                    'custom_price' => null,
                    'custom_duration' => null,
                    'notes' => 'Primary provider for this service',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Provider-service relationships seeded successfully');
    }
}
