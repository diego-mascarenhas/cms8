<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentTypes = [
            [
                'id' => 1,
                'name' => 'Efectivo',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Transferencia',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Tarjeta de crédito',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 4,
                'name' => 'PayPal',
                'discount' => 0.00,
                'status' => 1,
            ],
        ];

        foreach ($paymentTypes as $type) {
            PaymentType::create($type);
        }

        $this->command->info('PaymentTypeSeeder completed successfully!');
    }
}
