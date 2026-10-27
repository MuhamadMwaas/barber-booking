<?php

/**
 * The booking ticket ("Print Order" in the StaffDashboard) is a work order for
 * the provider. Salon request: it never shows prices, totals or durations —
 * not even the end time, which would reveal the duration.
 */

use App\Models\User;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;

    Permission::findOrCreate('StaffDashboard:print_ticket', 'web');
    Role::findByName('manager', 'web')->givePermissionTo('StaffDashboard:print_ticket');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('manager');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('prints the booking ticket without price, total, duration or end time', function () {
    // Hair Cut (60 min, 100.00) then Beard Trim (60 min, 50.00): 10:00 – 12:00.
    $appointment = app(BookingService::class)->createBooking($this->salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [
            ['service_id' => $this->salon->service->id, 'provider_id' => $this->salon->available->id, 'start_time' => '10:00'],
            ['service_id' => $this->salon->secondService->id, 'provider_id' => $this->salon->available->id, 'start_time' => '11:00'],
        ],
    ]);

    $this->actingAs($this->manager)
        ->get(route('appointment.print', $appointment))
        ->assertOk()
        ->assertSee('Hair Cut')
        ->assertSee('Beard Trim')
        ->assertSee('10:00')
        ->assertSee('UNPAID')
        ->assertDontSee('12:00')
        ->assertDontSee('60m')
        ->assertDontSee('120m')
        ->assertDontSee('100.00')
        ->assertDontSee('150.00')
        ->assertDontSee('50.00')
        ->assertDontSee('EUR');
});
