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
                'name' => 'Cash',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Bank Transfer',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Bank Deposit',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 4,
                'name' => 'Check',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 5,
                'name' => 'Debit',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 6,
                'name' => 'Credit Card',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 7,
                'name' => 'PayPal',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 8,
                'name' => 'Stripe',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 9,
                'name' => 'Wise Transfer',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 10,
                'name' => 'Cryptocurrency',
                'discount' => 0.00,
                'status' => 1,
            ],
            [
                'id' => 11,
                'name' => 'Bizum',
                'discount' => 0.00,
                'status' => 1,
            ],
        ];

        foreach ($paymentTypes as $type) {
            PaymentType::updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'discount' => $type['discount'],
                    'status' => $type['status'],
                ]
            );
        }

        $this->command->info('PaymentTypeSeeder completed successfully!');
    }
}
