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
        Currency::firstOrCreate(
            ['id' => 840],
            [
                'code' => 'USD',
                'name' => 'United States Dollar',
                'symbol' => '$',
                'status' => true,
            ],
        );

        Currency::firstOrCreate(
            ['id' => 978],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'status' => true,
            ],
        );

        Currency::firstOrCreate(
            ['id' => 826],
            [
                'code' => 'GBP',
                'name' => 'British Pound Sterling',
                'symbol' => '£',
                'status' => true,
            ],
        );
    }
}
