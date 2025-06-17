<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['type' => 'Minutos'],
            ['type' => '10 Minutos'],
            ['type' => 'Horas'],
            ['type' => 'Palabras'],
            ['type' => 'Páginas'],
            ['type' => 'Rollos'],
            ['type' => 'Total']
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
} 