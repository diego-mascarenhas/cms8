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

    // Create Brandty enterprise
    $brandty = \App\Models\Enterprise::where('name', 'Brandty')->first();

    // Create manual contacts
    $manualContacts = [
      [
        'team_id' => 2,
        'name' => 'Diego',
        'position' => 'CEO',
        'birthday' => '1975-11-25',
        'profile' => 'Experienced entrepreneur and marketing expert.',
        'creator_id' => 2,
        'responsible_id' => 2,
        'status_id' => 5,
      ],
      [
        'team_id' => 2,
        'name' => 'Pablo',
        'position' => 'CTO',
        'birthday' => '1976-01-11',
        'profile' => 'Experienced entrepreneur and marketing expert.',
        'creator_id' => 1,
        'responsible_id' => 2,
        'status_id' => 5,
      ],
      [
        'team_id' => 2,
        'name' => 'Lucio',
        'position' => 'CTO',
        'birthday' => '1976-01-11',
        'profile' => 'Experienced entrepreneur and marketing expert.',
        'creator_id' => 1,
        'responsible_id' => 2,
        'status_id' => 5,
      ],
      [
        'team_id' => 2,
        'name' => 'Victoria',
        'position' => 'CTO',
        'birthday' => '1976-01-11',
        'profile' => 'Experienced entrepreneur and marketing expert.',
        'creator_id' => 1,
        'responsible_id' => 2,
        'status_id' => 5,
      ],
      [
        'team_id' => 1,
        'name' => 'Guzmán',
        'position' => 'CEO',
        'birthday' => '1985-05-15',
        'profile' => 'Experienced entrepreneur and marketing expert.',
        'creator_id' => 2,
        'responsible_id' => 2,
        'status_id' => 5,
      ],
      [
        'team_id' => 1,
        'name' => 'Eva',
        'position' => 'COO',
        'birthday' => '1988-09-22',
        'profile' => 'Operations specialist with a background in project management.',
        'creator_id' => 2,
        'responsible_id' => 2,
        'status_id' => 5,
      ],
      [
        'team_id' => 1,
        'name' => 'Cristina',
        'position' => 'Creative Director',
        'birthday' => '1990-03-10',
        'profile' => 'Innovative designer with a passion for branding and visual communication.',
        'creator_id' => 2,
        'responsible_id' => 2,
        'status_id' => 3,
      ],
    ];

    foreach ($manualContacts as $contactData) {
      $contact = Contact::create([
        'team_id' => $contactData['team_id'],
        'name' => $contactData['name'],
        'creator_id' => $contactData['creator_id'],
        'responsible_id' => $contactData['responsible_id'],
        'status_id' => $contactData['status_id'],
      ]);

      // Relate contact to Brandty
      $contact->enterprises()->attach(1, ['position' => $faker->jobTitle]);

      ContactSentimentHistory::create([
        'contact_id' => $contact->id,
        'sentiment_id' => ContactSentiment::inRandomOrder()->first()->id,
        'notes' => $faker->sentence,
      ]);
    }

    // Create additional random contacts
    Contact::factory()
      ->count(147)
      ->create()
      ->each(function ($contact) use ($faker) {
        ContactSentimentHistory::create([
          'contact_id' => $contact->id,
          'sentiment_id' => ContactSentiment::inRandomOrder()->first()->id,
          'notes' => $faker->sentence,
        ]);
      });
  }
}
