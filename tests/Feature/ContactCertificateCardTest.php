<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactCertificateCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );
    }

    public function test_contact_profile_lists_certificates_and_the_public_download_url(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $person = User::factory()->create([
            'name' => 'Alan Robert Rodriguez',
            'email' => 'alan.04rt@gmail.com',
        ]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Alan',
            'surname' => 'Rodriguez',
            'email' => 'alan.04rt@gmail.com',
            'user_id' => $person->id,
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);
        $survey = Survey::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'title' => 'Conferencia SCI',
            'is_active' => true,
            'certificate_url' => 'https://cms.example/cert.jpg',
            'schema_json' => ['title' => 'Conferencia SCI', 'fields' => []],
        ]);
        SurveyResponse::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'survey_id' => $survey->id,
            'user_id' => $person->id,
            'certified' => true,
            'payload_json' => ['answers' => []],
        ]);

        $this->actingAs($admin)
            ->get(route('contact.show', $contact->id))
            ->assertOk()
            ->assertSee('Certificados')
            ->assertSee('Conferencia SCI')
            ->assertSee('https://oba.test/certificado', false);
    }
}
