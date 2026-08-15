<?php

namespace Tests\Feature;

use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProviderAvailabilityLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $provider;

    private Service $service;

    /**
     * The Tuesday used across the tests — always in the future, and always
     * inside the `max_booking_days` window (default 10).
     *
     * It used to be `today()->addWeek()->next(TUESDAY)`, which lands 8-14 days
     * out depending on which weekday the suite runs on. Availability now refuses
     * dates past the booking window with `outside_booking_window`, so on the
     * long end of that range these tests would fail on some days of the week and
     * pass on others.
     */
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = Carbon::today()->next(Carbon::TUESDAY)->format('Y-m-d');

        $this->provider = User::factory()->create(['is_active' => true]);

        $category = ServiceCategory::create([
            'name' => 'Hair',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->service = Service::create([
            'category_id' => $category->id,
            'name' => 'Hair Cut',
            'description' => 'Hair Cut',
            'price' => 50,
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
            'color_code' => '#000000',
        ]);

        DB::table('provider_service')->insert([
            'provider_id' => $this->provider->id,
            'service_id' => $this->service->id,
            'is_active' => true,
        ]);

        ProviderScheduledWork::create([
            'user_id' => $this->provider->id,
            'day_of_week' => Carbon::parse($this->date)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_work_day' => true,
            'is_active' => true,
        ]);
    }

    private function availability(?string $date = null): array
    {
        return $this->getJson('/api/availability/provider?' . http_build_query([
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => $date ?? $this->date,
        ]))->json();
    }

    private function createTimeOff(array $attributes): ProviderTimeOff
    {
        $reason = ReasonLeave::firstOrCreate(['name' => 'Vacation']);

        return ProviderTimeOff::create($attributes + ['reason_id' => $reason->id]);
    }

    public function test_a_working_day_without_leave_is_available(): void
    {
        $response = $this->availability();

        $this->assertTrue($response['data']['is_available']);
        $this->assertSame('available', $response['data']['reason_code']);
        $this->assertSame(8, $response['data']['total_slots']);
    }

    public function test_provider_on_full_day_leave_is_not_available(): void
    {
        $this->createTimeOff([
            'user_id' => $this->provider->id,
            'type' => ProviderTimeOff::TYPE_FULL_DAY,
            'start_date' => Carbon::parse($this->date)->subDay()->format('Y-m-d'),
            'end_date' => Carbon::parse($this->date)->addDay()->format('Y-m-d'),
        ]);

        $response = $this->availability();

        $this->assertFalse($response['data']['is_available']);
        $this->assertSame('on_leave', $response['data']['reason_code']);
        $this->assertSame(0, $response['data']['total_slots']);
        $this->assertSame([], $response['data']['available_slots']);
    }

    public function test_full_day_leave_without_an_end_date_still_blocks_its_own_day(): void
    {
        $this->createTimeOff([
            'user_id' => $this->provider->id,
            'type' => ProviderTimeOff::TYPE_FULL_DAY,
            'start_date' => $this->date,
            'end_date' => null,
        ]);

        $response = $this->availability();

        $this->assertFalse($response['data']['is_available']);
        $this->assertSame('on_leave', $response['data']['reason_code']);
    }

    public function test_hourly_leave_removes_only_the_overlapping_slots(): void
    {
        $this->createTimeOff([
            'user_id' => $this->provider->id,
            'type' => ProviderTimeOff::TYPE_HOURLY,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'start_time' => '11:00:00',
            'end_time' => '14:00:00',
        ]);

        $response = $this->availability();

        $this->assertSame(200, $this->getJson('/api/availability/provider?' . http_build_query([
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => $this->date,
        ]))->status());

        $starts = array_column($response['data']['available_slots'], 'start_time');

        $this->assertSame(['09:00', '10:00', '14:00', '15:00', '16:00'], $starts);
    }

    public function test_hourly_leave_spanning_several_days_blocks_every_day_in_range(): void
    {
        $this->createTimeOff([
            'user_id' => $this->provider->id,
            'type' => ProviderTimeOff::TYPE_HOURLY,
            'start_date' => Carbon::parse($this->date)->subDays(2)->format('Y-m-d'),
            'end_date' => Carbon::parse($this->date)->addDays(2)->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $response = $this->availability();

        $this->assertFalse($response['data']['is_available']);
        $this->assertSame(0, $response['data']['total_slots']);
    }

    public function test_calendar_marks_leave_days_as_unavailable(): void
    {
        $start = Carbon::parse($this->date)->subDay();
        $end = Carbon::parse($this->date)->addDay();

        $this->createTimeOff([
            'user_id' => $this->provider->id,
            'type' => ProviderTimeOff::TYPE_FULL_DAY,
            'start_date' => $this->date,
            'end_date' => $this->date,
        ]);

        $calendar = $this->getJson('/api/availability/calendar?' . http_build_query([
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ]))->json('data.calendar');

        $day = collect($calendar)->firstWhere('date', $this->date);

        $this->assertFalse($day['is_available']);
        $this->assertSame('on_leave', $day['reason_code']);
        $this->assertSame(0, $day['available_slots_count']);
    }
}
