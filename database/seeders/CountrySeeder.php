<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    public function run()
    {
        $countries = [
            ['code' => 'ar', 'name' => 'Argentina'],
            ['code' => 'au', 'name' => 'Australia'],
            ['code' => 'br', 'name' => 'Brazil'],
            ['code' => 'ca', 'name' => 'Canada'],
            ['code' => 'dk', 'name' => 'Denmark'],
            ['code' => 'fi', 'name' => 'Finland'],
            ['code' => 'fr', 'name' => 'France'],
            ['code' => 'de', 'name' => 'Germany'],
            ['code' => 'it', 'name' => 'Italy'],
            ['code' => 'jp', 'name' => 'Japan'],
            ['code' => 'mx', 'name' => 'Mexico'],
            ['code' => 'nl', 'name' => 'Netherlands'],
            ['code' => 'no', 'name' => 'Norway'],
            ['code' => 'pt', 'name' => 'Portugal'],
            ['code' => 'es', 'name' => 'Spain'],
            ['code' => 'se', 'name' => 'Sweden'],
            ['code' => 'ch', 'name' => 'Switzerland'],
            ['code' => 'gb', 'name' => 'United Kingdom'],
            ['code' => 'us', 'name' => 'United States'],
        ];

        foreach ($countries as $country) {
            Country::create([
                'code' => $country['code'],
                'name' => $country['name'],
            ]);
        }
    }
}