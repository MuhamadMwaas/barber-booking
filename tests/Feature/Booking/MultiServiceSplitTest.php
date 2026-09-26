<?php

/**
 * BOOKING-GAP-01 — a multi-service booking is stored as one appointment per
 * contiguous block, linked parent → children.
 *
 * The reported bug: a customer booked a beard trim at 09:40 and a haircut at
 * 15:10, and got ONE appointment from 09:40 to 15:10 that held the provider's
 * whole day. These tests pin the replacement contract:
 *
 *  - back-to-back services at one provider stay one appointment;
 *  - a gap, or a second provider, starts a new appointment (a child of the
 *    earliest one), so every row's window is exactly the time it is worked;
 *  - one draft invoice on the root covers the group, one payment settles it;
 *  - each block can be cancelled on its own, and cancelling the root hands the
 *    invoice to the next block;
 *  - the daily limit, the repeat-cancellation alert, reminders and the
 *    confirmation email all treat the group as ONE booking.
 */

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Exceptions\SlotUnavailableException;
use App\Livewire\StaffDashboard;
use App\Mail\BookingConfirmationMail;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentCancellationService;
use App\Services\BookingService;
use App\Services\CancellationMonitor;
use App\Services\InvoiceFinalizationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->token = $this->salon->customerToken();
});

afterEach(function () {
    Carbon::setTestNow();
});

/** One service line for a booking request. */
function splitLine(Service $service, User $provider, string $startTime): array
{
    return [
        'service_id' => $service->id,
        'provider_id' => $provider->id,
        'start_time' => $startTime,
    ];
}

/** Book through the service layer — the path both the API and the dashboard take. */
function splitBook(array $services, ?User $customer = null): Appointment
{
    return app(BookingService::class)->createBooking($customer ?? test()->salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => $services,
    ]);
}

/** Book through the customer API. */
function splitBookViaApi(array $services, array $extra = []): TestResponse
{
    return test()->withToken(test()->token)->postJson('/api/bookings', $extra + [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => $services,
    ]);
}

/** The reported case, on the fixture's clock: 10:00 and 14:00 at the same provider. */
function morningAndAfternoon(): Appointment
{
    $salon = test()->salon;

    return splitBook([
        splitLine($salon->service, $salon->available, '10:00'),       // 60 min, 100.00
        splitLine($salon->secondService, $salon->available, '14:00'), // 60 min, 50.00
    ]);
}

/** A third service (30 min, 20.00) — one request may not repeat a service. */
function splitWashService(): Service
{
    return Service::create([
        'category_id' => test()->salon->category->id,
        'name' => 'Wash',
        'description' => 'Wash',
        'price' => 20.00,
        'duration_minutes' => 30,
        'is_active' => true,
        'sort_order' => 1,
        'color_code' => '#000000',
    ]);
}

function splitMinutes(Appointment $appointment): int
{
    return (int) $appointment->start_time->diffInMinutes($appointment->end_time);
}

// ── Splitting ────────────────────────────────────────────────────────────────

it('keeps back-to-back services at one provider in a single appointment', function () {
    $root = splitBook([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '11:00'),
    ]);

    expect($root->children)->toHaveCount(0)
        ->and($root->start_time->format('H:i'))->toBe('10:00')
        ->and($root->end_time->format('H:i'))->toBe('12:00')
        ->and($root->services_record)->toHaveCount(2)
        ->and(Appointment::count())->toBe(8 + 1); // 8 fixture slots + this one
});

it('splits services with a gap into a parent and a child that never span the gap', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    expect($root->parent_appointment_id)->toBeNull()
        ->and($root->start_time->format('H:i'))->toBe('10:00')
        ->and($root->end_time->format('H:i'))->toBe('11:00')
        ->and($child->parent_appointment_id)->toBe($root->id)
        ->and($child->start_time->format('H:i'))->toBe('14:00')
        ->and($child->end_time->format('H:i'))->toBe('15:00')
        // The invariant the old single row broke: the window IS the work.
        ->and(splitMinutes($root))->toBe($root->duration_minutes)
        ->and(splitMinutes($child))->toBe($child->duration_minutes)
        ->and($root->total_amount)->toEqual('100.00')
        ->and($child->total_amount)->toEqual('50.00')
        ->and($child->customer_id)->toBe($this->salon->customer->id);
});

it('puts one draft invoice on the root that covers every block', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    $invoice = Invoice::where('appointment_id', $root->id)->sole();

    expect($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and($invoice->items()->count())->toBe(2)
        ->and($invoice->total_amount)->toEqual('150.00')
        ->and(Invoice::where('appointment_id', $child->id)->exists())->toBeFalse();
});

it('no longer holds the gap against the provider', function () {
    morningAndAfternoon();

    // A different customer with nothing else that day (the fixture's `filler`
    // owns the fully-booked provider's whole day, so it is never free).
    $someoneElse = User::factory()->create();

    // 12:00 sits inside the old 10:00–15:00 span and used to answer 409.
    $other = splitBook(
        [splitLine($this->salon->service, $this->salon->available, '12:00')],
        $someoneElse,
    );

    expect($other->start_time->format('H:i'))->toBe('12:00');
});

it('puts a second provider\'s service on that provider\'s own calendar', function () {
    $root = splitBook([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->doesNotOffer, '11:00'),
    ]);
    $child = $root->children->sole();

    expect($root->provider_id)->toBe($this->salon->available->id)
        ->and($root->end_time->format('H:i'))->toBe('11:00')
        ->and($child->provider_id)->toBe($this->salon->doesNotOffer->id)
        ->and($child->start_time->format('H:i'))->toBe('11:00');

    // Before the split, only the first provider got a row: the second one
    // could be double-booked at 11:00. Now that slot is really taken.
    expect(fn () => splitBook(
        [splitLine($this->salon->secondService, $this->salon->doesNotOffer, '11:00')],
        $this->salon->filler,
    ))->toThrow(SlotUnavailableException::class);
});

it('groups a contiguous run and splits only at the gap', function () {
    $wash = splitWashService();
    $this->salon->offerService($this->salon->available, $wash);

    $root = splitBook([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '11:00'),
        splitLine($wash, $this->salon->available, '15:00'),
    ]);
    $child = $root->children->sole();

    expect($root->services_record)->toHaveCount(2)
        ->and($root->end_time->format('H:i'))->toBe('12:00')
        ->and($child->services_record)->toHaveCount(1)
        ->and($child->start_time->format('H:i'))->toBe('15:00')
        ->and($child->end_time->format('H:i'))->toBe('15:30')
        ->and(Invoice::where('appointment_id', $root->id)->sole()->total_amount)->toEqual('170.00');
});

it('rolls the whole group back when a later block is taken', function () {
    $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '14:00', '15:00');
    $appointmentsBefore = Appointment::count();
    $invoicesBefore = Invoice::count();

    splitBookViaApi([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '14:00'),
    ])->assertStatus(409);

    // Not even the 10:00 block survives: one booking, all or nothing.
    expect(Appointment::count())->toBe($appointmentsBefore)
        ->and(Invoice::count())->toBe($invoicesBefore);
});

// ── API contract ─────────────────────────────────────────────────────────────

it('returns the root as data and describes the other blocks alongside it', function () {
    $response = splitBookViaApi([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '14:00'),
    ])->assertStatus(201);

    $rootId = $response->json('data.id');

    // Unchanged keys keep their meaning for an app that knows nothing new.
    expect($response->json('data.start_time'))->toBe('10:00')
        ->and($response->json('data.end_time'))->toBe('11:00')
        ->and($response->json('data.total_amount'))->toEqual(100.00)
        // Additive keys.
        ->and($response->json('data.is_child_booking'))->toBeFalse()
        ->and($response->json('data.group_root_id'))->toBe($rootId)
        ->and($response->json('data.group_total_amount'))->toEqual(150.00)
        ->and($response->json('data.linked_appointments'))->toHaveCount(1)
        ->and($response->json('data.linked_appointments.0.start_time'))->toBe('14:00')
        ->and($response->json('data.linked_appointments.0.services_details'))->toHaveCount(1);
});

it('lists every block as its own card', function () {
    $rootId = splitBookViaApi([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '14:00'),
    ])->json('data.id');

    $cards = collect($this->withToken($this->token)->getJson('/api/bookings')->assertOk()->json('data'))
        ->keyBy('id');

    $child = $cards->firstWhere('is_child_booking', true);

    expect($cards)->toHaveCount(2)
        ->and($child['group_root_id'])->toBe($rootId)
        ->and($child['start_time'])->toBe('14:00')
        ->and($child['end_time'])->toBe('15:00')
        // Lists never load children, so no per-card query and no group keys.
        ->and($cards[$rootId])->not->toHaveKey('linked_appointments');
});

it('shows the linked blocks on the root\'s detail page but not on a child\'s', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    $detail = $this->withToken($this->token)->getJson("/api/bookings/{$root->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.linked_appointments');

    expect($detail->json('data.group_total_amount'))->toEqual(150.00);

    $this->withToken($this->token)->getJson("/api/appointments/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.group_root_id', $root->id)
        ->assertJsonMissingPath('data.group_total_amount');
});

// ── One booking, counted once ────────────────────────────────────────────────

it('counts a split booking once against the daily limit', function () {
    $this->salon->setSetting('max_daily_bookings', '1');

    splitBookViaApi([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '14:00'),
    ])->assertStatus(201);

    splitBookViaApi([
        splitLine($this->salon->service, $this->salon->available, '16:00'),
    ])->assertStatus(422);
});

it('schedules the requested reminder before every block', function () {
    // The test queue is synchronous: without this the delayed job would fire
    // at once and retire the reminder before it can be inspected.
    Queue::fake();

    $rootId = splitBookViaApi([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '14:00'),
    ], ['reminder_offset_hours' => 1])->assertStatus(201)->json('data.id');

    $root = Appointment::with('activeReminder', 'children.activeReminder')->find($rootId);
    $child = $root->children->sole();

    // "Now" is frozen at 08:00, so one hour before 10:00 is still ahead.
    expect($root->activeReminder->remind_at->format('H:i'))->toBe('09:00')
        ->and($child->activeReminder)->not->toBeNull()
        ->and($child->activeReminder->remind_at->format('H:i'))->toBe('13:00');
});

it('sends one confirmation email that lists every block', function () {
    Mail::fake();

    splitBookViaApi([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '14:00'),
    ])->assertStatus(201);

    Mail::assertQueued(BookingConfirmationMail::class, 1);
    Mail::assertQueued(BookingConfirmationMail::class, function (BookingConfirmationMail $mail) {
        $html = $mail->render();

        return str_contains($html, '10:00 AM - 11:00 AM')
            && str_contains($html, '02:00 PM - 03:00 PM')
            && str_contains($html, '150.00');
    });
});

// ── Cancelling one block ─────────────────────────────────────────────────────

it('cancels a child alone, drops it from the invoice, and still lets the rest be paid', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    $this->withToken($this->token)->postJson("/api/bookings/{$child->id}/cancel")->assertOk();

    $invoice = Invoice::where('appointment_id', $root->id)->sole();

    expect($child->fresh()->status)->toBe(AppointmentStatus::USER_CANCELLED)
        ->and($root->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and($invoice->items()->count())->toBe(1)
        ->and($invoice->total_amount)->toEqual('100.00');

    // This used to throw "cancelled_or_no_show": one cancelled member made the
    // whole group unpayable.
    $paid = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $root->fresh(),
        paymentMethod: 'cash',
    );

    expect($paid->status)->toBe(InvoiceStatus::PAID)
        ->and($paid->total_amount)->toEqual('100.00')
        ->and($paid->payments()->sole()->payment_metadata['covered_appointment_ids'])->toBe([$root->id])
        ->and($root->fresh()->status)->toBe(AppointmentStatus::COMPLETED)
        ->and($child->fresh()->status)->toBe(AppointmentStatus::USER_CANCELLED);
});

it('promotes the next block when the customer cancels the root', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();
    $invoiceId = Invoice::where('appointment_id', $root->id)->sole()->id;

    $this->withToken($this->token)->postJson("/api/bookings/{$root->id}/cancel")->assertOk();

    $oldRoot = $root->fresh();
    $newRoot = $child->fresh();
    $invoice = Invoice::find($invoiceId);

    expect($oldRoot->status)->toBe(AppointmentStatus::USER_CANCELLED)
        ->and($newRoot->status)->toBe(AppointmentStatus::PENDING)
        ->and($newRoot->parent_appointment_id)->toBeNull()
        // The cancelled block stays in the booking, now under the new root.
        ->and($oldRoot->parent_appointment_id)->toBe($newRoot->id)
        // Same draft, moved — no second invoice, and it bills what is left.
        ->and($invoice->appointment_id)->toBe($newRoot->id)
        ->and($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and($invoice->items()->count())->toBe(1)
        ->and($invoice->total_amount)->toEqual('50.00')
        ->and(Invoice::count())->toBe(1);

    $paid = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $newRoot,
        paymentMethod: 'cash',
    );

    expect($paid->id)->toBe($invoiceId)
        ->and($paid->total_amount)->toEqual('50.00')
        ->and($newRoot->fresh()->status)->toBe(AppointmentStatus::COMPLETED);
});

it('promotes the next block when staff cancel the root too', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    app(AppointmentCancellationService::class)->cancel(
        $root,
        AppointmentStatus::ADMIN_CANCELLED,
        'Cancelled by staff',
    );

    expect($root->fresh()->status)->toBe(AppointmentStatus::ADMIN_CANCELLED)
        ->and($child->fresh()->parent_appointment_id)->toBeNull()
        ->and($root->fresh()->parent_appointment_id)->toBe($child->id)
        ->and(Invoice::where('appointment_id', $child->id)->sole()->total_amount)->toEqual('50.00');
});

it('promotes the earliest standing block when a root has several children', function () {
    $wash = splitWashService();
    $this->salon->offerService($this->salon->doesNotOffer, $wash);

    $root = splitBook([
        splitLine($this->salon->service, $this->salon->available, '10:00'),
        splitLine($this->salon->secondService, $this->salon->available, '12:00'),
        splitLine($wash, $this->salon->doesNotOffer, '15:00'),
    ]);
    [$noon, $afternoon] = $root->children->sortBy('start_time')->values()->all();

    // Cancel the root: the earliest standing block (noon) takes over.
    app(AppointmentCancellationService::class)->cancel($root, AppointmentStatus::ADMIN_CANCELLED);

    expect($noon->fresh()->parent_appointment_id)->toBeNull()
        ->and($afternoon->fresh()->parent_appointment_id)->toBe($noon->id);

    // Cancel the new root: the last block, at another provider, takes over,
    // and both cancelled blocks hang under it.
    app(AppointmentCancellationService::class)->cancel($noon->fresh(), AppointmentStatus::ADMIN_CANCELLED);

    expect($afternoon->fresh()->parent_appointment_id)->toBeNull()
        ->and($root->fresh()->parent_appointment_id)->toBe($afternoon->id)
        ->and($noon->fresh()->parent_appointment_id)->toBe($afternoon->id)
        ->and(Invoice::where('appointment_id', $afternoon->id)->sole()->total_amount)->toEqual('20.00');
});

it('leaves a root in place when nothing else in its group still stands', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    app(AppointmentCancellationService::class)->cancel($child, AppointmentStatus::ADMIN_CANCELLED);
    app(AppointmentCancellationService::class)->cancel($root->fresh(), AppointmentStatus::ADMIN_CANCELLED);

    expect($root->fresh()->parent_appointment_id)->toBeNull()
        ->and($child->fresh()->parent_appointment_id)->toBe($root->id)
        ->and(Invoice::where('appointment_id', $root->id)->exists())->toBeTrue();
});

it('refuses to cancel a block that is no longer pending', function () {
    $root = morningAndAfternoon();

    app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $root,
        paymentMethod: 'cash',
    );

    expect(fn () => app(AppointmentCancellationService::class)->cancel(
        $root->children->sole()->fresh(),
        AppointmentStatus::ADMIN_CANCELLED,
    ))->toThrow(InvalidArgumentException::class);
});

it('counts dropping every block of one booking as a single cancellation', function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $root = morningAndAfternoon();
    $child = $root->children->sole();

    $this->withToken($this->token)->postJson("/api/bookings/{$root->id}/cancel")->assertOk();
    $this->withToken($this->token)->postJson("/api/bookings/{$child->id}/cancel")->assertOk();

    expect(app(CancellationMonitor::class)->recentCancellationCount($this->salon->customer))->toBe(1)
        ->and(DB::table('notifications')->count())->toBe(0);
});

// ── Staff dashboard ──────────────────────────────────────────────────────────

/** A dashboard user who may do everything (SuperAdmin short-circuits dashCan). */
function splitDashboardAdmin(): User
{
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true]);
    Permission::findOrCreate('StaffDashboard:access', 'web');
    Role::findOrCreate('SuperAdmin', 'web')->givePermissionTo('StaffDashboard:access');

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SuperAdmin');

    return $admin;
}

it('splits a gapped booking made from the dashboard and names every block', function () {
    $component = Livewire::actingAs(splitDashboardAdmin())
        ->test(StaffDashboard::class)
        ->set('selectedDate', SalonFixture::DATE)
        ->call('saveBookingFromAlpine', [
            'customerType' => 'existing',
            'selectedCustomerId' => $this->salon->customer->id,
            'services' => [
                splitLine($this->salon->service, $this->salon->available, '10:00'),
                splitLine($this->salon->secondService, $this->salon->available, '14:00'),
            ],
        ])
        ->assertDispatched('booking-saved');

    $root = Appointment::where('customer_id', $this->salon->customer->id)->whereNull('parent_appointment_id')->sole();
    $child = $root->children()->sole();

    $component->assertDispatched('notify', fn (string $event, array $params) => $params['type'] === 'success'
        && str_contains($params['message'], '#'.$root->number)
        && str_contains($params['message'], '#'.$child->number));

    expect($root->end_time->format('H:i'))->toBe('11:00')
        ->and($child->start_time->format('H:i'))->toBe('14:00');
});

it('lets staff cancel a root that still has an active block, promoting that block', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    // This used to be refused with "cannot_cancel_has_children".
    Livewire::actingAs(splitDashboardAdmin())
        ->test(StaffDashboard::class)
        ->set('selectedDate', SalonFixture::DATE)
        ->set('selectedAppointmentId', $root->id)
        ->call('cancelAppointment')
        ->assertDispatched('notify', fn (string $event, array $params) => $params['type'] === 'success');

    expect($root->fresh()->status)->toBe(AppointmentStatus::ADMIN_CANCELLED)
        ->and($child->fresh()->parent_appointment_id)->toBeNull()
        ->and(Invoice::where('appointment_id', $child->id)->sole()->total_amount)->toEqual('50.00');
});

// ── Adding a service to a block ──────────────────────────────────────────────

it('adds a service next to a child block without measuring the gap from the root', function () {
    $root = morningAndAfternoon();
    $child = $root->children->sole();

    // 15:00 is back-to-back with the 14:00 block but four hours after the root
    // ends; measured against the root it was refused as gap_too_large.
    $result = app(BookingService::class)->addServiceToBooking($child, [
        'service_id' => $this->salon->secondService->id,
        'provider_id' => $this->salon->doesNotOffer->id,
        'placement' => 'after',
    ]);

    $added = $result['appointment'];

    expect($result['mode'])->toBe('child_created')
        ->and($added->parent_appointment_id)->toBe($root->id)
        ->and($added->start_time->format('H:i'))->toBe('15:00')
        ->and($result['invoice']->total_amount)->toEqual('200.00');
});
