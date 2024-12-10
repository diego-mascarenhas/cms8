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


    if (app()->environment('local')) {
      $revisionContacts = [
        [
          'team_id' => 2,
          'name' => 'Diego Mascarenhas',
          'position' => 'CEO',
          'birthday' => '1975-11-25',
          'profile' => 'Software Artisan & Freaky ;-)',
          'creator_id' => 2,
          'responsible_id' => 2,
          'status_id' => 5,
        ],
        [
          'team_id' => 2,
          'name' => 'Carla de Loureiro',
          'position' => 'CTO',
          'birthday' => '1976-09-24',
          'profile' => 'Developer Senior',
          'creator_id' => 1,
          'responsible_id' => 2,
          'status_id' => 5,
        ],
        [
          'team_id' => 2,
          'name' => 'Lucio Buxal',
          'position' => 'TSS',
          'profile' => 'Technical Support Specialist',
          'creator_id' => 1,
          'responsible_id' => 2,
          'status_id' => 5,
        ],
        [
          'team_id' => 2,
          'name' => 'Victoria',
          'position' => 'ADM',
          'profile' => 'Administrative Manager',
          'creator_id' => 1,
          'responsible_id' => 2,
          'status_id' => 5,
        ],
      ];

      foreach ($revisionContacts as $contactData) {
        $contact = Contact::create([
          'team_id' => $contactData['team_id'],
          'name' => $contactData['name'],
          'creator_id' => $contactData['creator_id'],
          'responsible_id' => $contactData['responsible_id'],
          'status_id' => $contactData['status_id'],
        ]);

        // Relate contact to revision
        $contact->enterprises()->attach(1, ['position' => $faker->jobTitle]);
      }

      $enterprise = \App\Models\Enterprise::find(1);
        
      if ($enterprise)
      {
          $enterprise->responsible_id = 1;
          $enterprise->save();
      }

      
      // Create Brandty enterprise
      $brandty = \App\Models\Enterprise::where('name', 'Brandty')->first();

      // Create manual contacts
      $manualContacts = [
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
        $contact->enterprises()->attach(2, ['position' => $faker->jobTitle]);

        $enterprise = \App\Models\Enterprise::find(2);
        
        if ($enterprise)
        {
            $enterprise->responsible_id = 5;
            $enterprise->save();
        }

        ContactSentimentHistory::create([
          'contact_id' => $contact->id,
          'sentiment_id' => (function() {
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

      
      // Create Generator Landing enterprise
      $brandty = \App\Models\Enterprise::where('name', 'Generator Landing')->first();

      // Create manual contacts
      $manualContacts = [
        [
          'team_id' => 1,
          'name' => 'Lluis Sarda',
          'position' => 'CEO',
          'birthday' => '1985-05-15',
          'profile' => 'Experienced entrepreneur and marketing expert.',
          'creator_id' => 2,
          'responsible_id' => 2,
          'status_id' => 5,
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

        // Relate contact to Generator Landing
        $contact->enterprises()->attach(3, ['position' => $faker->jobTitle]);

        $enterprise = \App\Models\Enterprise::find(3);
        
        if ($enterprise)
        {
            $enterprise->responsible_id = 8;
            $enterprise->save();
        }

        ContactSentimentHistory::create([
          'contact_id' => $contact->id,
          'sentiment_id' => (function() {
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

    // Create additional random contacts
    Contact::factory()
      ->count(147)
      ->create()
      ->each(function ($contact) use ($faker) {
        ContactSentimentHistory::create([
          'contact_id' => $contact->id,
          'sentiment_id' => (function() {
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
  }
}
