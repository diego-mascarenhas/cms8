<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;

class ClientSeeder extends Seeder
{
    public function run()
    {
        if (app()->environment(['local', 'dev'])) {
            $faker = \Faker\Factory::create();

            foreach (range(1, 2) as $index) {
                Client::create([
                    'name' => $faker->company,
                    'description' => $faker->sentence,
                    'user_id' => $faker->numberBetween(5, 6),
                    'assigned_to' => $faker->numberBetween(3, 4),
                ]);
            }
        }
    }
}
