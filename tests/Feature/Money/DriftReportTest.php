<?php
use App\Enum\InvoiceStatus;
use App\Models\Invoice;

it('reports drifted invoices without modifying any row', function () {
    // A row written by the OLD truncating implementation: 50.00 gross stored
    // with net 42.01 / tax 7.99 instead of the correct 42.02 / 7.98.
    $bad = Invoice::create([
        'appointment_id' => null, 'customer_id' => null,
        'invoice_number' => 'INV-0001', 'subtotal' => '42.01',
        'tax_amount' => '7.99', 'tax_rate' => '19', 'total_amount' => '50.00',
        'status' => InvoiceStatus::PAID,
    ]);

    // A correct row.
    $good = Invoice::create([
        'appointment_id' => null, 'customer_id' => null,
        'invoice_number' => 'INV-0002', 'subtotal' => '42.02',
        'tax_amount' => '7.98', 'tax_rate' => '19', 'total_amount' => '50.00',
        'status' => InvoiceStatus::PAID,
    ]);

    $this->artisan('tax:drift-report')
        ->expectsOutputToContain('INV-0001')
        ->assertSuccessful();

    // The report must be inert.
    expect($bad->fresh()->tax_amount)->toEqual('7.99')
        ->and($good->fresh()->tax_amount)->toEqual('7.98');
});

it('finds nothing when every row is already correct', function () {
    Invoice::create([
        'appointment_id' => null, 'customer_id' => null,
        'invoice_number' => 'INV-0003', 'subtotal' => '42.02',
        'tax_amount' => '7.98', 'tax_rate' => '19', 'total_amount' => '50.00',
        'status' => InvoiceStatus::PAID,
    ]);

    $this->artisan('tax:drift-report')
        ->expectsOutputToContain('لا انحراف')
        ->assertSuccessful();
});
