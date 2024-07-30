<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Enterprise;

class EnterpriseSeeder extends Seeder
{
    public function run()
    {
        if (app()->environment(['local', 'dev'])) {
            $faker = \Faker\Factory::create();

            foreach (range(1, 2) as $index) {
                Enterprise::create([
                    'name' => $faker->company,
                    'type_id' => $faker->numberBetween(1, 2),
                    'user_id' => $faker->numberBetween(5, 6),
                    'assigned_to' => $faker->numberBetween(3, 4),
                ]);
            }
        }
    }
}
