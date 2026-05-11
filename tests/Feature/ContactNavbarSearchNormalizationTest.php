<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EnterpriseBillingAddress;
use App\Models\EnterpriseTaxStatusType;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactNavbarSearchNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
        $team->enableModule('contacts');
    }

    public function test_navbar_contact_search_is_case_insensitive(): void
    {
        $team = $this->user->currentTeam;

        Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'PEDRO',
            'surname' => 'LÓPEZ',
            'email' => 'pedro@example.test',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('contact.search', ['q' => 'pedro']));

        $response->assertOk();
        $names = collect($response->json('members'))->pluck('name')->all();
        $this->assertContains('PEDRO LÓPEZ', $names);
    }

    public function test_navbar_contact_search_matches_surname_column(): void
    {
        $team = $this->user->currentTeam;

        Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Laura',
            'surname' => 'MARTINEZ',
            'email' => 'laura@example.test',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('contact.search', ['q' => 'martinez']));

        $response->assertOk();
        $names = collect($response->json('members'))->pluck('name')->all();
        $this->assertContains('Laura MARTINEZ', $names);
    }

    public function test_navbar_contact_search_matches_spanish_accents_when_query_has_none(): void
    {
        $team = $this->user->currentTeam;

        Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'López Fernández',
            'email' => 'ana.lopez@example.test',
            'responsible_id' => $this->user->id,
            'creator_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('contact.search', ['q' => 'fernandez']));

        $response->assertOk();
        $names = collect($response->json('members'))->pluck('name')->all();
        $this->assertContains('Ana López Fernández', $names);
    }

    public function test_navbar_enterprise_search_matches_razon_social_on_billing_address(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
        ]);

        $team = $this->user->currentTeam;

        $enterpriseId = DB::table('enterprises')->insertGetId([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Nombre Comercial Corto',
            'code' => 'NCC',
            'creator_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $enterpriseId,
            'name' => 'RAZON SOCIAL LARGA SA',
            'tax_status_type_id' => EnterpriseTaxStatusType::query()->firstOrFail()->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('contact.search', ['q' => 'razon social larga']));

        $response->assertOk();
        $names = collect($response->json('enterprises'))->pluck('name')->all();
        $this->assertContains('Nombre Comercial Corto', $names);
    }
}
