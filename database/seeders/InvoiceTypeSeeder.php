<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceTypeSeeder extends Seeder
{
	public function run(): void
	{
		if (!Schema::hasTable('invoice_types')) {
			return;
		}

		// Check if is_active column exists
		$hasIsActive = Schema::hasColumn('invoice_types', 'is_active');

		$types = [
			['id' => 1, 'name' => 'Invoice'],
			['id' => 2, 'name' => 'Credit Note'],
			['id' => 3, 'name' => 'Debit Note'],
		];

		foreach ($types as $type) {
			$data = ['name' => $type['name']];

			// Only add is_active if column exists
			if ($hasIsActive) {
				$data['is_active'] = true;
			}

			DB::table('invoice_types')->updateOrInsert(
				['id' => $type['id']],
				$data
			);
		}
	}
}
