<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    public function run()
    {
        $countries = [
            ['id' => 724, 'name' => 'España', 'code' => 'ES'],
            ['id' => 840, 'name' => 'Estados Unidos', 'code' => 'US'],
            ['id' => 484, 'name' => 'México', 'code' => 'MX'],
            ['id' => 32, 'name' => 'Argentina', 'code' => 'AR'],
            ['id' => 152, 'name' => 'Chile', 'code' => 'CL'],
            ['id' => 170, 'name' => 'Colombia', 'code' => 'CO'],
            ['id' => 604, 'name' => 'Perú', 'code' => 'PE'],
            ['id' => 862, 'name' => 'Venezuela', 'code' => 'VE'],
            ['id' => 218, 'name' => 'Ecuador', 'code' => 'EC'],
            ['id' => 591, 'name' => 'Panamá', 'code' => 'PA'],
            ['id' => 188, 'name' => 'Costa Rica', 'code' => 'CR'],
            ['id' => 320, 'name' => 'Guatemala', 'code' => 'GT'],
            ['id' => 340, 'name' => 'Honduras', 'code' => 'HN'],
            ['id' => 222, 'name' => 'El Salvador', 'code' => 'SV'],
            ['id' => 558, 'name' => 'Nicaragua', 'code' => 'NI'],
            ['id' => 214, 'name' => 'República Dominicana', 'code' => 'DO'],
            ['id' => 192, 'name' => 'Cuba', 'code' => 'CU'],
            ['id' => 858, 'name' => 'Uruguay', 'code' => 'UY'],
            ['id' => 68, 'name' => 'Bolivia', 'code' => 'BO'],
            ['id' => 600, 'name' => 'Paraguay', 'code' => 'PY'],
        ];

        foreach ($countries as $country) {
            Country::create($country);
        }
    }
}
