<?php

namespace Tests\Feature;

use App\Livewire\StaffDashboard;
use App\Models\ProviderTimeOff;
use App\Models\Language;
use App\Models\ReasonLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * BOOK-04 — a leave must be well-formed before it reaches the calendar.
 *
 * The dashboard's time-off modal validates nothing in the browser and neither
 * save path checked anything server-side, so an "hourly" leave with empty times,
 * or a range running backwards, was stored happily. Those rows then have no
 * sensible meaning in either the availability or the booking layer.
 *
 * A backwards time pair is rejected rather than read as crossing midnight: the
 * salon does not trade through midnight, so 22:00 → 02:00 is a typo.
 */
class ProviderTimeOffValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private int $reasonId;

    protected function setUp(): void
    {
        parent::setUp();

        // The dashboard view renders leave reasons, which resolve their label
        // through the default language.
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);

        Permission::findOrCreate('StaffDashboard:access', 'web');
        Permission::findOrCreate('StaffDashboard:manage_timeoff', 'web');
        Role::findOrCreate('admin', 'web')
            ->givePermissionTo(['StaffDashboard:access', 'StaffDashboard:manage_timeoff']);

        $this->staff = User::create([
            'first_name' => 'Dana',
            'last_name' => 'Manager',
            'email' => 'dana@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
        $this->staff->assignRole('admin');

        $this->reasonId = ReasonLeave::firstOrCreate(['name' => 'Vacation'])->id;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'providerId' => $this->staff->id,
            'type' => ProviderTimeOff::TYPE_HOURLY,
            'startDate' => '2026-09-10',
            'endDate' => '2026-09-10',
            'startTime' => '12:00',
            'endTime' => '13:00',
            'reasonId' => $this->reasonId,
        ];
    }

    private function save(array $overrides = []): void
    {
        Livewire::actingAs($this->staff)
            ->test(StaffDashboard::class)
            ->call('saveTimeOffFromAlpine', $this->payload($overrides));
    }

    public function test_a_well_formed_hourly_leave_is_stored(): void
    {
        $this->save();

        $this->assertDatabaseCount('provider_time_offs', 1);
    }

    public function test_it_rejects_an_end_date_before_the_start_date(): void
    {
        $this->save(['startDate' => '2026-09-10', 'endDate' => '2026-09-08']);

        $this->assertDatabaseCount('provider_time_offs', 0);
    }

    public function test_it_rejects_an_hourly_leave_without_times(): void
    {
        $this->save(['startTime' => '', 'endTime' => '']);

        $this->assertDatabaseCount('provider_time_offs', 0);
    }

    public function test_it_rejects_a_leave_whose_times_run_backwards(): void
    {
        // Read as a typo, not as an absence crossing midnight.
        $this->save(['startTime' => '22:00', 'endTime' => '02:00']);

        $this->assertDatabaseCount('provider_time_offs', 0);
    }

    public function test_a_full_day_leave_needs_no_times(): void
    {
        $this->save([
            'type' => ProviderTimeOff::TYPE_FULL_DAY,
            'startTime' => '',
            'endTime' => '',
        ]);

        $this->assertDatabaseCount('provider_time_offs', 1);
    }
}
