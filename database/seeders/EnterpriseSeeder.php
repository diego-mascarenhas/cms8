<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Enterprise;
use App\Models\EnterpriseSentiment;
use App\Models\EnterpriseSentimentHistory;
use Faker\Factory as Faker;

class EnterpriseSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        Enterprise::factory()->count(50)->create()->each(function ($enterprise) use ($faker) {
            EnterpriseSentimentHistory::create([
                'enterprise_id' => $enterprise->id,
                'sentiment_id' => EnterpriseSentiment::inRandomOrder()->first()->id,
                'notes' => $faker->sentence,
            ]);
        });
    }
}