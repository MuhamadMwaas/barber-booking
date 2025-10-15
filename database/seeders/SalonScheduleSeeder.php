<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalonSchedule;
use App\Models\Branch;

class SalonScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();


        $schedules = [
            ['day_of_week' => 0, 'open_time' => '09:00:00', 'close_time' => '21:00:00', 'is_open' => true],
            ['day_of_week' => 1, 'open_time' => '09:00:00', 'close_time' => '21:00:00', 'is_open' => true],
            ['day_of_week' => 2, 'open_time' => '09:00:00', 'close_time' => '21:00:00', 'is_open' => true],
            ['day_of_week' => 3, 'open_time' => '09:00:00', 'close_time' => '21:00:00', 'is_open' => true],
            ['day_of_week' => 4, 'open_time' => '09:00:00', 'close_time' => '21:00:00', 'is_open' => true],
            ['day_of_week' => 5, 'open_time' => '10:00:00', 'close_time' => '23:00:00', 'is_open' => true],
            ['day_of_week' => 6, 'open_time' => '10:00:00', 'close_time' => '23:00:00', 'is_open' => true],
        ];

        foreach ($branches as $branch) {

            foreach ($schedules as $schedule) {
                SalonSchedule::create(array_merge($schedule, ['branch_id' => $branch->id]));
            }

        }

        $this->command->info('Salon schedules seeded successfully');
    }
}
