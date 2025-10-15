<?php

namespace Database\Seeders;

use App\Models\ProviderScheduledWork;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ProviderScheduledWorkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $providers_role = Role::where('name', 'provider')->first();
        $providers = User::role('provider')->get();
        // $providers = User::whereHas('roles', function ($query) use ($providers_role) {
        //     $query->where('model_has_roles.role_id', $providers_role->id);
        // })->get();


        $schedulePatterns = [

            'full_time' => [
                ['day_of_week' => 0, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => true, 'break_minutes' => 60],
                ['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => true, 'break_minutes' => 60],
                ['day_of_week' => 2, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => true, 'break_minutes' => 60],
                ['day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => true, 'break_minutes' => 60],
                ['day_of_week' => 4, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => true, 'break_minutes' => 60],
                ['day_of_week' => 5, 'start_time' => '10:00:00', 'end_time' => '19:00:00', 'is_work_day' => true, 'break_minutes' => 60],
                ['day_of_week' => 6, 'start_time' => '10:00:00', 'end_time' => '19:00:00', 'is_work_day' => true, 'break_minutes' => 60],
            ],

            'part_time' => [
                ['day_of_week' => 0, 'start_time' => '10:00:00', 'end_time' => '16:00:00', 'is_work_day' => true, 'break_minutes' => 30],
                ['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => false, 'break_minutes' => 0],
                ['day_of_week' => 2, 'start_time' => '10:00:00', 'end_time' => '16:00:00', 'is_work_day' => true, 'break_minutes' => 30],
                ['day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => false, 'break_minutes' => 0],
                ['day_of_week' => 4, 'start_time' => '10:00:00', 'end_time' => '16:00:00', 'is_work_day' => true, 'break_minutes' => 30],
                ['day_of_week' => 5, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => false, 'break_minutes' => 0],
                ['day_of_week' => 6, 'start_time' => '10:00:00', 'end_time' => '16:00:00', 'is_work_day' => true, 'break_minutes' => 30],
            ],

            'evening_shift' => [
                ['day_of_week' => 0, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => false, 'break_minutes' => 0],
                ['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_work_day' => false, 'break_minutes' => 0],
                ['day_of_week' => 2, 'start_time' => '14:00:00', 'end_time' => '21:00:00', 'is_work_day' => true, 'break_minutes' => 45],
                ['day_of_week' => 3, 'start_time' => '14:00:00', 'end_time' => '21:00:00', 'is_work_day' => true, 'break_minutes' => 45],
                ['day_of_week' => 4, 'start_time' => '14:00:00', 'end_time' => '21:00:00', 'is_work_day' => true, 'break_minutes' => 45],
                ['day_of_week' => 5, 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_work_day' => true, 'break_minutes' => 60],
                ['day_of_week' => 6, 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'is_work_day' => true, 'break_minutes' => 60],
            ],
        ];

        $patternTypes = ['full_time', 'full_time', 'full_time', 'part_time', 'full_time', 'evening_shift', 'full_time', 'part_time'];

        foreach ($providers as $index => $provider) {
            $patternType = $patternTypes[$index % count($patternTypes)];
            $schedule = $schedulePatterns[$patternType];

            foreach ($schedule as $day) {
                ProviderScheduledWork::create([
                    'user_id' => $provider->id,
                    'day_of_week' => $day['day_of_week'],
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                    'is_work_day' => $day['is_work_day'],
                    'break_minutes' => $day['break_minutes'],
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Provider scheduled work seeded successfully');
    }
}
