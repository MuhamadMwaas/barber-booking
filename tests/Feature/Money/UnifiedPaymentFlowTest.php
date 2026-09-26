<?php

/**
 * MON-05 — every payment entry point delegates to one application operation.
 *
 * The UI-specific tests only need to prove delegation. These tests protect the
 * shared business result: one paid invoice, one method-linked Payment, one
 * normalized method code, and atomic completion of every covered appointment.
 */

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\InvoiceTemplate;
use App\Models\AppointmentService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\BookingService;
use App\Services\InvoiceFinalizationService;
use App\Services\InvoiceService;
use App\Services\InvoiceTemplate\DynamicFieldResolver;
use App\Services\TaxCalculatorService;
use Illuminate\Support\Carbon;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
});

afterEach(function () {
    Carbon::setTestNow();
});

function bookForUnifiedPayment(string $startTime = '10:00'): Appointment
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

it('records a complete cash payment with one consistent financial result', function () {
    $appointment = bookForUnifiedPayment();

    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $appointment,
        paymentMethod: 'cash',
        finalAmount: null,
        notes: 'Paid at the staff counter',
        source: 'staff_dashboard',
    );

    $payment = $invoice->payments()->sole();
    $cash = PaymentMethod::query()->where('code', 'cash')->sole();

    expect($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice->invoice_number)->not->toBeNull()
        ->and($invoice->discount_amount)->toEqual('0.00')
        ->and($invoice->invoice_data['payment_method_id'])->toBe($cash->id)
        ->and($invoice->invoice_data['payment_method_code'])->toBe('cash')
        ->and($invoice->invoice_data['finalization_method'])->toBe('staff_dashboard')
        ->and($invoice->invoice_data['tse_data']['tse_enabled'])->toBeFalse()
        ->and($payment->payment_method_id)->toBe($cash->id)
        ->and($payment->status)->toBe(PaymentStatus::PAID_ONSTIE_CASH)
        ->and($payment->type)->toBe(Payment::TYPE_FULL)
        ->and($payment->amount)->toEqual($invoice->total_amount)
        ->and($payment->subtotal)->toEqual($invoice->subtotal)
        ->and($payment->tax_amount)->toEqual($invoice->tax_amount)
        ->and($payment->payment_metadata['tse_enabled'])->toBeFalse()
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::COMPLETED)
        ->and($appointment->fresh()->payment_status)->toBe(PaymentStatus::PAID_ONSTIE_CASH)
        ->and($appointment->fresh()->payment_method)->toBe('cash');
});

it('treats a lower amount as a special customer price and records a full card payment', function () {
    $appointment = bookForUnifiedPayment();
    $card = PaymentMethod::query()->where('code', 'card')->sole();

    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $appointment,
        paymentMethod: $card->id,
        finalAmount: 80.00,
        source: 'filament_appointments',
    );

    $payment = $invoice->payments()->sole();

    expect($invoice->total_amount)->toEqual('80.00')
        ->and($invoice->discount_amount)->toEqual('20.00')
        ->and($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($payment->amount)->toEqual('80.00')
        ->and($payment->type)->toBe(Payment::TYPE_FULL)
        ->and($payment->payment_method_id)->toBe($card->id)
        ->and($payment->status)->toBe(PaymentStatus::PAID_ONSTIE_CARD)
        ->and($appointment->fresh()->payment_method)->toBe('card')
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::COMPLETED);
});

it('finalizes one invoice and completes the parent and every linked appointment together', function () {
    $parent = bookForUnifiedPayment();
    $split = app(TaxCalculatorService::class)->extractTax('50.00', '19', 2);

    $child = Appointment::create([
        'number' => 'APT-20260909-CHILD1',
        'parent_appointment_id' => $parent->id,
        'customer_id' => $parent->customer_id,
        'provider_id' => $this->salon->available->id,
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
        'service_id' => $this->salon->secondService->id,
        'service_name' => $this->salon->secondService->name,
        'duration_minutes' => 60,
        'price' => '50.00',
        'sequence_order' => 1,
    ]);

    // Calling from the child must still settle the invoice owned by the parent.
    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $child,
        paymentMethod: 'cash',
        source: 'provider_appointments',
    );

    expect($invoice->appointment_id)->toBe($parent->id)
        ->and($invoice->items()->count())->toBe(2)
        ->and($invoice->total_amount)->toEqual('150.00')
        ->and($invoice->payments()->count())->toBe(1)
        ->and($invoice->payments()->sole()->payment_metadata['covered_appointment_ids'])
        ->toContain($parent->id, $child->id)
        ->and($parent->fresh()->status)->toBe(AppointmentStatus::COMPLETED)
        ->and($child->fresh()->status)->toBe(AppointmentStatus::COMPLETED)
        ->and($parent->fresh()->payment_status)->toBe(PaymentStatus::PAID_ONSTIE_CASH)
        ->and($child->fresh()->payment_status)->toBe(PaymentStatus::PAID_ONSTIE_CASH);
});

it('treats a higher amount as the full price plus a tip outside the taxed totals', function () {
    $appointment = bookForUnifiedPayment();
    $expectedSplit = app(TaxCalculatorService::class)->extractTax('100.00', '19', 2);

    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: $appointment,
        paymentMethod: 'cash',
        finalAmount: 115.00,
        source: 'staff_dashboard',
    );

    $payment = $invoice->payments()->sole();

    // The sale and its VAT are exactly the full-price ones; the tip sits beside them.
    expect($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice->total_amount)->toEqual('100.00')
        ->and($invoice->discount_amount)->toEqual('0.00')
        ->and($invoice->tip_amount)->toEqual('15.00')
        ->and($invoice->subtotal)->toEqual($expectedSplit['net'])
        ->and($invoice->tax_amount)->toEqual($expectedSplit['tax'])
        ->and($invoice->invoice_data['tip_amount'])->toBe('15.00')
        ->and($payment->amount)->toEqual('100.00')
        ->and($payment->tip_amount)->toEqual('15.00')
        ->and($payment->subtotal)->toEqual($invoice->subtotal)
        ->and($payment->tax_amount)->toEqual($invoice->tax_amount)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::COMPLETED);
});

it('records no tip for a full or discounted payment', function (?float $amount, string $total, string $discount) {
    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: bookForUnifiedPayment(),
        paymentMethod: 'cash',
        finalAmount: $amount,
    );

    expect($invoice->total_amount)->toEqual($total)
        ->and($invoice->discount_amount)->toEqual($discount)
        ->and($invoice->tip_amount)->toEqual('0.00')
        ->and($invoice->payments()->sole()->tip_amount)->toEqual('0.00');
})->with([
    'full price' => [null, '100.00', '0.00'],
    'exact amount typed' => [100.00, '100.00', '0.00'],
    'discount' => [90.00, '90.00', '10.00'],
]);

it('prints the tip and the amount actually handed over on the receipt', function () {
    $invoice = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: bookForUnifiedPayment(),
        paymentMethod: 'cash',
        finalAmount: 105.00,
    );
    $resolver = new DynamicFieldResolver($invoice, new InvoiceTemplate(['language' => 'de']));

    expect($resolver->resolve('invoice.total'))->toBe('100.00')
        ->and($resolver->resolve('invoice.tip'))->toBe('+5.00')
        ->and($resolver->resolve('invoice.paid_amount'))->toBe('105.00');

    $untipped = app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
        appointment: bookForUnifiedPayment('12:00'),
        paymentMethod: 'cash',
    );
    $resolver = new DynamicFieldResolver($untipped, new InvoiceTemplate(['language' => 'de']));

    // Blank so the hide_when_empty tip line disappears on a receipt without a tip.
    expect($resolver->resolve('invoice.tip'))->toBe('')
        ->and($resolver->resolve('invoice.paid_amount'))->toBe('100.00');
});

it('reports payment errors in the language of the current user', function (string $locale, string $message) {
    app()->setLocale($locale);

    expect(fn () => app(InvoiceFinalizationService::class)
        ->finalizeAppointmentPayment(bookForUnifiedPayment(), 'cash', 0.00))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'german' => ['de', 'Der Zahlungsbetrag muss größer als null sein.'],
    'english' => ['en', 'The payment amount must be greater than zero.'],
    'arabic' => ['ar', 'يجب أن يكون مبلغ الدفع أكبر من صفر.'],
]);

it('rejects zero, inactive methods and non-onsite methods without partial writes', function (
    string $case
) {
    $appointment = bookForUnifiedPayment();
    $service = app(InvoiceFinalizationService::class);

    $method = match ($case) {
        'zero' => 'cash',
        'inactive' => PaymentMethod::create([
            'name' => 'Disabled Cash',
            'code' => 'offcash',
            'type' => PaymentMethod::TYPE_CASH,
            'status' => false,
        ])->id,
        'online' => PaymentMethod::create([
            'name' => 'Stripe',
            'code' => 'stripe',
            'type' => PaymentMethod::TYPE_STRIPE,
            'status' => true,
        ])->id,
    };

    $amount = match ($case) {
        'zero' => 0.00,
        default => null,
    };

    expect(fn () => $service->finalizeAppointmentPayment($appointment, $method, $amount))
        ->toThrow(InvalidArgumentException::class);

    expect($appointment->invoice->fresh()->status)->toBe(InvoiceStatus::DRAFT)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::PENDING)
        ->and(Payment::query()->count())->toBe(0);
})->with(['zero', 'inactive', 'online']);

it('has no alternate invoice finalization method left in InvoiceService', function () {
    expect(method_exists(InvoiceService::class, 'finalizeDraftInvoice'))->toBeFalse()
        ->and(method_exists(InvoiceService::class, 'createInvoiceFromAppointment'))->toBeFalse();
});
