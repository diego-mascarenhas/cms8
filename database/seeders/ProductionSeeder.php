<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            CategorySeeder::class,
            MessageTypeSeeder::class,
            TemplateSeeder::class,
            MessageSeeder::class,
            PageSeeder::class,
            RolesAndPermissionsSeeder::class,
            PolicySeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
            SourceSeeder::class,
        ]);

        // Create root user
        $revision = User::factory()->create([
            'name' => 'Diego Mascarenhas',
            'phone' => 34722372858,
            'email' => 'diego.mascarenhas@icloud.com',
            'password' => '$2y$10$9His4IIPh5nFp0TSilz.h.0DLLE4DzhX1Os2y0QHwt.a19s6whxyC',
        ]);
        $revision->assignRole([1, 2, 10]);
    }
}
