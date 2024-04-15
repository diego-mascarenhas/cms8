<?php

namespace Database\Seeders;

use App\Models\OrderSlipCommand;
use Illuminate\Database\Seeder;

class OrderSlipCommandSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		OrderSlipCommand::create([
			'id' => 1,
			'name' => 'Abrir',
			'action_id' => 1
		]);

		OrderSlipCommand::create([
			'id' => 2,
			'name' => 'Entrada',
			'action_id' => 2
		]);

		OrderSlipCommand::create([
			'id' => 3,
			'name' => 'Principal',
			'action_id' => 2
		]);

		OrderSlipCommand::create([
			'id' => 4,
			'name' => 'Directo',
			'action_id' => 2
		]);

		OrderSlipCommand::create([
			'id' => 5,
			'name' => 'Adicional',
			'action_id' => 2
		]);

		OrderSlipCommand::create([
			'id' => 6,
			'name' => 'Cuenta',
			'action_id' => 3
		]);

		OrderSlipCommand::create([
			'id' => 7,
			'name' => 'Pagar',
			'action_id' => 3
		]);

		OrderSlipCommand::create([
			'id' => 8,
			'name' => 'Propina',
			'action_id' => 3
		]);
	}
}