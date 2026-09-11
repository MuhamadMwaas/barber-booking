<?php

/**
 * MON-03 — one invoice, one number, issued once.
 *
 * The headline bug was NOT a race condition, which is how the audit framed it.
 * `DocumentNumberGenerator` read the newest invoice ROW (`ORDER BY id DESC`)
 * instead of the highest NUMBER. Every booking creates a DRAFT invoice whose
 * `invoice_number` is NULL, so the newest row was almost always a draft, the
 * suffix parsed as 0, and **every** invoice was issued as `INV-YYYY-000001` —
 * deterministically, single-threaded, with no concurrency at all.
 *
 * On top of that the generator committed its own transaction before returning,
 * so the lock it took was released before the caller wrote the number, and
 * the old finalization path checked `status !== DRAFT` outside its transaction
 * with no row lock.
 *
 * ⚠️ These tests run on SQLite, where `SELECT ... FOR UPDATE` is a no-op. They
 * verify the *logic* — that the guard is re-evaluated after a re-read inside
 * the transaction, that numbers advance from a counter rather than a table
 * scan, and that the database itself refuses a duplicate. True concurrency
 * behaviour depends on the MySQL row lock and cannot be asserted here.
 */

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Exceptions\InvoiceAlreadyFinalizedException;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\DocumentNumberGenerator;
use App\Services\InvoiceFinalizationService;
use App\Services\InvoiceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeDraft(array $overrides = []): Invoice
{
    return Invoice::create($overrides + [
        'appointment_id' => null,
        'customer_id' => null,
        'invoice_number' => null,
        'subtotal' => '42.02',
        'tax_amount' => '7.98',
        'tax_rate' => '19',
        'total_amount' => '50.00',
        'status' => InvoiceStatus::DRAFT,
    ]);
}

/** Book an appointment through the real flow; it arrives with a DRAFT invoice. */
function bookOne(string $startTime = '10:00'): Appointment
{
    $salon = test()->salon;

    return app(BookingService::class)->createBooking($salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => $startTime,
        ]],
    ]);
}

// ── 1. The deterministic duplication is gone ─────────────────────────────────

it('issues a different, ascending number for every invoice', function () {
    $numbers = [];

    // The exact shape that broke: a DRAFT with a NULL number exists (and is the
    // newest row) each time a number is generated.
    for ($i = 0; $i < 6; $i++) {
        $draft = makeDraft();

        DB::transaction(function () use ($draft, &$numbers) {
            $number = Invoice::generateInvoiceNumber();
            $draft->update(['invoice_number' => $number, 'status' => InvoiceStatus::PAID]);
            $numbers[] = $number;
        });
    }

    $year = now()->format('Y');

    expect($numbers)->toBe([
        "INV-{$year}-000001",
        "INV-{$year}-000002",
        "INV-{$year}-000003",
        "INV-{$year}-000004",
        "INV-{$year}-000005",
        "INV-{$year}-000006",
    ])->and(array_unique($numbers))->toHaveCount(6);
});

it('is not confused by a newer draft landing between two finalizations', function () {
    $year = now()->format('Y');

    $a = makeDraft();
    DB::transaction(fn () => $a->update([
        'invoice_number' => Invoice::generateInvoiceNumber(),
        'status' => InvoiceStatus::PAID,
    ]));

    // Another customer books: a DRAFT with a NULL number becomes the newest row.
    // This alone used to reset the sequence to 000001.
    makeDraft(['total_amount' => '30.00']);

    $b = makeDraft(['total_amount' => '70.00']);
    DB::transaction(fn () => $b->update([
        'invoice_number' => Invoice::generateInvoiceNumber(),
        'status' => InvoiceStatus::PAID,
    ]));

    expect($a->fresh()->invoice_number)->toBe("INV-{$year}-000001")
        ->and($b->fresh()->invoice_number)->toBe("INV-{$year}-000002");
});

it('continues past numbers that already exist instead of restarting at one', function () {
    $year = now()->format('Y');

    // Simulate legacy data plus a counter that knows about it.
    makeDraft(['invoice_number' => "INV-{$year}-000041", 'status' => InvoiceStatus::PAID]);
    DB::table('document_counters')->where('series', 'invoice')
        ->update(['period' => $year, 'current' => 41]);

    $next = DB::transaction(fn () => Invoice::generateInvoiceNumber());

    expect($next)->toBe("INV-{$year}-000042");
});

// ── 2. The number is reserved, not merely read ───────────────────────────────

// NOTE: the "refuses to issue a number outside a transaction" guard is asserted
// in tests/Unit/DocumentNumberGeneratorGuardTest.php. It cannot be tested here:
// RefreshDatabase wraps every test in a transaction, so DB::transactionLevel()
// is already 1 and the guard correctly permits the call.

it('leaves no gap when the surrounding transaction rolls back', function () {
    $year = now()->format('Y');
    $before = DocumentNumberGenerator::peek('invoice')['current'];

    try {
        DB::transaction(function () {
            Invoice::generateInvoiceNumber();
            throw new RuntimeException('payment failed after the number was taken');
        });
    } catch (RuntimeException) {
        // expected
    }

    // The counter rolled back with the transaction, so the number was not burnt.
    expect(DocumentNumberGenerator::peek('invoice')['current'])->toBe($before);

    $next = DB::transaction(fn () => Invoice::generateInvoiceNumber());
    expect($next)->toBe(sprintf('INV-%s-%06d', $year, $before + 1));
});

it('resets the sequence on a new year without colliding', function () {
    Carbon::setTestNow('2026-12-31 23:00:00');
    $lastOf2026 = DB::transaction(fn () => Invoice::generateInvoiceNumber());

    Carbon::setTestNow('2027-01-01 09:00:00');
    $firstOf2027 = DB::transaction(fn () => Invoice::generateInvoiceNumber());
    $secondOf2027 = DB::transaction(fn () => Invoice::generateInvoiceNumber());

    expect($lastOf2026)->toBe('INV-2026-000001')
        ->and($firstOf2027)->toBe('INV-2027-000001')
        ->and($secondOf2027)->toBe('INV-2027-000002')
        // The year lives inside the number, so the reset cannot produce a clash.
        ->and($firstOf2027)->not->toBe($lastOf2026);
});

// ── 3. The database refuses a duplicate ──────────────────────────────────────

it('rejects a duplicate invoice number at the database level', function () {
    $year = now()->format('Y');
    makeDraft(['invoice_number' => "INV-{$year}-000001", 'status' => InvoiceStatus::PAID]);

    expect(fn () => makeDraft([
        'invoice_number' => "INV-{$year}-000001",
        'status' => InvoiceStatus::PAID,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('still allows many drafts, because a draft has no number', function () {
    // NULL is not a value: multiple NULLs coexist under a unique index. A draft
    // is not an issued document, so it must not consume a number.
    makeDraft();
    makeDraft();
    makeDraft();

    expect(Invoice::whereNull('invoice_number')->count())->toBe(3);
});

it('rejects a second invoice on the same appointment', function () {
    $appointment = bookOne();

    expect(fn () => makeDraft(['appointment_id' => $appointment->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects a duplicate appointment number', function () {
    $appointment = bookOne();

    expect(fn () => Appointment::create([
        'number' => $appointment->number,
        'provider_id' => $this->salon->available->id,
        'appointment_date' => SalonFixture::DATE,
        'start_time' => SalonFixture::DATE.' 15:00:00',
        'end_time' => SalonFixture::DATE.' 16:00:00',
        'duration_minutes' => 60,
        'subtotal' => '0',
        'tax_amount' => '0',
        'total_amount' => '0',
        'status' => AppointmentStatus::PENDING,
        'created_status' => 1,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('rejects a duplicate provider_service pair', function () {
    // Two rows for one pair mean an UNDEFINED price: getEffectivePrice() and
    // getProviderServicePricing() both use ->first(), so the app could quote 30
    // and charge 45.
    expect(fn () => DB::table('provider_service')->insert([
        'provider_id' => $this->salon->available->id,
        'service_id' => $this->salon->service->id,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

// ── 4. The double-click cannot double-charge ─────────────────────────────────

it('finalizes once and reports the second attempt as already finalized', function () {
    $appointment = bookOne();
    $finalization = app(InvoiceFinalizationService::class);

    $first = $finalization->finalizeAppointmentPayment($appointment, 'cash');

    expect($first->invoice_number)->not->toBeNull()
        ->and($first->status)->toBe(InvoiceStatus::PAID);

    // The second request of a double-click, or a retry after a network drop.
    // It used to sail past the unlocked status check and issue a duplicate
    // number plus a second payment row for the same money.
    $caught = null;
    try {
        $finalization->finalizeAppointmentPayment($appointment->fresh(), 'cash');
    } catch (InvoiceAlreadyFinalizedException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->invoice->invoice_number)->toBe($first->invoice_number);

    // The load-bearing assertion: ONE payment row, not two.
    $payments = Payment::where('paymentable_id', $first->id)
        ->where('paymentable_type', Invoice::class)
        ->get();

    expect($payments)->toHaveCount(1)
        ->and($payments->first()->amount)->toEqual($first->total_amount);

    // And exactly one invoice carrying that number.
    expect(Invoice::where('invoice_number', $first->invoice_number)->count())->toBe(1);
});

it('gives every payment record its own sequential number', function () {
    $year = now()->format('Y');
    $numbers = [];

    foreach (['10:00', '12:00', '14:00'] as $slot) {
        $appointment = bookOne($slot);
        $invoice = app(InvoiceFinalizationService::class)
            ->finalizeAppointmentPayment($appointment, 'cash');

        $numbers[] = Payment::where('paymentable_id', $invoice->id)->firstOrFail()->payment_number;
    }

    // Was `PAY-Ymd-` + 6 hex chars of uniqid(): microsecond-based, so two calls
    // in the same microsecond collided, with no retry loop and no constraint.
    expect($numbers)->toBe([
        "PAY-{$year}-000001",
        "PAY-{$year}-000002",
        "PAY-{$year}-000003",
    ]);
});

// ── 5. One invoice per appointment, in the code as well as the schema ────────

it('the canonical payment path upgrades the existing draft instead of inserting a second invoice', function () {
    $appointment = bookOne();
    $draftId = $appointment->invoice->id;

    // Every UI now calls the same service. The former provider relation path
    // inserted/finalized independently and could leave a second invoice.
    $invoice = app(InvoiceFinalizationService::class)
        ->finalizeAppointmentPayment($appointment->fresh(), 'cash');

    expect($invoice->id)->toBe($draftId)
        ->and($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice->invoice_number)->not->toBeNull()
        ->and(Invoice::where('appointment_id', $appointment->id)->count())->toBe(1);
});

it('refuses to invoice a cancelled appointment', function () {
    $appointment = bookOne();
    $appointment->update(['status' => AppointmentStatus::ADMIN_CANCELLED]);

    // The guard read `$appointment->status->value === 'admin_cancelled'`, but
    // AppointmentStatus is INT-backed (-2), so it never matched — the check was
    // dead code and a cancelled appointment could be invoiced.
    expect(fn () => app(InvoiceService::class)->validateInvoiceCreation($appointment->fresh()))
        ->toThrow(Exception::class, 'ملغي');
});

it('refuses to invoice an appointment whose invoice is already finalized', function () {
    $appointment = bookOne();

    $invoiceService = app(InvoiceService::class);
    app(InvoiceFinalizationService::class)
        ->finalizeAppointmentPayment($appointment, 'cash');

    // Used to check for a PAID invoice only — so a PENDING, PARTIALLY_PAID or
    // REFUNDED invoice let a second document through.
    expect(fn () => $invoiceService->validateInvoiceCreation($appointment->fresh()))
        ->toThrow(Exception::class, 'مُنهاة');
});
