<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\Person;
use App\Models\PersonEnrichment;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactSentimentSeeder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactEnrichApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_show_includes_the_stored_apollo_profile(): void
    {
        [$user] = $this->adminTeam();
        $contact = $this->contact($user, 'ana@acme.test');
        $person = Person::query()->create([
            'name' => 'Ana',
            'surname' => 'Simeone',
            'title' => 'Directora',
            'organization_name' => 'Acme',
        ]);
        PersonEnrichment::query()->create([
            'person_id' => $person->id,
            'source' => 'apollo',
            'external_id' => 'apollo-1',
            'payload' => [
                'title' => 'Directora',
                'city' => 'Madrid',
                'linkedin_url' => 'https://www.linkedin.com/in/ana',
                'seniority' => 'director',
                'organization' => ['name' => 'Acme'],
            ],
            'fetched_at' => now(),
        ]);
        $contact->person_id = $person->id;
        $contact->title = 'Directora';
        $contact->save();
        $this->seed(ContactSentimentSeeder::class);
        ContactSentimentHistory::query()->create([
            'contact_id' => $contact->id,
            'sentiment_id' => 5,
            'notes' => 'Muy conforme',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('contact')->plainTextToken)
            ->getJson('/api/contacts/'.$contact->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Directora')
            ->assertJsonPath('data.organization', 'Acme')
            ->assertJsonPath('data.city', 'Madrid')
            ->assertJsonPath('data.linkedin', 'https://www.linkedin.com/in/ana')
            ->assertJsonPath('data.seniority', 'Director')
            ->assertJsonPath('data.can_enrich', true)
            ->assertJsonPath('data.role', 'Directora')
            ->assertJsonPath('data.categories', [])
            ->assertJsonPath('data.access', null)
            ->assertJsonPath('data.activity', [])
            ->assertJsonPath('data.sentiment.emoji', '🥳')
            ->assertJsonPath('data.sentiment.name', 'Muy Positivo')
            ->assertJsonPath('data.status.name', 'Lead')
            ->assertJsonPath('data.status.label_class', 'bg-label-success');
    }

    public function test_enrich_matches_apollo_by_email_and_stores_the_profile(): void
    {
        [$user] = $this->adminTeam();
        $contact = $this->contact($user, 'ana@acme.test');
        config(['services.apollo.api_key' => 'test-key']);
        Http::fake([
            'api.apollo.io/*' => Http::response([
                'person' => [
                    'id' => 'apollo-9',
                    'title' => 'Fundadora',
                    'city' => 'Barcelona',
                    'linkedin_url' => 'https://www.linkedin.com/in/ana-acme',
                    'seniority' => 'founder',
                    'organization' => ['name' => 'Acme'],
                ],
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('contact')->plainTextToken)
            ->postJson('/api/contacts/'.$contact->id.'/enrich')
            ->assertOk()
            ->assertJsonPath('data.title', 'Fundadora')
            ->assertJsonPath('data.organization', 'Acme')
            ->assertJsonPath('data.city', 'Barcelona')
            ->assertJsonPath('data.linkedin', 'https://www.linkedin.com/in/ana-acme');

        Http::assertSent(function ($request): bool
        {
            $url = $request->url();

            return str_contains($url, '/people/match')
                && str_contains($url, 'email=ana%40acme.test')
                && ! str_contains($url, 'reveal_');
        });
        $this->assertSame('Fundadora', $contact->fresh()->title);
        $this->assertDatabaseHas('person_enrichments', [
            'source' => 'apollo',
            'external_id' => 'apollo-9',
        ]);
    }

    public function test_enrich_without_an_identifier_does_not_call_apollo(): void
    {
        [$user] = $this->adminTeam();
        $contact = $this->contact($user, null);
        Http::fake();

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('contact')->plainTextToken)
            ->postJson('/api/contacts/'.$contact->id.'/enrich')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Hace falta un email, o nombre, apellido y empresa, para enriquecer los datos.');

        Http::assertNothingSent();
    }

    private function contact(User $user, ?string $email): Contact
    {
        return Contact::query()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Ana',
            'surname' => $email === null ? null : 'Simeone',
            'email' => $email,
            'language' => 'es',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => ContactStatus::query()->where('name', 'Lead')->value('id'),
        ]);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminTeam(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(ContactStatusSeeder::class);
        $this->seed(LanguageSeeder::class);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team];
    }
}
