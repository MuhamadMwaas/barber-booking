<?php

/**
 * DELETE /api/profile end to end, on data built through the real services.
 *
 * The account row must disappear; everything the salon recorded must stay —
 * every booking (with the customer's name, phone and email on it) and every
 * invoice and payment — and every screen that shows those records afterwards
 * must still render. DeleteAccountTest pins the individual column rules; this
 * file proves the whole thing holds together on a realistic history.
 */

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Livewire\StaffDashboard;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\AppointmentCancellationService;
use App\Services\AppointmentReminderService;
use App\Services\Auth\GoogleSignInService;
use App\Services\BookingService;
use App\Services\DailyReportService;
use App\Services\GuestBookingClaimService;
use App\Services\InvoiceFinalizationService;
use Database\Seeders\InvoiceTemplateSeeder;
use Database\Seeders\PrinterSeeder;
use Filament\Facades\Filament;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\SalonFixture;

const DELETION_PHONE = '+4917655501234';

/** The Wednesday after SalonFixture::DATE — the next day the fixture's barbers work. */
const NEXT_WEEK = '2026-09-16';

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    Notification::fake();
    Http::fake();
    Storage::fake('public');
    $this->withoutMiddleware(ThrottleRequests::class);

    $this->salon = new SalonFixture;
    $this->customer = $this->salon->customer;
    $this->customer->update(['phone' => DELETION_PHONE, 'phone_verified_at' => now()]);

    $this->seed([PrinterSeeder::class, InvoiceTemplateSeeder::class]);
    foreach (['Invoice:print', 'Invoice:print_others', 'StaffDashboard:print_ticket', 'StaffDashboard:access'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    Role::findByName('manager', 'web')->givePermissionTo([
        'Invoice:print', 'Invoice:print_others', 'StaffDashboard:print_ticket', 'StaffDashboard:access',
    ]);
    Role::findOrCreate('SuperAdmin', 'web')->givePermissionTo(['StaffDashboard:access']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('manager');
});

afterEach(function () {
    Carbon::setTestNow();
});

function deletionBook(User $customer, string $date, array $slots): Appointment
{
    $salon = test()->salon;

    return app(BookingService::class)->createBooking($customer, [
        'date' => $date,
        'payment_method' => 'cash',
        'services' => array_map(fn (array $slot) => [
            'service_id' => $slot[0]->id,
            'provider_id' => $salon->available->id,
            'start_time' => $slot[1],
        ], $slots),
    ]);
}

/**
 * Everything one real customer accumulates: a paid visit, a split visit left
 * unpaid, a cancelled booking, two upcoming bookings (one split), a reminder,
 * and the personal rows around the account.
 *
 * @return array<string, mixed>
 */
function buildCustomerHistory(User $customer): array
{
    $salon = test()->salon;

    // ── Last week (frozen "now" is 2026-09-07, booking day 2026-09-09) ──────
    $paid = deletionBook($customer, SalonFixture::DATE, [[$salon->service, '09:00']]);
    Auth::login($salon->available);
    $paidInvoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $paid,
        paymentMethod: 'cash',
    );
    Auth::logout();

    // Two blocks with a gap between them: one parent + one child appointment.
    $pastSplit = deletionBook($customer, SalonFixture::DATE, [
        [$salon->service, '11:00'],
        [$salon->secondService, '14:00'],
    ]);

    $cancelled = deletionBook($customer, SalonFixture::DATE, [[$salon->service, '16:00']]);
    app(AppointmentCancellationService::class)->cancel($cancelled, AppointmentStatus::USER_CANCELLED, 'Changed my mind');

    // ── A day later: 2026-09-09 is history, next Wednesday is upcoming ──────
    Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));

    $future = deletionBook($customer, NEXT_WEEK, [[$salon->service, '10:00']]);
    $futureSplit = deletionBook($customer, NEXT_WEEK, [
        [$salon->service, '12:00'],
        [$salon->secondService, '15:00'],
    ]);

    $reminder = app(AppointmentReminderService::class)
        ->scheduleReminder($future->fresh(), Carbon::parse(NEXT_WEEK.' 08:00:00'));

    $cash = PaymentMethod::where('code', 'cash')->sole();
    DB::table('user_devices')->insert([
        'user_id' => $customer->id, 'device_id' => 'device-1', 'device_token' => 'token-1',
        'platform' => 'android', 'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('save_payment_methods')->insert([
        'user_id' => $customer->id, 'payment_method_id' => $cash->id, 'display_name' => 'Card',
        'token' => 'tok', 'expires_at' => now()->addYear(), 'is_default' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('service_reviews')->insert([
        'service_id' => $salon->service->id, 'user_id' => $customer->id, 'rating' => 5,
        'is_approved' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('notifications')->insert([
        'id' => (string) str()->uuid(), 'type' => 'test', 'notifiable_type' => $customer->getMorphClass(),
        'notifiable_id' => $customer->id, 'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('user_settings')->insert([
        'user_id' => $customer->id, 'key' => 'reminder_sms', 'value' => json_encode(true),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('password_reset_grants')->insert([
        'user_id' => $customer->id, 'token_hash' => str_repeat('a', 64), 'channel' => 0,
        'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    Storage::disk('public')->put('avatars/lina.jpg', 'image-bytes');
    File::create([
        'instance_type' => $customer->getMorphClass(), 'instance_id' => $customer->id,
        'name' => 'lina.jpg', 'path' => 'avatars/lina.jpg', 'disk' => 'public',
        'type' => 'profile_image', 'extension' => 'jpg',
    ]);

    return [
        'paid' => $paid->fresh(),
        'paidInvoice' => $paidInvoice->fresh(),
        'pastSplit' => $pastSplit->fresh(),
        'cancelled' => $cancelled->fresh(),
        'future' => $future->fresh(),
        'futureSplit' => $futureSplit->fresh(),
        'reminder' => $reminder,
        'appointmentIds' => Appointment::where('customer_id', $customer->id)->orderBy('id')->pluck('id')->all(),
        'invoiceIds' => Invoice::where('customer_id', $customer->id)->orderBy('id')->pluck('id')->all(),
        'paymentIds' => Payment::orderBy('id')->pluck('id')->all(),
    ];
}

/** Every column of every row, for "nothing changed" comparisons. */
function snapshotRows(string $table, array $ids): array
{
    return DB::table($table)->whereIn('id', $ids)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
}

it('deletes the account and keeps every booking, invoice and payment of a full history', function () {
    $history = buildCustomerHistory($this->customer);
    $token = $this->customer->createToken('mobile')->plainTextToken;

    // Sanity: the history really has the shapes this test is about.
    expect($history['appointmentIds'])->toHaveCount(7)
        ->and($history['pastSplit']->children()->count())->toBe(1)
        ->and($history['futureSplit']->children()->count())->toBe(1)
        ->and($history['paidInvoice']->status)->toBe(InvoiceStatus::PAID)
        ->and($history['paidInvoice']->invoice_number)->not->toBeNull();

    $paidInvoiceBefore = snapshotRows('invoices', [$history['paidInvoice']->id])[0];
    $paymentsBefore = snapshotRows('payments', $history['paymentIds']);
    $pastBefore = collect(snapshotRows('appointments', $history['appointmentIds']))
        ->keyBy('id')
        ->only([$history['paid']->id, $history['pastSplit']->id, $history['cancelled']->id]);

    $this->withToken($token)
        ->deleteJson('/api/profile', ['current_password' => 'password'])
        ->assertOk()
        ->assertJson(['success' => true]);

    // ── The account is gone for good ─────────────────────────────────────
    expect(User::withTrashed()->find($this->customer->id))->toBeNull();
    foreach ([
        'user_devices', 'save_payment_methods', 'service_reviews', 'user_settings',
        'password_reset_grants', 'appointment_reminders', 'refresh_tokens',
    ] as $table) {
        expect(DB::table($table)->where('user_id', $this->customer->id)->count())->toBe(0, $table);
    }
    expect(DB::table('notifications')->where('notifiable_id', $this->customer->id)->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $this->customer->id)->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $this->customer->id)->count())->toBe(0)
        ->and(File::where('instance_id', $this->customer->id)->where('type', 'profile_image')->count())->toBe(0);
    Storage::disk('public')->assertMissing('avatars/lina.jpg');

    // ── Every booking is still there, unlinked, with the contact on it ─────
    $after = Appointment::whereIn('id', $history['appointmentIds'])->get()->keyBy('id');
    expect($after)->toHaveCount(7);
    foreach ($after as $appointment) {
        expect($appointment->customer_id)->toBeNull()
            ->and($appointment->getRawOriginal('customer_name'))->toBe('Lina Hassan')
            ->and($appointment->getRawOriginal('customer_email'))->toBe('lina.hassan@gmail.com')
            ->and($appointment->getRawOriginal('customer_phone'))->toBe(DELETION_PHONE)
            ->and($appointment->customer_account_deleted_at)->not->toBeNull()
            ->and($appointment->services_record()->count())->toBeGreaterThan(0);
    }

    // History is untouched apart from the unlinking columns.
    $ignore = ['customer_id', 'customer_account_deleted_at', 'updated_at', 'customer_name', 'customer_email', 'customer_phone'];
    foreach ($pastBefore as $id => $before) {
        $now = (array) DB::table('appointments')->find($id);
        expect(array_diff_key($now, array_flip($ignore)))->toEqual(array_diff_key($before, array_flip($ignore)), "appointment {$id}");
    }
    expect($after[$history['paid']->id]->status)->toBe(AppointmentStatus::COMPLETED)
        ->and($after[$history['cancelled']->id]->cancellation_reason)->toBe('Changed my mind');

    // Upcoming bookings — the whole split group — are cancelled, and the group stays a group.
    $upcoming = [$history['future'], $history['futureSplit'], ...$history['futureSplit']->children()->get()];
    foreach ($upcoming as $appointment) {
        expect($after[$appointment->id]->status)->toBe(AppointmentStatus::USER_CANCELLED)
            ->and($after[$appointment->id]->cancellation_reason)->toBe('Account deleted by customer');
    }
    expect(Appointment::find($history['futureSplit']->id)->children()->count())->toBe(1);

    // ── Invoices and payments are all still there ────────────────────────
    expect(Invoice::whereIn('id', $history['invoiceIds'])->count())->toBe(count($history['invoiceIds']))
        ->and(Invoice::whereIn('id', $history['invoiceIds'])->whereNotNull('customer_id')->count())->toBe(0);

    $paidInvoiceAfter = snapshotRows('invoices', [$history['paidInvoice']->id])[0];
    expect(array_diff_key($paidInvoiceAfter, array_flip(['customer_id', 'updated_at'])))
        ->toEqual(array_diff_key($paidInvoiceBefore, array_flip(['customer_id', 'updated_at'])))
        ->and(snapshotRows('payments', $history['paymentIds']))->toEqual($paymentsBefore);

    // ── The old token is dead, and a second delete changes nothing ───────
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/profile')->assertUnauthorized();
    $this->withToken($token)->deleteJson('/api/profile', ['current_password' => 'password'])->assertUnauthorized();
});

it('keeps every staff screen working on the orphaned records', function () {
    $history = buildCustomerHistory($this->customer);
    Sanctum::actingAs($this->customer);
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertOk();

    // Printed invoice still renders with its number. (The default template has
    // no customer line, so the name is not on it — before or after.)
    $this->actingAs($this->manager)
        ->get(route('invoice.print', $history['paidInvoice']->id))
        ->assertOk()
        ->assertSee($history['paidInvoice']->invoice_number);

    // Booking ticket of the past split visit (the whole group prints) — it
    // reads the name and phone from the booking, so both are still on it.
    $this->actingAs($this->manager)
        ->get(route('appointment.print', $history['pastSplit']->id))
        ->assertOk()
        ->assertSee('Lina Hassan')
        ->assertSee(DELETION_PHONE);

    // The upcoming booking was cancelled by the deletion, and cancelled
    // bookings never print a ticket — same rule as before the deletion.
    $this->actingAs($this->manager)
        ->get(route('appointment.print', $history['future']->id))
        ->assertForbidden();

    // Staff dashboard on both days.
    foreach ([SalonFixture::DATE, NEXT_WEEK] as $date) {
        Livewire::actingAs($this->manager)
            ->test(StaffDashboard::class)
            ->set('selectedDate', $date)
            ->assertOk();
    }

    // Daily report for the day the payment was taken.
    $report = app(DailyReportService::class)->build('2026-09-07', '2026-09-10');
    expect(json_encode($report))->toContain('Lina Hassan');
});

it('keeps the admin panel working on the orphaned records', function () {
    $history = buildCustomerHistory($this->customer);
    Sanctum::actingAs($this->customer);
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertOk();
    $this->app['auth']->forgetGuards();

    Filament::setCurrentPanel('admin');
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SuperAdmin');
    // Sanctum::actingAs() above made `sanctum` the default guard for the rest
    // of this test; the panel reads `web`.
    $this->actingAs($admin, 'web');

    Livewire::test(ListAppointments::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Appointment::whereIn('id', $history['appointmentIds'])->whereNull('parent_appointment_id')->get());
    Livewire::test(ViewAppointment::class, ['record' => $history['paid']->id])
        ->assertOk();
});

it('starts a re-registration from scratch and never hands the old bookings back', function () {
    $history = buildCustomerHistory($this->customer);
    Sanctum::actingAs($this->customer);
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertOk();
    $this->app['auth']->forgetGuards();

    // Email and phone are free again.
    $this->postJson('/api/auth/register', [
        'first_name' => 'Lina',
        'last_name' => 'Hassan',
        'registration_method' => 'phone',
        'email' => 'lina.hassan@gmail.com',
        'phone' => DELETION_PHONE,
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertCreated();

    $again = User::where('email', 'lina.hassan@gmail.com')->sole();
    expect($again->id)->not->toBe($this->customer->id);

    // Proving the same number must not pull the old history back in.
    $again->update(['phone_verified_at' => now()]);
    expect(app(GuestBookingClaimService::class)->claimFor($again))->toBe(0)
        ->and(app(GuestBookingClaimService::class)->claimForAllVerifiedUsers())->toBe([])
        ->and(Appointment::where('customer_id', $again->id)->count())->toBe(0)
        ->and(Invoice::where('customer_id', $again->id)->count())->toBe(0)
        ->and(Appointment::whereIn('id', $history['appointmentIds'])->count())->toBe(7);
});

it('leaves a Google sign-in with the same email on a fresh account', function () {
    buildCustomerHistory($this->customer);
    Sanctum::actingAs($this->customer);
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertOk();

    $user = app(GoogleSignInService::class)->findOrCreateUser([
        'google_id' => 'google-123', 'email' => 'lina.hassan@gmail.com', 'email_verified' => true,
        'name' => 'Lina Hassan', 'given_name' => 'Lina', 'family_name' => 'Hassan', 'picture' => null,
    ]);

    expect($user->id)->not->toBe($this->customer->id)
        ->and($user->hasRole('customer'))->toBeTrue()
        ->and(Appointment::where('customer_id', $user->id)->count())->toBe(0);
});

it('changes nothing when the password is wrong or missing', function (array $body, int $status) {
    $history = buildCustomerHistory($this->customer);
    $appointmentsBefore = snapshotRows('appointments', $history['appointmentIds']);
    $invoicesBefore = snapshotRows('invoices', $history['invoiceIds']);

    Sanctum::actingAs($this->customer);
    $this->deleteJson('/api/profile', $body)->assertStatus($status);

    expect(User::find($this->customer->id))->not->toBeNull()
        ->and(snapshotRows('appointments', $history['appointmentIds']))->toEqual($appointmentsBefore)
        ->and(snapshotRows('invoices', $history['invoiceIds']))->toEqual($invoicesBefore)
        ->and(AppointmentReminder::find($history['reminder']->id))->not->toBeNull();
})->with([
    'wrong password' => [['current_password' => 'not-my-password'], 422],
    'no password' => [[], 422],
]);

it('refuses every staff role and leaves the account alone', function (string $role) {
    Role::findOrCreate($role, 'web');
    $staff = User::factory()->create(['is_active' => true]);
    $staff->syncRoles([$role]);

    Sanctum::actingAs($staff);
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertForbidden();

    expect(User::find($staff->id))->not->toBeNull();
})->with(['SuperAdmin', 'admin', 'manager', 'provider']);

it('requires a login', function () {
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertUnauthorized();
});

it('rolls everything back when the delete fails half way', function () {
    $history = buildCustomerHistory($this->customer);
    $appointmentsBefore = snapshotRows('appointments', $history['appointmentIds']);
    $invoicesBefore = snapshotRows('invoices', $history['invoiceIds']);

    // The very last step of the transaction blows up.
    User::forceDeleting(fn () => throw new RuntimeException('disk full'));

    Sanctum::actingAs($this->customer);
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertServerError();

    expect(User::find($this->customer->id))->not->toBeNull()
        ->and($this->customer->fresh()->hasRole('customer'))->toBeTrue()
        ->and(snapshotRows('appointments', $history['appointmentIds']))->toEqual($appointmentsBefore)
        ->and(snapshotRows('invoices', $history['invoiceIds']))->toEqual($invoicesBefore)
        ->and(DB::table('user_devices')->where('user_id', $this->customer->id)->count())->toBe(1)
        ->and(AppointmentReminder::find($history['reminder']->id))->not->toBeNull();
    Storage::disk('public')->assertExists('avatars/lina.jpg');
});

it('deletes cleanly while a split visit is under way', function () {
    $salon = $this->salon;
    $group = deletionBook($this->customer, SalonFixture::DATE, [
        [$salon->service, '10:00'],
        [$salon->secondService, '14:00'],
    ]);
    $child = $group->children()->sole();

    // 10:30 on the day: the first block has started, the second has not.
    Carbon::setTestNow(Carbon::parse(SalonFixture::DATE.' 10:30:00'));

    Sanctum::actingAs($this->customer);
    $this->deleteJson('/api/profile', ['current_password' => 'password'])->assertOk();

    expect($group->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and($child->fresh()->status)->toBe(AppointmentStatus::USER_CANCELLED)
        ->and($child->fresh()->parent_appointment_id)->toBe($group->id)
        ->and($group->fresh()->getRawOriginal('customer_name'))->toBe('Lina Hassan');
});
