<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            ServiceTypeSeeder::class,
            ServiceSeeder::class,
            TemplateSeeder::class,
        ]);
    }
}