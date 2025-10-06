<?php

namespace Database\Seeders;

use Idoneo\HumanoBilling\Models\InvoiceType;
use Illuminate\Database\Seeder;

class InvoiceTypeSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$invoiceTypes = [
			[
				'id' => 1,
				'name' => 'Factura',
			],
			[
				'id' => 2,
				'name' => 'Presupuesto',
			],
			[
				'id' => 3,
				'name' => 'Nota de crédito',
			],
			[
				'id' => 4,
				'name' => 'Nota de débito',
			],
		];

		foreach ($invoiceTypes as $type) {
			InvoiceType::create($type);
		}

		$this->command->info('InvoiceTypeSeeder completed successfully!');
	}
}
