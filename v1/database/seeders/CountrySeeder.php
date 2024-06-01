<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::create([
            'id' => 32,
            'name' => 'Argentina',
            'iso_code_2' => 'AR',
            'iso_code_3' => 'ARG'
        ]);

        Country::create([
            'id' => 152,
            'name' => 'Chile',
            'iso_code_2' => 'CL',
            'iso_code_3' => 'CHL'
        ]);

        Country::create([
            'id' => 484,
            'name' => 'Mexico',
            'iso_code_2' => 'MX',
            'iso_code_3' => 'MEX'
        ]);

        Country::create([
            'id' => 724,
            'name' => 'España',
            'iso_code_2' => 'ES',
            'iso_code_3' => 'ESP'
        ]);

        Country::create([
            'id' => 840,
            'name' => 'Estados Unidos',
            'iso_code_2' => 'US',
            'iso_code_3' => 'USA'
        ]);

        Country::create([
            'id' => 858,
            'name' => 'Uruguay',
            'iso_code_2' => 'UY',
            'iso_code_3' => 'URY'
        ]);
    }
}
