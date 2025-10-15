<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            LanguageSeeder::class,
            BranchSeeder::class,
            SalonSettingSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            SalonScheduleSeeder::class,
            ReasonLeaveSeeder::class,
            ProviderScheduledWorkSeeder::class,
            ProviderTimeOffSeeder::class,
            ServiceCategorySeeder::class,
            ServiceSeeder::class,
            ProviderServiceSeeder::class,
            AppointmentSeeder::class,
        ]);


    }
}
