<?php

namespace Database\Seeders;

use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProviderTimeOffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                // $providers = User::where('user_type', User::TYPE_PROVIDER)->get();
        $providers = User::role('provider')->get();

        $reasons = ReasonLeave::all();

        $timeOffs = [
            [
                'provider_index' => 0,
                'type' => ProviderTimeOff::TYPE_FULL_DAY,
                'start_date' => Carbon::now()->subDays(45),
                'end_date' => Carbon::now()->subDays(40),
                'duration_days' => 5,
                'reason_name' => 'Annual Leave',
            ],
            [
                'provider_index' => 1,
                'type' => ProviderTimeOff::TYPE_HOURLY,
                'start_date' => Carbon::now()->subDays(15),
                'end_date' => Carbon::now()->subDays(15),
                'start_time' => '10:00:00',
                'end_time' => '13:00:00',
                'duration_hours' => 3,
                'reason_name' => 'Medical Appointment',
            ],
            [
                'provider_index' => 2,
                'type' => ProviderTimeOff::TYPE_FULL_DAY,
                'start_date' => Carbon::now()->subDays(30),
                'end_date' => Carbon::now()->subDays(28),
                'duration_days' => 2,
                'reason_name' => 'Sick Leave',
            ],

            // Current/Active time offs
            [
                'provider_index' => 3,
                'type' => ProviderTimeOff::TYPE_FULL_DAY,
                'start_date' => Carbon::now()->subDays(2),
                'end_date' => Carbon::now()->addDays(3),
                'duration_days' => 5,
                'reason_name' => 'Annual Leave',
            ],
            [
                'provider_index' => 4,
                'type' => ProviderTimeOff::TYPE_HOURLY,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now(),
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'duration_hours' => 2,
                'reason_name' => 'Personal Day',
            ],

            // Upcoming time offs
            [
                'provider_index' => 0,
                'type' => ProviderTimeOff::TYPE_FULL_DAY,
                'start_date' => Carbon::now()->addDays(10),
                'end_date' => Carbon::now()->addDays(17),
                'duration_days' => 7,
                'reason_name' => 'Annual Leave',
            ],
            [
                'provider_index' => 1,
                'type' => ProviderTimeOff::TYPE_FULL_DAY,
                'start_date' => Carbon::now()->addDays(5),
                'end_date' => Carbon::now()->addDays(6),
                'duration_days' => 2,
                'reason_name' => 'Training/Conference',
            ],
            [
                'provider_index' => 2,
                'type' => ProviderTimeOff::TYPE_HOURLY,
                'start_date' => Carbon::now()->addDays(3),
                'end_date' => Carbon::now()->addDays(3),
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
                'duration_hours' => 3,
                'reason_name' => 'Medical Appointment',
            ],
            [
                'provider_index' => 5,
                'type' => ProviderTimeOff::TYPE_FULL_DAY,
                'start_date' => Carbon::now()->addDays(20),
                'end_date' => Carbon::now()->addDays(24),
                'duration_days' => 4,
                'reason_name' => 'Religious Holiday',
            ],
            [
                'provider_index' => 6,
                'type' => ProviderTimeOff::TYPE_HOURLY,
                'start_date' => Carbon::now()->addDays(7),
                'end_date' => Carbon::now()->addDays(7),
                'start_time' => '11:00:00',
                'end_time' => '14:00:00',
                'duration_hours' => 3,
                'reason_name' => 'Personal Day',
            ],
            [
                'provider_index' => 7,
                'type' => ProviderTimeOff::TYPE_FULL_DAY,
                'start_date' => Carbon::now()->addDays(30),
                'end_date' => Carbon::now()->addDays(35),
                'duration_days' => 5,
                'reason_name' => 'Annual Leave',
            ],
        ];

        foreach ($timeOffs as $timeOff) {
            $provider = $providers[$timeOff['provider_index']];
            $reason = $reasons->where('name', $timeOff['reason_name'])->first();

            $data = [
                'user_id' => $provider->id,
                'type' => $timeOff['type'],
                'start_date' => $timeOff['start_date'],
                'end_date' => $timeOff['end_date'],
                'reason_id' => $reason->id,
            ];

            if ($timeOff['type'] == ProviderTimeOff::TYPE_HOURLY) {
                $data['start_time'] = $timeOff['start_time'];
                $data['end_time'] = $timeOff['end_time'];
                $data['duration_hours'] = $timeOff['duration_hours'];
            } else {
                $data['duration_days'] = $timeOff['duration_days'];
            }

            ProviderTimeOff::create($data);
        }

        $this->command->info('Provider time offs seeded successfully');
    }
}
