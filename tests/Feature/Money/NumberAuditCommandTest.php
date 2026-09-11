<?php

/**
 * MON-03 — the pre-flight diagnostic must find what the migration will repair,
 * and must never change a row itself.
 */

use App\Enum\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('reports a clean database and confirms the constraints are enforced', function () {
    $this->artisan('documents:number-audit')
        ->expectsOutputToContain('لا تكرارات')
        ->expectsOutputToContain('invoices_number_unique')
        ->assertSuccessful();
});

it('finds duplicate invoice numbers that predate the constraint', function () {
    $year = now()->format('Y');

    // Legacy data cannot be inserted while the constraint is live, so drop it
    // for the duration of this test. This reproduces exactly what a real
    // pre-migration database looks like: many invoices all numbered 000001,
    // because the generator read the newest ROW instead of the highest NUMBER.
    Schema::table('invoices', fn ($t) => $t->dropUnique('invoices_number_unique'));

    foreach ([1, 2, 3] as $i) {
        DB::table('invoices')->insert([
            'appointment_id' => null,
            'customer_id'    => null,
            'invoice_number' => "INV-{$year}-000001",
            'subtotal'       => '42.02',
            'tax_amount'     => '7.98',
            'tax_rate'       => '19',
            'total_amount'   => '50.00',
            'status'         => InvoiceStatus::PAID->value,
            'created_at'     => now()->subMinutes(10 - $i),
            'updated_at'     => now(),
        ]);
    }

    $this->artisan('documents:number-audit')
        ->expectsOutputToContain("INV-{$year}-000001")
        ->expectsOutputToContain('مشكلة فرادة')
        ->expectsOutputToContain('مفقود')      // the dropped index is reported missing
        ->assertSuccessful();

    // The audit is inert: all three rows still carry the duplicate number.
    expect(
        DB::table('invoices')->where('invoice_number', "INV-{$year}-000001")->count()
    )->toBe(3);
});

it('fails loudly when an appointment carries two finalized invoices', function () {
    $year = now()->format('Y');

    // The one case the migration refuses to resolve on its own: deleting an
    // issued document is the accountant's call, not a migration's.
    Schema::table('invoices', fn ($t) => $t->dropUnique('invoices_appointment_unique'));

    $appointment = App\Models\Appointment::create([
        'number'           => 'APT-TEST-000001',
        'provider_id'      => $this->salonProvider ?? App\Models\User::factory()->create()->id,
        'appointment_date' => now()->toDateString(),
        'start_time'       => now(),
        'end_time'         => now()->addHour(),
        'duration_minutes' => 60,
        'subtotal'         => '0',
        'tax_amount'       => '0',
        'total_amount'     => '0',
        'status'           => App\Enum\AppointmentStatus::PENDING,
        'created_status'   => 1,
    ]);

    foreach ([1, 2] as $i) {
        DB::table('invoices')->insert([
            'appointment_id' => $appointment->id,
            'customer_id'    => null,
            'invoice_number' => sprintf('INV-%s-%06d', $year, $i),
            'subtotal'       => '0',
            'tax_amount'     => '0',
            'tax_rate'       => '19',
            'total_amount'   => '0',
            'status'         => InvoiceStatus::PAID->value,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    $this->artisan('documents:number-audit')
        ->expectsOutputToContain('مُنهاة')
        ->assertFailed();

    // Nothing deleted.
    expect(Invoice::where('appointment_id', $appointment->id)->count())->toBe(2);
});
