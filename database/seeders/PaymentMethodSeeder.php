<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'CASH',
                'code' => 'cash',
                'type' => PaymentMethod::TYPE_CASH,
                'status' => true,
                'class' => "-",
            ],
            [
                'name' => 'Credit Card',
                'code' => 'credit',
                'type' => PaymentMethod::TYPE_CREDIT_CARD,
                'status' => true,
                'class' => "-",
            ],
            [
                'name' => 'Debit Card',
                'code' => 'debit',
                'type' => PaymentMethod::TYPE_DEBIT_CARD,
                'status' => true,
                'class' => "-",
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']], // Search by code
                $method // Data to update or create
            );
        }

        $this->command->info('Payment methods seeded successfully (' . count($paymentMethods) . ' methods)');
    }
}
