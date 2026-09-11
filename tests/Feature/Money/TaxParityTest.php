<?php

/**
 * MON-01 — ONE transaction must carry ONE VAT figure, everywhere.
 *
 * The bug this guards: the project had SEVEN implementations of the same
 * reverse-tax equation at three different internal precisions. The booking
 * layer computed at scale 6 (correct); TaxCalculatorService divided at scale 2,
 * and `bcdiv` TRUNCATES instead of rounding — so a 50.00 EUR service produced
 * 7.98 VAT in `appointments` and 7.99 in `invoices`, for the same money. The
 * German VAT return is built on `invoices.tax_amount`, and the printed receipt
 * showed a figure the confirmation email contradicted.
 *
 * Why the old test suite missed it: every existing assertion was either on
 * 119.00 (which divides exactly by 1.19, so no truncation shows) or on the
 * SELF-CONSISTENCY of one layer — `net + tax == gross`. That identity holds
 * happily with a wrong net: 42.01 + 7.99 == 50.00. Nothing asked whether the
 * net was *arithmetically correct*, and nothing compared two layers.
 *
 * So the tests here assert the two things that were missing:
 *   1. the net is the correctly ROUNDED quotient, not the truncated one;
 *   2. every layer that writes money agrees with every other layer.
 */

use App\Enum\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Service;
use App\Services\BookingService;
use App\Services\InvoiceFinalizationService;
use App\Services\InvoiceService;
use App\Services\TaxCalculatorService;
use Illuminate\Support\Carbon;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->tax = app(TaxCalculatorService::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * The reference answer, computed independently of the application code:
 * round(gross / (1 + rate/100)) to 2 dp, with tax as the remainder.
 *
 * Deliberately uses PHP's own round() on a float rather than any project
 * helper — if the calculator and this disagree, the calculator is wrong.
 */
function expectedSplit(string $gross, string $rate): array
{
    $net = round(((float) $gross) / (1 + ((float) $rate) / 100), 2);

    return [
        'net' => number_format($net, 2, '.', ''),
        'tax' => number_format(((float) $gross) - $net, 2, '.', ''),
    ];
}

/**
 * The prices that actually diverged under the truncating implementation.
 *
 * 100.00 is deliberately EXCLUDED from the divergent set: 100 / 1.19 = 84.0336
 * truncates and rounds to the same 84.03, so it cannot detect this bug — and it
 * happens to be the fixture's default service price, which is part of why the
 * suite never caught it.
 */
dataset('divergent_prices', [
    '19.00' => ['19.00', '15.97', '3.03'],
    '25.00' => ['25.00', '21.01', '3.99'],
    '50.00' => ['50.00', '42.02', '7.98'],
    '99.99' => ['99.99', '84.03', '15.96'],
    '19.99' => ['19.99', '16.80', '3.19'],
    '29.90' => ['29.90', '25.13', '4.77'],
    '45.00' => ['45.00', '37.82', '7.18'],
    '15.00' => ['15.00', '12.61', '2.39'],
]);

// ── 1. The calculator rounds; it does not truncate ───────────────────────────

it('rounds the net instead of truncating it', function (string $gross, string $net, string $tax) {
    $result = $this->tax->extractTax($gross, '19', 2);

    // These are the exact figures the truncating version got wrong by a cent.
    expect($result['net'])->toBe($net)
        ->and($result['tax'])->toBe($tax)
        ->and(bcadd($result['net'], $result['tax'], 2))->toBe($gross);
})->with('divergent_prices');

it('matches an independent float reference across many prices and rates', function () {
    $prices = ['0.01', '1.00', '7.50', '12.50', '15.00', '19.00', '19.99', '25.00',
        '29.90', '35.00', '45.00', '50.00', '60.00', '88.88', '99.99', '120.75', '9999.99'];

    foreach (['19', '7', '16', '0'] as $rate) {
        foreach ($prices as $gross) {
            $result = $this->tax->extractTax($gross, $rate, 2);
            $expected = expectedSplit($gross, $rate);

            expect($result['net'])->toBe($expected['net'], "net wrong for {$gross} @ {$rate}%")
                ->and($result['tax'])->toBe($expected['tax'], "tax wrong for {$gross} @ {$rate}%");
        }
    }
});

it('honours fractional tax rates instead of truncating them to whole percent', function () {
    // bcdiv('19.5', '100', 2) === '0.19' — the second decimal was destroyed, so
    // 19.5% was silently computed as 19%. This is a ~0.35 EUR error per 100,
    // two orders of magnitude worse than the rounding bug.
    $result = $this->tax->extractTax('100.00', '19.5', 2);

    expect($result['net'])->toBe('83.68')
        ->and($result['tax'])->toBe('16.32')
        ->and($result['net'])->not->toBe('84.03'); // what the 19% factor produced
});

it('does not truncate a high-precision input before calculating', function () {
    // normalizeAmount() used to run bcadd($amount, '0', 2), so '33.333333'
    // reached the equation as '33.33' — the input itself was destroyed.
    $result = $this->tax->extractTax('33.333333', '0', 6);

    expect($result['net'])->toBe('33.333333');
});

it('never leaves bcmath global scale altered behind it', function () {
    // extractTax used to call bcscale(), which is REQUEST-WIDE state: it
    // silently changed the default precision of every later bcmath call in the
    // same request, including money maths in other layers (MON-06).
    bcscale(7);
    $this->tax->extractTax('50.00', '19', 2);

    expect(bcdiv('10', '4'))->toBe('2.5000000');
});

// ── 2. The layers agree with each other ──────────────────────────────────────

it('writes the same VAT to appointments, invoices, invoice items and payments', function (
    string $gross,
    string $net,
    string $tax
) {
    $salon = $this->salon;

    $salon->service->update(['price' => $gross, 'discount_price' => null]);

    $appointment = app(BookingService::class)->createBooking($salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => '10:00',
        ]],
    ]);

    // Layer 1 — the booking row.
    expect($appointment->subtotal)->toEqual($net)
        ->and($appointment->tax_amount)->toEqual($tax)
        ->and($appointment->total_amount)->toEqual($gross);

    // Layer 2 — the draft invoice copied from it.
    $invoice = $appointment->invoice;
    expect($invoice->status)->toBe(InvoiceStatus::DRAFT)
        ->and($invoice->tax_amount)->toEqual($tax);

    // Layer 3 — the canonical staff payment operation rebuilds the items,
    // applies the final amount, issues the invoice and creates Payment.
    $invoice = app(InvoiceFinalizationService::class)
        ->finalizeAppointmentPayment($appointment, 'cash');

    expect($invoice->subtotal)->toEqual($net, 'invoice net drifted from the booking')
        ->and($invoice->tax_amount)->toEqual($tax, 'invoice VAT drifted from the booking')
        ->and($invoice->total_amount)->toEqual($gross)
        ->and($invoice->discount_amount)->toEqual('0.00');

    $item = $invoice->items()->firstOrFail();
    expect($item->tax_amount)->toEqual($tax)
        ->and($item->total_amount)->toEqual($gross);

    // Layer 4 — Payment copies the exact reconciled invoice split; it does not
    // recalculate VAT in another implementation.
    $payment = Payment::where('paymentable_id', $invoice->id)
        ->where('paymentable_type', Invoice::class)
        ->firstOrFail();

    expect($payment->tax_amount)->toEqual($tax, 'payment VAT disagrees with the invoice it settles')
        ->and($payment->subtotal)->toEqual($net);

    // The whole point, stated once: every table holding this transaction's VAT
    // holds the SAME number.
    $appointment->refresh();
    expect($appointment->tax_amount)->toEqual($invoice->fresh()->tax_amount)
        ->and($payment->tax_amount)->toEqual($invoice->fresh()->tax_amount);
})->with('divergent_prices');

it('keeps the booking and the invoice in step under a discount', function () {
    $salon = $this->salon;
    $salon->service->update(['price' => '50.00', 'discount_price' => null]);

    $appointment = app(BookingService::class)->createBooking($salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => '10:00',
        ]],
    ]);

    $invoiceService = app(InvoiceService::class);
    $invoice = $invoiceService->rebuildAggregatedInvoice($appointment);

    // Staff charge 45.00 instead of 50.00.
    $invoice = $invoiceService->applyFinalAmount($invoice, 45.00);

    $expected = expectedSplit('45.00', '19');

    expect($invoice->total_amount)->toEqual('45.00')
        ->and($invoice->discount_amount)->toEqual('5.00')
        ->and($invoice->subtotal)->toEqual($expected['net'])
        ->and($invoice->tax_amount)->toEqual($expected['tax'])
        ->and(bcadd((string) $invoice->subtotal, (string) $invoice->tax_amount, 2))
        ->toBe('45.00');
});

// ── 3. The gross is never re-derived from the net ────────────────────────────

it('does not shave a cent off an invoice item when it is saved again', function (
    string $gross,
    string $net,
    string $tax
) {
    $invoice = Invoice::create([
        'appointment_id' => null,
        'customer_id' => null,
        'invoice_number' => null,
        'subtotal' => $net,
        'tax_amount' => $tax,
        'tax_rate' => '19',
        'total_amount' => $gross,
        'status' => InvoiceStatus::DRAFT,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'description' => 'Colour',
        'quantity' => 1,
        'unit_price' => $net,
        'tax_rate' => '19',
        'tax_amount' => $tax,
        'total_amount' => $gross,
        'itemable_id' => $this->salon->service->id,
        'itemable_type' => Service::class,
    ]);
    $item->save();

    expect($item->total_amount)->toEqual($gross, 'gross changed on first save');

    // Touching an unrelated field must not move the money. The observer used to
    // rebuild the gross with addTax(net), and net -> gross is lossy: 42.01
    // becomes 49.99, so every save quietly shrank the invoice by a cent — even
    // one already finalised, signed and printed.
    $item->description = 'Colour (touched)';
    $item->save();

    expect($item->fresh()->total_amount)->toEqual($gross, 'gross was re-derived from the net and lost a cent')
        ->and(bcadd((string) $item->fresh()->unit_price, (string) $item->fresh()->tax_amount, 2))
        ->toBe($gross);
})->with('divergent_prices');

it('still derives the gross forward when an item carries only a net price', function () {
    // The fallback must stay: an item built from a net unit price with no gross
    // yet has to get one.
    $invoice = Invoice::create([
        'appointment_id' => null,
        'customer_id' => null,
        'invoice_number' => null,
        'subtotal' => '0',
        'tax_amount' => '0',
        'tax_rate' => '19',
        'total_amount' => '0',
        'status' => InvoiceStatus::DRAFT,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'description' => 'Net-only line',
        'quantity' => 2,
        'unit_price' => '10.00',
        'tax_rate' => '19',
        'itemable_id' => $this->salon->service->id,
        'itemable_type' => Service::class,
    ]);
    $item->save();

    // 20.00 net + 19% = 23.80 gross
    expect($item->total_amount)->toEqual('23.80')
        ->and($item->tax_amount)->toEqual('3.80');
});
