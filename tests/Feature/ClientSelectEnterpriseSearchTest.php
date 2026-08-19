<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientSelectEnterpriseSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ContactStatusSeeder::class,
            CurrencySeeder::class,
            InvoiceTypeSeeder::class,
            \Database\Seeders\PaymentTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_project_create_client_select_includes_all_enterprise_types_and_contact_keywords(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Demo SA',
            'type_id' => 1,
            'status_id' => 2,
            'responsible_id' => $user->id,
        ]);

        $alliance = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'DOA Alliance Co',
            'type_id' => 3,
            'status_id' => 2,
            'responsible_id' => $user->id,
        ]);

        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor Alpha',
            'type_id' => 2,
            'status_id' => 2,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ignacio',
            'surname' => 'Escobar Unique',
            'email' => 'ignacio.escobar.unique@example.test',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);
        $alliance->contacts()->attach($contact->id);

        $response = $this->actingAs($user)->get(route('project.create'));

        $response->assertOk();
        $response->assertSee('value="'.$client->id.'"', false);
        $response->assertSee('value="'.$alliance->id.'"', false);
        $response->assertSee('value="'.$supplier->id.'"', false);
        $response->assertSee('data-responsible="'.$user->name.' · '.$user->email.'"', false);
        $response->assertSee('data-contacts=', false);
        $response->assertSee('ignacio.escobar.unique@example.test', false);
        $response->assertSee('Ignacio Escobar Unique', false);
        $response->assertSee('select2-client-enterprise', false);
        $response->assertSee('data-type="Alianza"', false);
        $response->assertSee('data-type="Cliente"', false);
        $response->assertSee('data-type="Proveedor"', false);
        $response->assertDontSee("$('#enterprise_id, #category_id, #status_id').select2", false);

        // The widget searches this payload client-side, so what matters is that it decodes to a
        // label carrying the contact, not how the attribute happens to be quoted or escaped.
        preg_match_all("/data-contacts='([^']*)'/", (string) $response->getContent(), $matches);
        $labels = collect($matches[1])
            ->flatMap(fn (string $json): array => json_decode(html_entity_decode($json), true) ?: [])
            ->pluck('label')
            ->all();

        $this->assertContains('Ignacio Escobar Unique · ignacio.escobar.unique@example.test', $labels);
    }

    public function test_expense_create_enterprise_select_includes_contact_keywords(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Lizama Abogados Unique',
            'type_id' => 1,
            'status_id' => 2,
            'responsible_id' => $user->id,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Natalia Andrea',
            'surname' => 'Escobar Arce Unique',
            'email' => 'natalia.escobar.unique@example.test',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);
        $enterprise->contacts()->attach($contact->id);

        $response = $this->actingAs($user)->get(route('expense.create'));

        $response->assertOk();
        $response->assertSee('value="'.$enterprise->id.'"', false);
        $response->assertSee('data-responsible="'.$user->name.' · '.$user->email.'"', false);
        $response->assertSee('Natalia Andrea Escobar Arce Unique', false);
        $response->assertSee('natalia.escobar.unique@example.test', false);
        $response->assertSee('foldEnterpriseAccent', false);
    }
}
