<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Currency::create([
            'id' => 840,
            'code' => 'USD',
            'name' => 'United States Dollar',
            'symbol' => '$',
            'status' => true,
        ]);

        Currency::create([
            'id' => 978,
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'status' => true,
        ]);

        Currency::create([
            'id' => 32,
            'code' => 'ARS',
            'name' => 'Argentine Peso',
            'symbol' => '$',
            'status' => true,
        ]);
    }
}
