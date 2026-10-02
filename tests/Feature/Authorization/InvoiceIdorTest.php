<?php

/**
 * AUTHZ-03 — invoice printing and viewing are authorized per invoice, not per login.
 *
 * Every print entry point used to accept any logged-in user and any invoice id.
 * These tests pin the rule from InvoicePolicy / AppointmentTicketPolicy:
 *
 *   customer  -> reads their OWN PAID receipts (read-only, never prints)
 *   provider  -> prints invoices of bookings they served or payments they took
 *   manager   -> prints anything (Invoice:print_others)
 */

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BookingService;
use App\Services\InvoiceFinalizationService;
use App\Services\TaxCalculatorService;
use Database\Seeders\InvoiceTemplateSeeder;
use Database\Seeders\PrinterSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->seed([PrinterSeeder::class, InvoiceTemplateSeeder::class]);

    foreach ([
        'Invoice:print', 'Invoice:print_others', 'InvoiceTemplate:view', 'PrintLog:view',
        'StaffDashboard:print_ticket', 'StaffDashboard:view_team',
    ] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    // Same grants as RoleSeeder / the 2026_09_27 migration.
    Role::findByName('provider', 'web')->givePermissionTo(['Invoice:print', 'StaffDashboard:print_ticket']);
    Role::findByName('manager', 'web')->givePermissionTo([
        'Invoice:print', 'Invoice:print_others', 'InvoiceTemplate:view', 'PrintLog:view',
        'StaffDashboard:print_ticket',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // The provider who served the booking, and a colleague who did not.
    $this->server = $this->salon->available;
    $this->colleague = $this->salon->doesNotOffer;
});

afterEach(function () {
    Carbon::setTestNow();
});

function paidInvoiceFor(User $customer, ?User $cashier = null, string $startTime = '10:00'): Invoice
{
    $salon = test()->salon;

    $appointment = app(BookingService::class)->createBooking($customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => $startTime,
        ]],
    ]);

    if ($cashier) {
        Auth::login($cashier);
    }

    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $appointment,
        paymentMethod: 'cash',
    );

    Auth::logout();

    return $invoice->fresh();
}

/** A second customer with a clean calendar (the fixture's `filler` is booked all day). */
function otherCustomer(): User
{
    $customer = User::factory()->create(['is_active' => true, 'email_verified_via_otp_at' => now()]);
    $customer->assignRole('customer');

    return $customer;
}

function staffUser(string $role, bool $active = true): User
{
    $user = User::factory()->create(['is_active' => $active]);
    $user->assignRole($role);

    return $user;
}

// ── Staff: web print ────────────────────────────────────────────────────────

it('lets the provider who served the booking print its invoice', function () {
    $invoice = paidInvoiceFor($this->salon->customer);

    $this->actingAs($this->server)
        ->get(route('invoice.print', $invoice))
        ->assertOk();

    expect($invoice->fresh()->print_count)->toBe(1);
});

it('forbids a colleague from printing an invoice for a booking they did not serve', function () {
    $invoice = paidInvoiceFor($this->salon->customer);

    $this->actingAs($this->colleague)
        ->get(route('invoice.print', $invoice))
        ->assertForbidden();

    // Refused before any work: no print log, no copy counter.
    expect($invoice->fresh()->print_count)->toBe(0)
        ->and($invoice->printLogs()->count())->toBe(0);
});

it('does not let StaffDashboard:edit_others stand in for Invoice:print_others', function () {
    Permission::findOrCreate('StaffDashboard:edit_others', 'web');
    $this->colleague->givePermissionTo('StaffDashboard:edit_others');
    $invoice = paidInvoiceFor($this->salon->customer);

    $this->actingAs($this->colleague)
        ->get(route('invoice.print', $invoice))
        ->assertForbidden();
});

it('lets the colleague who took the payment print that receipt', function () {
    $invoice = paidInvoiceFor($this->salon->customer, cashier: $this->colleague);

    expect($invoice->invoice_data['finalized_by_id'])->toBe($this->colleague->id);

    $this->actingAs($this->colleague)
        ->get(route('invoice.print', $invoice))
        ->assertOk();
});

it('lets a provider who served only a child segment print the group invoice', function () {
    $salon = $this->salon;
    $parent = app(BookingService::class)->createBooking($salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => '10:00',
        ]],
    ]);

    $split = app(TaxCalculatorService::class)->extractTax('50.00', '19', 2);
    $child = Appointment::create([
        'number' => 'APT-20260909-CHILD-IDOR',
        'parent_appointment_id' => $parent->id,
        'customer_id' => $parent->customer_id,
        'provider_id' => $this->colleague->id,
        'appointment_date' => SalonFixture::DATE,
        'start_time' => SalonFixture::DATE.' 12:00:00',
        'end_time' => SalonFixture::DATE.' 13:00:00',
        'duration_minutes' => 60,
        'subtotal' => $split['net'],
        'tax_amount' => $split['tax'],
        'total_amount' => $split['gross'],
        'status' => AppointmentStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'payment_method' => 'cash',
        'created_status' => 1,
    ]);
    AppointmentService::create([
        'appointment_id' => $child->id,
        'service_id' => $salon->secondService->id,
        'service_name' => $salon->secondService->name,
        'duration_minutes' => 60,
        'price' => '50.00',
        'sequence_order' => 1,
    ]);

    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $parent,
        paymentMethod: 'cash',
    );

    $this->actingAs($this->colleague)
        ->get(route('invoice.print', $invoice))
        ->assertOk();
});

it('lets a manager print any invoice', function () {
    $invoice = paidInvoiceFor($this->salon->customer);

    $this->actingAs(staffUser('manager'))
        ->get(route('invoice.print', $invoice))
        ->assertOk();
});

it('locks a deactivated provider out even for their own booking', function () {
    $invoice = paidInvoiceFor($this->salon->customer);
    $this->server->update(['is_active' => false]);

    $this->actingAs($this->server->fresh())
        ->get(route('invoice.print', $invoice))
        ->assertForbidden();
});

it('forbids a customer with a web session from the staff print route, even for their own invoice', function () {
    $invoice = paidInvoiceFor($this->salon->customer);

    $this->actingAs($this->salon->customer)
        ->get(route('invoice.print', $invoice))
        ->assertForbidden();
});

// ── Staff: batch print ──────────────────────────────────────────────────────

it('refuses the whole batch when one invoice is not the caller\'s', function () {
    $own = paidInvoiceFor($this->salon->customer, startTime: '10:00');
    $foreign = paidInvoiceFor($this->salon->customer, startTime: '11:00');
    $foreign->appointment->update(['provider_id' => $this->colleague->id]);

    $this->actingAs($this->server)
        ->get(route('invoices.print-batch', ['invoice_ids' => "{$own->id},{$foreign->id}"]))
        ->assertForbidden();

    expect($own->fresh()->print_count)->toBe(0);
});

it('rejects an empty or oversized batch instead of printing', function () {
    $manager = staffUser('manager');

    $this->actingAs($manager)
        ->get(route('invoices.print-batch', ['invoice_ids' => '']))
        ->assertSessionHasErrors('invoice_ids');

    $this->actingAs($manager)
        ->get(route('invoices.print-batch', ['invoice_ids' => implode(',', range(1, 51))]))
        ->assertSessionHasErrors('invoice_ids');
});

// ── API print endpoints are staff-only ──────────────────────────────────────

it('forbids a customer token from the API print endpoints, even for their own invoice', function () {
    $invoice = paidInvoiceFor($this->salon->customer);
    Sanctum::actingAs($this->salon->customer);

    $this->postJson(route('api.invoice.print', $invoice))->assertForbidden();
    $this->getJson(route('api.invoice.print-url', $invoice))->assertForbidden();
    $this->postJson(route('api.invoices.print-batch'), ['invoice_ids' => [$invoice->id]])->assertForbidden();
    $this->getJson(route('api.print.logs'))->assertForbidden();
    $this->getJson(route('api.print.statistics'))->assertForbidden();

    expect($invoice->fresh()->print_count)->toBe(0);
});

// ── Customer: own receipts ──────────────────────────────────────────────────

it('lists only the customer\'s own paid invoices', function () {
    $own = paidInvoiceFor($this->salon->customer, startTime: '10:00');
    $someoneElses = paidInvoiceFor(otherCustomer(), startTime: '11:00');

    Sanctum::actingAs($this->salon->customer);

    $response = $this->getJson(route('my.invoices.index'))->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$own->id])
        ->and($response->json('data.0'))->not->toHaveKeys(['invoice_data', 'notes', 'print_count'])
        ->and($response->json('data.0.view_url'))->toBeString();

    expect($someoneElses->id)->not->toBe($own->id);
});

it('hides draft invoices from the customer', function () {
    app(BookingService::class)->createBooking($this->salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '10:00',
        ]],
    ]);

    Sanctum::actingAs($this->salon->customer);

    $this->getJson(route('my.invoices.index'))->assertOk()->assertJsonCount(0, 'data');
});

it('answers 404 — not 403 — for another customer\'s invoice', function () {
    $someoneElses = paidInvoiceFor(otherCustomer(), startTime: '11:00');
    Sanctum::actingAs($this->salon->customer);

    $this->getJson(route('my.invoices.show', $someoneElses->id))->assertNotFound();
});

it('opens the signed HTML copy read-only and marked as a copy', function () {
    $invoice = paidInvoiceFor($this->salon->customer);
    Sanctum::actingAs($this->salon->customer);

    $url = $this->getJson(route('my.invoices.show', $invoice->id))->assertOk()->json('data.view_url');

    // No session and no token: the signature is the credential.
    Auth::forgetGuards();
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toMatch('/\((Kopie|COPY)\)/')
        ->and($invoice->fresh()->print_count)->toBe(0)
        ->and($invoice->printLogs()->count())->toBe(0);
});

it('rejects a tampered or re-targeted signed link', function () {
    $own = paidInvoiceFor($this->salon->customer, startTime: '10:00');
    $foreign = paidInvoiceFor(otherCustomer(), startTime: '11:00');
    Sanctum::actingAs($this->salon->customer);

    $url = $this->getJson(route('my.invoices.show', $own->id))->json('data.view_url');
    Auth::forgetGuards();

    $this->get(str_replace("/my/invoices/{$own->id}/", "/my/invoices/{$foreign->id}/", $url))
        ->assertForbidden();
});

it('stops a signed link working once the invoice is no longer the customer\'s', function () {
    $invoice = paidInvoiceFor($this->salon->customer);
    Sanctum::actingAs($this->salon->customer);

    $url = $this->getJson(route('my.invoices.show', $invoice->id))->json('data.view_url');
    Auth::forgetGuards();

    $invoice->update(['customer_id' => $this->salon->filler->id]);

    $this->get($url)->assertNotFound();
});

// ── Appointment ticket ──────────────────────────────────────────────────────

it('guards the appointment ticket like the dashboard board does', function () {
    $invoice = paidInvoiceFor($this->salon->customer);
    $appointment = $invoice->appointment;

    $this->actingAs($this->colleague)
        ->get(route('appointment.print', $appointment))
        ->assertForbidden();

    $this->colleague->givePermissionTo('StaffDashboard:view_team');

    $this->actingAs($this->colleague->fresh())
        ->get(route('appointment.print', $appointment))
        ->assertOk();

    $this->actingAs($this->salon->customer)
        ->get(route('appointment.print', $appointment))
        ->assertForbidden();
});

// ── Template preview ────────────────────────────────────────────────────────

it('no longer serves the template preview to anonymous visitors', function () {
    $template = \App\Models\InvoiceTemplate::query()->firstOrFail();

    $this->get(route('invoice-template.preview', $template))->assertRedirect();

    $this->actingAs(staffUser('manager'))
        ->get(route('invoice-template.preview', $template))
        ->assertOk();
});
