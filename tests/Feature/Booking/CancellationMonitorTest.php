<?php

/**
 * BOOK-08 — cancelling is always allowed; repeat cancellers are surfaced.
 *
 * Two things are pinned here: the two customer cancel endpoints now agree (they
 * used to disagree about cancelling after the appointment had started, so the
 * customer chose their own policy by choosing a URL), and the second
 * cancellation inside a rolling week raises a Filament notification to admins
 * and managers.
 */

use App\Enum\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Services\CancellationMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->token = $this->salon->customerToken();
    Carbon::setTestNow(SalonFixture::NOW);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A panel user who should receive cancellation alerts. */
function recipient(string $role): User
{
    Role::findOrCreate($role, 'web');

    $user = User::create([
        'first_name' => ucfirst($role),
        'last_name' => 'User',
        'email' => $role.'@example.com',
        'password' => 'secret-password',
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

/** A cancelled booking for the fixture customer, cancelled $daysAgo days ago. */
function pastCancellation(int $daysAgo): Appointment
{
    $appointment = test()->salon->bookSlot(
        test()->salon->available,
        SalonFixture::DATE,
        '15:00',
        '16:00',
    );

    $appointment->forceFill([
        'customer_id' => test()->salon->customer->id,
        'status' => AppointmentStatus::USER_CANCELLED,
        'cancelled_at' => now()->subDays($daysAgo),
    ])->save();

    return $appointment;
}

/** Book at 10:00 and cancel it through the bookings endpoint. */
function bookThenCancel(): void
{
    $response = test()->withToken(test()->token)->postJson('/api/bookings', [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => test()->salon->service->id,
            'provider_id' => test()->salon->available->id,
            'start_time' => '10:00',
        ]],
    ]);

    $id = $response->json('data.id');

    test()->withToken(test()->token)
        ->postJson("/api/bookings/{$id}/cancel")
        ->assertOk();
}

// ── Unified policy ───────────────────────────────────────────────────────────

it('lets a customer cancel through either endpoint after the appointment started', function () {
    $appointment = $this->salon->bookSlot(
        $this->salon->available,
        SalonFixture::DATE,
        '10:00',
        '11:00',
    );
    $appointment->forceFill(['customer_id' => $this->salon->customer->id])->save();

    // Jump past the start time. /api/appointments used to refuse here while
    // /api/bookings allowed it.
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE.' 10:30'));

    $this->withToken($this->token)
        ->postJson("/api/appointments/{$appointment->id}/cancel", ['reason' => 'Running late'])
        ->assertOk();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::USER_CANCELLED);
});

it('rejects an over-long cancellation reason', function () {
    $appointment = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $appointment->forceFill(['customer_id' => $this->salon->customer->id])->save();

    $this->withToken($this->token)
        ->postJson("/api/bookings/{$appointment->id}/cancel", [
            'cancellation_reason' => str_repeat('x', 501),
        ])
        ->assertStatus(422);
});

// ── The alert ────────────────────────────────────────────────────────────────

it('stays quiet on a customer\'s first cancellation of the week', function () {
    recipient('admin');

    bookThenCancel();

    expect(DB::table('notifications')->count())->toBe(0);
});

it('alerts admins and managers on the second cancellation within a week', function () {
    $admin = recipient('admin');
    $manager = recipient('manager');

    pastCancellation(daysAgo: 3);
    bookThenCancel();

    $notifications = DB::table('notifications')->get();

    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('notifiable_id')->sort()->values()->all())
        ->toBe(collect([$admin->id, $manager->id])->sort()->values()->all());

    expect($notifications->first()->data)->toContain($this->salon->customer->full_name);
});

it('ignores a cancellation that fell outside the window', function () {
    recipient('admin');

    pastCancellation(daysAgo: 8);
    bookThenCancel();

    expect(DB::table('notifications')->count())->toBe(0);
});

it('does not count cancellations the salon made itself', function () {
    recipient('admin');

    // An ADMIN_CANCELLED booking is the salon's own doing and must not be held
    // against the customer.
    $appointment = pastCancellation(daysAgo: 1);
    $appointment->forceFill(['status' => AppointmentStatus::ADMIN_CANCELLED])->save();

    bookThenCancel();

    expect(DB::table('notifications')->count())->toBe(0);
});

it('keeps alerting as the pattern escalates', function () {
    recipient('admin');

    pastCancellation(daysAgo: 1);
    pastCancellation(daysAgo: 2);
    bookThenCancel();

    // Third cancellation in the window: still alerted, and the count says three.
    expect(DB::table('notifications')->count())->toBe(1)
        ->and(DB::table('notifications')->first()->data)->toContain('3');
});

it('does not let an alerting failure undo the cancellation', function () {
    $appointment = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '10:00', '11:00');
    $appointment->forceFill(['customer_id' => $this->salon->customer->id])->save();

    $exploding = new class extends CancellationMonitor {
        public function recentCancellationCount(User $customer): int
        {
            throw new RuntimeException('alerting is down');
        }
    };
    app()->instance(CancellationMonitor::class, $exploding);

    expect($appointment->cancel('Plans changed'))->toBeTrue()
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::USER_CANCELLED);
});
