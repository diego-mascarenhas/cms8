<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('currencies')->insert([
            [
                'id' => 840,
                'code' => 'USD',
                'name' => 'United States Dollar',
                'symbol' => '$',
                'status' => true,
            ],
            [
                'id' => 978,
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'status' => true,
            ],
            [
                'id' => 32,
                'code' => 'ARS',
                'name' => 'Argentine Peso',
                'symbol' => '$',
                'status' => true,
            ],
        ]);
    }
}
