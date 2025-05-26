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
            ['type' => 'min'],
            ['type' => 'pal'],
            ['type' => 'pag'],
            ['type' => 'rollo'],
            ['type' => 'hour']
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
} 