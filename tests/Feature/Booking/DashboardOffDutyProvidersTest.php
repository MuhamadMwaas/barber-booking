<?php

/**
 * Off-duty providers on the StaffDashboard timeline (not scheduled that day,
 * or on a full-day leave) are hidden by default but can be shown and hidden
 * again — one at a time from the team list, or all at once.
 */

use App\Livewire\StaffDashboard;
use App\Models\Branch;
use App\Models\Language;
use App\Models\SalonSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    Carbon::setTestNow(SalonFixture::NOW);

    // The timeline renders nothing on a closed day, so open the salon.
    foreach (Branch::where('is_active', true)->get() as $branch) {
        SalonSchedule::create([
            'branch_id' => $branch->id,
            'day_of_week' => Carbon::parse(SalonFixture::DATE)->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'is_open' => true,
        ]);
    }

    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);
    Permission::findOrCreate('StaffDashboard:access', 'web');
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');
    Role::findByName('admin', 'web')->givePermissionTo('StaffDashboard:access');
});

afterEach(function () {
    Carbon::setTestNow();
});

function dashboardOnFixtureDate()
{
    return Livewire::actingAs(test()->admin)
        ->test(StaffDashboard::class)
        ->call('selectDate', SalonFixture::DATE);
}

/** Timeline columns currently rendered, keyed by provider id. */
function timelineColumns($component): array
{
    return collect($component->instance()->getTimelineData()['providers'])->keyBy('id')->all();
}

it('hides off-duty providers by default', function () {
    $columns = timelineColumns(dashboardOnFixtureDate());

    expect($columns)
        ->toHaveKey($this->salon->available->id)
        ->not->toHaveKey($this->salon->notWorking->id)
        ->not->toHaveKey($this->salon->onLeave->id);
});

it('shows and hides a single off-duty provider, flagged as unavailable', function () {
    $component = dashboardOnFixtureDate()->call('toggleProvider', $this->salon->notWorking->id);

    $columns = timelineColumns($component);
    expect($columns)->toHaveKey($this->salon->notWorking->id)
        ->and($columns[$this->salon->notWorking->id]['is_available'])->toBeFalse()
        ->and($columns[$this->salon->available->id]['is_available'])->toBeTrue();

    $component->call('toggleProvider', $this->salon->notWorking->id);
    expect(timelineColumns($component))->not->toHaveKey($this->salon->notWorking->id);
});

it('keeps a shown off-duty provider across re-renders', function () {
    $component = dashboardOnFixtureDate()
        ->call('toggleProvider', $this->salon->onLeave->id)
        ->call('$refresh');

    expect(timelineColumns($component))->toHaveKey($this->salon->onLeave->id);
});

it('shows all off-duty providers at once and hides them again', function () {
    $component = dashboardOnFixtureDate()->call('toggleUnavailableProviders');

    expect(timelineColumns($component))
        ->toHaveKey($this->salon->notWorking->id)
        ->toHaveKey($this->salon->onLeave->id)
        ->toHaveKey($this->salon->available->id);

    $component->call('toggleUnavailableProviders');

    expect(timelineColumns($component))
        ->not->toHaveKey($this->salon->notWorking->id)
        ->not->toHaveKey($this->salon->onLeave->id)
        ->toHaveKey($this->salon->available->id);
});

it('ignores ids that are not dashboard providers', function () {
    $component = dashboardOnFixtureDate()->call('toggleProvider', $this->salon->customer->id);

    expect($component->get('selectedProviderIds'))->not->toContain($this->salon->customer->id);
});

it('resets to available providers only when the date changes', function () {
    $component = dashboardOnFixtureDate()
        ->call('toggleProvider', $this->salon->notWorking->id)
        ->call('selectDate', SalonFixture::DATE);

    expect(timelineColumns($component))->not->toHaveKey($this->salon->notWorking->id);
});
