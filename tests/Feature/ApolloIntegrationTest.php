<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApolloIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        if (DB::table('contact_statuses')->where('id', 1)->doesntExist())
        {
            DB::table('contact_statuses')->insert([
                'id' => 1,
                'name' => 'Lead',
                'label_class' => 'bg-label-success',
            ]);
        }
    }

    protected function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    protected function enableProspectingModule(Team $team): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'prospecting'],
            [
                'name' => 'Prospecting',
                'icon' => 'target',
                'description' => 'Prospect search',
                'is_core' => false,
                'group' => null,
                'order' => 0,
                'status' => 1,
            ],
        );

        $team->enableModule('prospecting');
    }

    public function test_apollo_index_requires_authentication(): void
    {
        $response = $this->get(route('contact.apollo'));

        $response->assertRedirect();
        $this->assertTrue(str_contains($response->headers->get('Location'), 'login'));
    }

    public function test_apollo_index_requires_contact_create_permission(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $user->teams()->attach($team->id, ['role' => 'guest']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'guest']));

        // Without the module the route 404s before it ever checks permissions, which would make
        // this test pass for the wrong reason. The guest must not own the team: owners can
        // manage contacts even without a create role.
        $this->enableProspectingModule($team);

        $this->actingAs($user);

        $response = $this->get(route('contact.apollo'));

        $response->assertDeniedForBrowser();
    }

    public function test_apollo_index_returns_404_when_prospecting_module_disabled(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->actingAs($user);

        $response = $this->get(route('contact.apollo'));

        $response->assertStatus(404);
    }

    public function test_apollo_index_returns_view_when_authorized(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $this->assertTrue($user->fresh()->currentTeam->hasModule('prospecting'));
        $this->actingAs($user->fresh());

        $response = $this->get(route('contact.apollo'));

        $response->assertOk();
        $response->assertSee('Encuentra contactos', false);
    }

    public function test_add_person_as_contact_creates_contact_with_team_and_apollo_data(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-person-123',
            'first_name' => 'Jane',
            'last_name_obfuscated' => 'Do***e',
            'title' => 'VP Sales',
            'organization_name' => 'Acme Inc',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'contact_id', 'redirect_url']);

        $this->assertDatabaseHas('contacts', [
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Jane Do***e',
            'status_id' => 1,
        ]);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertIsObject($contact->data);
        $this->assertEquals('apollo-person-123', $contact->data->apollo->apollo_id ?? null);
        $this->assertEquals('VP Sales', $contact->data->apollo->title ?? null);
        $this->assertEquals('Acme Inc', $contact->data->apollo->organization_name ?? null);
    }

    public function test_add_person_as_contact_redirects_when_not_ajax(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-456',
            'first_name' => 'John',
            'last_name_obfuscated' => '',
            'title' => null,
            'organization_name' => 'Other Co',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/contact/', $response->headers->get('Location'));

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('John', $contact->name);
    }
}
