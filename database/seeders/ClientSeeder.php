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

            foreach (range(1, 20) as $index) {
                Client::create([
                    'name' => $faker->company,
                    'description' => $faker->sentence,
                    'user_id' => $faker->numberBetween(1, 10),
                    'assigned_to' => $faker->numberBetween(1, 10),
                ]);
            }
        }
    }
}
