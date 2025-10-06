<?php

namespace Database\Seeders;

use Idoneo\HumanoBilling\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$paymentTypes = [
			['id' => 1, 'name' => 'Cash'],
			['id' => 2, 'name' => 'Bank Transfer'],
			['id' => 3, 'name' => 'Bank Deposit'],
			['id' => 4, 'name' => 'Check'],
			['id' => 5, 'name' => 'Debit'],
			['id' => 6, 'name' => 'Credit Card'],
			['id' => 7, 'name' => 'PayPal'],
			['id' => 8, 'name' => 'Stripe'],
			['id' => 9, 'name' => 'Wise Transfer'],
			['id' => 10, 'name' => 'Cryptocurrency'],
			['id' => 11, 'name' => 'Bizum'],
			['id' => 12, 'name' => 'MercadoPago'],
		];

		foreach ($paymentTypes as $type) {
			PaymentType::updateOrCreate(
				['id' => $type['id']],
				[
					'name' => $type['name'],
					'is_active' => true,
				]
			);
		}

		$this->command->info('PaymentTypeSeeder completed successfully!');
	}
}
