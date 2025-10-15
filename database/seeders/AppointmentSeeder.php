<?php

namespace Database\Seeders;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        $subtotal = 0;


        foreach ($data['services'] as $service) {
            $totalDuration += $service->duration_minutes;
            $subtotal += $service->display_price;
        }


        $endTime = $startTime->copy()->addMinutes($totalDuration);


        $taxAmount = $subtotal * 0.05;
        $totalAmount = $subtotal + $taxAmount;


        $appointment = Appointment::create([
            'number' => 'APT-' . strtoupper(uniqid()),
            'customer_id' => $data['customer']->id,
            'provider_id' => $data['provider']->id,
            'appointment_date' => $appointmentDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $totalDuration,
            'subtotal' => $subtotal,
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
}
