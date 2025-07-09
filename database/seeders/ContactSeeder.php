<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactSentiment;
use App\Models\ContactSentimentHistory;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        if (app()->environment('local')) {
            // Solo contactos de ejemplo para Team 1 (Demo)
            $exampleContacts = [
                [
                    'team_id' => 1,
                    'name' => 'Admin Example',
                    'email' => 'admin@example.com',
                    'phone' => 600000000,
                    'profile' => 'Example admin contact for demonstration',
                    'creator_id' => 1,
                    'responsible_id' => 1,
                    'status_id' => 5,
                ],
                [
                    'team_id' => 1,
                    'name' => 'Demo User',
                    'email' => 'demo@example.com',
                    'phone' => 600000001,
                    'profile' => 'Example demo contact for testing',
                    'creator_id' => 1,
                    'responsible_id' => 1,
                    'status_id' => 5,
                ],
            ];

            foreach ($exampleContacts as $contactData) {
                $contact = Contact::updateOrCreate(
                    ['email' => $contactData['email'], 'team_id' => $contactData['team_id']],
                    $contactData
                );

                // Create sentiment history for example contacts
                if (!ContactSentimentHistory::where('contact_id', $contact->id)->exists()) {
                    ContactSentimentHistory::create([
                        'contact_id' => $contact->id,
                        'sentiment_id' => (function () {
                            $rand = rand(1, 100);
                            if ($rand <= 80) {
                                return ContactSentiment::whereIn('id', [3, 4, 5])
                                    ->inRandomOrder()
                                    ->first()
                                    ->id;
                            } else {
                                return ContactSentiment::whereIn('id', [1, 2])
                                    ->inRandomOrder()
                                    ->first()
                                    ->id;
                            }
                        })(),
                        'notes' => $faker->sentence,
                    ]);
                }
            }
        }

        /*
        Create additional random contacts - DISABLED to use real collaborators from CollaboratorsSeeder
        Contact::factory()
            ->count(147)
            ->create()
            ->each(function ($contact) use ($faker) {
                ContactSentimentHistory::create([
                    'contact_id' => $contact->id,
                    'sentiment_id' => (function () {
                        $rand = rand(1, 100);
                        if ($rand <= 80) {
                            return ContactSentiment::whereIn('id', [3, 4, 5])
                                ->inRandomOrder()
                                ->first()
                                ->id;
                        } else {
                            return ContactSentiment::whereIn('id', [1, 2])
                                ->inRandomOrder()
                                ->first()
                                ->id;
                        }
                    })(),
                    'notes' => $faker->sentence,
                ]);
            });
        */
    }
}
