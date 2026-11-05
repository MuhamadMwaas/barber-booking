<?php
namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Look up - Landshut-Rupprechtstraße',
                'adress' => 'Rupprechtstraße 33 -84034 Landshut',
                'phone' => '+49 871 6877271',
                'email' => 'info@lookupfriseur.com',
                'latitude' => 48.5417032,
                'longitude' => 12.1327871,
                'is_active' => true,
            ]
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(['name' => $branch['name']], $branch);
        }

        $this->command->info('Branches seeded successfully');
    }
}
