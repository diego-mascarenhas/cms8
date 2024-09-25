<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    public function run()
    {
        $countries = [
            ['code' => 'AR', 'name' => 'Argentina'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'BR', 'name' => 'Brazil'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'DK', 'name' => 'Denmark'],
            ['code' => 'FI', 'name' => 'Finland'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'MX', 'name' => 'Mexico'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'NO', 'name' => 'Norway'],
            ['code' => 'PT', 'name' => 'Portugal'],
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'SE', 'name' => 'Sweden'],
            ['code' => 'CH', 'name' => 'Switzerland'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'US', 'name' => 'United States'],
        ];

        foreach ($countries as $country) {
            Country::create([
                'code' => $country['code'],
                'name' => $country['name'],
            ]);
        }
    }
}