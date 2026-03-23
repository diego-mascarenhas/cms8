<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo product catalogue for the demo team: textile sample only (Ropa, Calzado, Accesorios).
 * Legacy hosting SKUs were removed; use TextileProductsSeeder as the single source.
 */
class TeamDemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TextileProductsSeeder::class);
    }
}
