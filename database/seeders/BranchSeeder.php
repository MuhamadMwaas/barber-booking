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
                'name' => 'Main Branch - Downtown',
                'adress' => '123 Main Street, Downtown Dubai',
                'phone' => '+971-4-123-4567',
                'email' => 'downtown@gmail.com',
                'latitude' => 25.2048,
                'longitude' => 55.2708,
                'is_active' => true,
            ]
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }

        $this->command->info('Branches seeded successfully');
    }
}
