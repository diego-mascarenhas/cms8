<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PaymentTypeSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		// Check if is_active column exists
		$hasIsActive = Schema::hasColumn('payment_types', 'is_active');

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
			$data = ['name' => $type['name']];

			// Only add is_active if column exists
			if ($hasIsActive) {
				$data['is_active'] = true;
			}

			PaymentType::updateOrCreate(
				['id' => $type['id']],
				$data
			);
		}

		$this->command->info('PaymentTypeSeeder completed successfully!');
	}
}
