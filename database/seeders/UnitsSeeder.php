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
            ['type' => 'Minute'],
            ['type' => '10 Minutes'],
            ['type' => 'Hour'],
            ['type' => 'Word'],
            ['type' => 'Page'],
            ['type' => 'Roll'],
            ['type' => 'Total']
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
} 