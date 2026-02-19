<?php

namespace Database\Seeders;

use App\Models\FareType;
use Illuminate\Database\Seeder;

class FareTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Traducción audiovisual'],
            ['name' => 'Traducción general (texto)'],
            ['name' => 'Accesibilidad audiovisual'],
        ];

        foreach ($types as $type)
        {
            FareType::firstOrCreate(
                ['name' => $type['name']],
                $type,
            );
        }
    }
}
