<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
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
            UserSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseSentimentSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseSeeder::class,
		]);
    }
}
