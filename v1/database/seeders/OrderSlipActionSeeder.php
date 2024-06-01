<?php

namespace Database\Seeders;

use App\Models\OrderSlipAction;
use Illuminate\Database\Seeder;

class OrderSlipActionSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		OrderSlipAction::create([
			'id' => 1,
			'name' => 'Abrir',
			'url' => '/pedimosfacilmdw/comando/1/add/'
		]);

		OrderSlipAction::create([
			'id' => 2,
			'name' => 'Pedir',
			'url' => '/pedimosfacilmdw/comando/2/add/'
		]);

		OrderSlipAction::create([
			'id' => 3,
			'name' => 'Cerrar',
			'url' => '/pedimosfacilmdw/comando/3/add/'
		]);
	}
}