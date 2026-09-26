<?php

/**
 * Booking from the staff dashboard outside a barber's working hours.
 *
 * Reported: dragging on a barber's column at an hour they do not work opened
 * the booking modal with that barber greyed out and NOT selected, and saving
 * answered "Please fill in all service details" — hard-coded English that
 * describes nothing the staff member did wrong. The barber is simply not
 * available at that hour, and the message now says so, in the user's language.
 */

use App\Livewire\StaffDashboard;
use App\Models\Appointment;
use App\Models\Language;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;

    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);
    Permission::findOrCreate('StaffDashboard:access', 'web');
    Role::findOrCreate('SuperAdmin', 'web')->givePermissionTo('StaffDashboard:access');

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('SuperAdmin');
});

afterEach(function () {
    Carbon::setTestNow();
    app()->setLocale(config('app.locale'));
});

/** Save through the modal endpoint and return the error toast text. */
function dashboardSaveError(array $services): string
{
    $message = null;

    Livewire::actingAs(test()->admin)
        ->test(StaffDashboard::class)
        ->set('selectedDate', SalonFixture::DATE)
        ->call('saveBookingFromAlpine', [
            'customerType' => 'existing',
            'selectedCustomerId' => test()->salon->customer->id,
            'services' => $services,
        ])
        ->assertDispatched('booking-error')
        ->assertDispatched('notify', function (string $event, array $params) use (&$message) {
            $message = $params['message'];

            return $params['type'] === 'error';
        });

    return (string) $message;
}

/** A row as the modal sends it after a drag on `available`'s column at $time. */
function draggedRow(string $time, ?int $providerId = null): array
{
    $salon = test()->salon;

    return [
        'category_id' => $salon->category->id,
        'service_id' => $salon->service->id,
        'start_time' => $time,
        'duration' => 60,
        'provider_id' => $providerId ?? '',
        'preselected_provider_id' => $salon->available->id,
    ];
}

it('names the barber and says they are outside working hours', function () {
    // `available` works 09:00–17:00; 18:00 is after their shift.
    $message = dashboardSaveError([draggedRow('18:00')]);

    expect($message)
        ->toBe('Available Provider is not available at 18:00 (Outside working hours). Choose another time or another provider.')
        ->not->toContain('fill in all service details');
});

it('explains it in the dashboard user\'s language', function (string $locale, string $expected) {
    app()->setLocale($locale);

    expect(dashboardSaveError([draggedRow('18:00')]))->toBe($expected);
})->with([
    'de' => ['de', 'Available Provider ist um 18:00 nicht verfügbar (Außerhalb der Arbeitszeit). Bitte wählen Sie eine andere Uhrzeit oder einen anderen Mitarbeiter.'],
    'ar' => ['ar', 'Available Provider غير متاح الساعة 18:00 (خارج ساعات الدوام). اختر وقتاً آخر أو مقدم خدمة آخر.'],
]);

it('says nobody is available when no barber works at that hour', function () {
    $row = draggedRow('18:00');
    unset($row['preselected_provider_id']);

    expect(dashboardSaveError([$row]))
        ->toBe('No provider is available for Hair Cut at 18:00. Choose another time.');
});

it('asks for a provider when someone could have been picked', function () {
    $row = draggedRow('10:00');
    unset($row['preselected_provider_id']);

    expect(dashboardSaveError([$row]))
        ->toBe('Please choose an available provider for Hair Cut at 10:00.');
});

it('refuses instead of silently dropping a service that has no provider', function () {
    // The first row is complete; the second's barber was greyed out. This used
    // to save the first service alone without a word about the second.
    $message = dashboardSaveError([
        draggedRow('10:00', $this->salon->available->id),
        [...draggedRow('18:00'), 'service_id' => $this->salon->secondService->id],
    ]);

    expect($message)->toContain('18:00')
        ->and(Appointment::where('customer_id', $this->salon->customer->id)->exists())->toBeFalse();
});

it('ignores an extra row with no service picked', function () {
    Livewire::actingAs($this->admin)
        ->test(StaffDashboard::class)
        ->set('selectedDate', SalonFixture::DATE)
        ->call('saveBookingFromAlpine', [
            'customerType' => 'existing',
            'selectedCustomerId' => $this->salon->customer->id,
            'services' => [
                draggedRow('10:00', $this->salon->available->id),
                ['category_id' => '', 'service_id' => '', 'start_time' => '11:00', 'provider_id' => ''],
            ],
        ])
        ->assertDispatched('booking-saved');
});

it('translates the server-side refusal when an out-of-hours provider is forced through', function () {
    // A forged/raced request that names the barber anyway still hits
    // BookingValidationService — whose message used to be hard-coded English.
    app()->setLocale('de');

    expect(dashboardSaveError([draggedRow('18:00', $this->salon->available->id)]))
        ->toBe('Der Mitarbeiter ist zu dieser Zeit nicht verfügbar: außerhalb der Arbeitszeit (09:00 - 17:00).');
});
