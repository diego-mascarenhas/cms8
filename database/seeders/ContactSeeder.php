<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contact;
use App\Models\ContactSentiment;
use App\Models\ContactSentimentHistory;
use Faker\Factory as Faker;

class ContactSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        Contact::factory()->count(50)->create()->each(function ($contact) use ($faker) {
            ContactSentimentHistory::create([
                'contact_id' => $contact->id,
                'sentiment_id' => ContactSentiment::inRandomOrder()->first()->id,
                'notes' => $faker->sentence,
            ]);
        });
    }
}