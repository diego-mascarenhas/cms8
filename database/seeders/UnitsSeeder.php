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
            ['type' => '10 min'],
            ['type' => 'h'],
            ['type' => 'pal'],
            ['type' => 'pág'],
            ['type' => 'rollo'],
            ['type' => 'Total'],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}
