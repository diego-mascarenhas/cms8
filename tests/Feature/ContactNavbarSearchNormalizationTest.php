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
use Database\Seeders\ProjectStatusSeeder;
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

    public function test_navbar_billing_address_search_is_not_listed_as_an_enterprise(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'enterprises'],
            [
                'name' => 'Enterprises',
                'icon' => 'building',
                'description' => 'Clients',
                'status' => 1,
            ],
        );
        $this->user->currentTeam->enableModule('enterprises');

        $team = $this->user->currentTeam;
        $enterpriseId = DB::table('enterprises')->insertGetId([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Clean Up',
            'code' => 'cus_TTFOX7NVHkJwYC',
            'creator_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $enterpriseId,
            'name' => 'CLEAN UP BUENOS AIRES SRL',
            'identification_number' => '30717198561',
            'tax_status_type_id' => EnterpriseTaxStatusType::query()->firstOrFail()->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('contact.search', ['q' => 'CLEAN UP BUENOS AIRES']));

        $response->assertOk();
        $enterpriseNames = collect($response->json('enterprises'))->pluck('name')->all();
        $billing = collect($response->json('billingAddresses'));

        $this->assertNotContains('CLEAN UP BUENOS AIRES SRL', $enterpriseNames);
        $this->assertContains('Clean Up', $enterpriseNames);
        $this->assertTrue($billing->pluck('name')->contains('CLEAN UP BUENOS AIRES SRL'));
        $this->assertSame(
            route('empresas.show', $enterpriseId),
            $billing->firstWhere('name', 'CLEAN UP BUENOS AIRES SRL')['url'],
        );
    }

    public function test_navbar_project_search_translates_status_to_spanish(): void
    {
        app()->setLocale('es');

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'projects'],
            [
                'name' => 'Projects',
                'icon' => 'folder',
                'description' => 'Projects',
                'status' => 1,
            ],
        );
        $this->user->currentTeam->enableModule('projects');

        $team = $this->user->currentTeam;
        $cleanUpId = DB::table('enterprises')->insertGetId([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Clean Up',
            'creator_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cbdId = DB::table('enterprises')->insertGetId([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'CBD Norte',
            'creator_id' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->insert([
            [
                'team_id' => $team->id,
                'enterprise_id' => $cleanUpId,
                'responsible_id' => $this->user->id,
                'name' => 'Sitio Clean Up',
                'status_id' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'team_id' => $team->id,
                'enterprise_id' => $cbdId,
                'responsible_id' => $this->user->id,
                'name' => 'Local CBD Norte',
                'status_id' => 13,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $invoiced = $this->actingAs($this->user)->getJson(route('contact.search', ['q' => 'Sitio Clean']));
        $invoiced->assertOk();
        $this->assertSame(
            'Cliente: Clean Up - Estado: Facturado',
            collect($invoiced->json('projects'))->firstWhere('name', 'Sitio Clean Up')['subtitle'] ?? '',
        );

        $rejected = $this->actingAs($this->user)->getJson(route('contact.search', ['q' => 'Local CBD']));
        $rejected->assertOk();
        $this->assertSame(
            'Cliente: CBD Norte - Estado: No aprobado',
            collect($rejected->json('projects'))->firstWhere('name', 'Local CBD Norte')['subtitle'] ?? '',
        );
    }
}
