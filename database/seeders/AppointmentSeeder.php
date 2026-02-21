<?php

namespace Database\Seeders;

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\User;
use App\Services\TaxCalculatorService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentSeeder extends Seeder
{

    public function run(): void
    {
        $customers = User::role('customer')->get();
        $providers = User::role('provider')->get();
        $services = Service::active()->get();

        if ($customers->isEmpty() || $providers->isEmpty() || $services->isEmpty()) {
            $this->command->warn('skipping appointment seeding  Missing customers, providers, or services');
            return;
        }

        $appointments = [
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(2),
                'status' => AppointmentStatus::COMPLETED,
                'payment_status' => PaymentStatus::PAID_ONLINE,
                'days_from_now' => -7,
            ],
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(1),
                'status' => AppointmentStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'days_from_now' => 3,
            ],
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(3),
                'status' => AppointmentStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'days_from_now' => 5,
            ],
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(1),
                'status' => AppointmentStatus::USER_CANCELLED,
                'payment_status' => PaymentStatus::REFUNDED,
                'days_from_now' => 2,
                'cancellation_reason' => 'Personal emergency',
            ],
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(2),
                'status' => AppointmentStatus::COMPLETED,
                'payment_status' => PaymentStatus::PAID_ONSTIE_CASH,
                'days_from_now' => -10,
            ],
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(1),
                'status' => AppointmentStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'days_from_now' => 1,
            ],
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(2),
                'status' => AppointmentStatus::COMPLETED,
                'payment_status' => PaymentStatus::PAID_ONSTIE_CARD,
                'days_from_now' => -3,
            ],
            [
                'customer' => $customers->random(),
                'provider' => $providers->random(),
                'services' => $services->random(4),
                'status' => AppointmentStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'days_from_now' => 7,
            ],
        ];

        foreach ($appointments as $appointmentData) {
            $this->createAppointment($appointmentData);
        }

        $this->command->info('Appointments seeded successfully (' . count($appointments) . ' appointments)');
    }


    private function createAppointment(array $data): void
    {

        $appointmentDate = now()->addDays($data['days_from_now'])->setTime(0, 0, 0);
        $startTime = $appointmentDate->copy()->setTime(10, 0, 0); // 10:00 AM
        $totalDuration = 0;
        $totalAmount = 0;


        foreach ($data['services'] as $service) {
            $totalDuration += $service->duration_minutes;
            $totalAmount += $service->display_price;
        }
        Log::info("appointment total amount before tax calculation: $totalAmount");

        $endTime = $startTime->copy()->addMinutes($totalDuration);



        $TaxCalculatorService=app(TaxCalculatorService::class);

        // استخدم tax_rate من الإعدادات لضمان التوافق مع InvoiceService
        $taxRate = (float) get_setting('tax_rate', 19);
        $tax_result = $TaxCalculatorService->extractTax($totalAmount, $taxRate);
            $net = $tax_result['net'];
            $taxAmount = $tax_result['tax'];
            Log::info("Calculated tax: $taxAmount, net amount: $net, tax rate: $taxRate%");
            Log::info('---');
        $appointment = Appointment::create([
            'number' => 'APT-' . strtoupper(uniqid()),
            'customer_id' => $data['customer']->id,
            'provider_id' => $data['provider']->id,
            'appointment_date' => $appointmentDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $totalDuration,
            'subtotal' => $net,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => $data['status'],
            'payment_status' => $data['payment_status'],
            'cancellation_reason' => $data['cancellation_reason'] ?? null,
            'cancelled_at' => $data['status']->value < 0 ? now() : null,
            'notes' => $this->generateAppointmentNotes($data),
            'payment_method' => $this->getPaymentMethod($data['payment_status']),
        ]);


        $sequence = 1;
        foreach ($data['services'] as $service) {
            DB::table('appointment_services')->insert([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'service_name' => $service->getNameIn('en'),
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->display_price,
                'sequence_order' => $sequence++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create invoice for completed and paid appointments
        $InvoiceService=app(\App\Services\InvoiceService::class);
        $InvoiceService->createDtaftInvoiceFromAppointment(
            appointment: $appointment,
            paymentType: PaymentStatus::PAID_ONSTIE_CASH->value,
            amountPaid: $appointment->total_amount,
            notes: null,
            adjustedDuration: null,
            amountIncludesTax: true
        );
        // $this->createInvoiceForAppointment($appointment, $data['services']->all());
    }


    private function generateAppointmentNotes(array $data): ?string
    {
        $notes = [
            'Regular customer, prefers morning appointments',
            'First time customer, requested specific stylist',
            'Returning customer, loyal to this provider',
            'Special occasion booking, needs extra attention',
            'VIP customer, provide premium service',
            null,
        ];

        return $notes[array_rand($notes)];
    }

    /**
     * Determine payment method based on payment status
     */
    private function getPaymentMethod(PaymentStatus $paymentStatus): ?string
    {
        return match ($paymentStatus) {
            PaymentStatus::PAID_ONLINE => 'Credit Card',
            PaymentStatus::PAID_ONSTIE_CASH => 'Cash',
            PaymentStatus::PAID_ONSTIE_CARD => 'Card Terminal',
            default => null,
        };
    }

    /**
     * Create invoice for completed and paid appointments
     */
    private function createInvoiceForAppointment(Appointment $appointment, array $services): void
    {
        // Only create invoices for completed and paid appointments
        if ($appointment->status !== AppointmentStatus::COMPLETED) {
            return;
        }

        $paidStatuses = [
            PaymentStatus::PAID_ONLINE,
            PaymentStatus::PAID_ONSTIE_CASH,
            PaymentStatus::PAID_ONSTIE_CARD,
        ];

        if (!in_array($appointment->payment_status, $paidStatuses)) {
            return;
        }

        // Tax rate is fixed at 19%
        $taxRate = 19.00;

        // Calculate totals with random decimal prices for testing precision
        $subtotal = 0;
        $itemsData = [];

        foreach ($services as $service) {
            // Generate random price with decimals (e.g., 45.73, 123.99)
            $basePrice = $service->display_price;
            $randomAdjustment = mt_rand(-500, 500) / 100; // Random adjustment between -5.00 and +5.00
            $unitPrice = max(5.00, round($basePrice + $randomAdjustment, 2)); // Minimum price 5.00

            // Add some cents variation for testing
            $cents = mt_rand(1, 99) / 100;
            $unitPrice = floor($unitPrice) + $cents;

            $quantity = 1;
            $itemSubtotal = round($unitPrice * $quantity, 2);
            $itemTaxAmount = round($itemSubtotal * ($taxRate / 100), 2);
            $itemTotalAmount = round($itemSubtotal + $itemTaxAmount, 2);

            $subtotal += $itemSubtotal;

            $itemsData[] = [
                'service' => $service,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'tax_rate' => $taxRate,
                'tax_amount' => $itemTaxAmount,
                'total_amount' => $itemTotalAmount,
            ];
        }

        // Calculate invoice totals
        $invoiceSubtotal = round($subtotal, 2);
        $invoiceTaxAmount = round($invoiceSubtotal * ($taxRate / 100), 2);
        $invoiceTotalAmount = round($invoiceSubtotal + $invoiceTaxAmount, 2);

        // Create the invoice
        $invoice = Invoice::create([
            'appointment_id' => $appointment->id,
            'customer_id' => $appointment->customer_id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'subtotal' => $invoiceSubtotal,
            'tax_amount' => $invoiceTaxAmount,
            'tax_rate' => $taxRate,
            'total_amount' => $invoiceTotalAmount,
            'status' => InvoiceStatus::PAID,
            'notes' => 'Auto-generated invoice from seeder for testing',
            'invoice_data' => [
                'generated_at' => now()->toDateTimeString(),
                'payment_method' => $appointment->payment_method,
                'provider_name' => $appointment->provider->full_name ?? 'Unknown',
                'customer_name' => $appointment->customer->full_name ?? 'Guest',
            ],
        ]);

        // Create invoice items
        foreach ($itemsData as $itemData) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $itemData['service']->getNameIn('en') . ' - ' . $itemData['service']->duration_minutes . ' min',
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'tax_rate' => $itemData['tax_rate'],
                'tax_amount' => $itemData['tax_amount'],
                'total_amount' => $itemData['total_amount'],
                'itemable_id' => $itemData['service']->id,
                'itemable_type' => Service::class,
            ]);
        }

        // Update appointment totals to match invoice (for consistency)

    }
}
