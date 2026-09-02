<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Models\TokenUsageLog;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountManagementDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_management_datatable_returns_all_teams_including_those_without_owner(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $root = User::factory()->create();
        $root->assignRole('root');

        Account::query()->create([
            'name' => 'With Owner',
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $orphanOwner = User::factory()->create();
        $orphanTeam = Account::query()->create([
            'name' => 'Orphan Owner',
            'user_id' => $orphanOwner->id,
            'personal_team' => false,
        ]);
        $orphanOwner->delete();
        $orphanTeam->refresh();

        $response = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableBaseQuery()));

        $response->assertOk();
        $payload = $response->json();
        $this->assertArrayNotHasKey('error', $payload);
        $this->assertSame(2, (int) $payload['recordsTotal']);
        $this->assertCount(2, $payload['data']);
        $names = collect($payload['data'])->pluck('name')->implode(' ');
        $this->assertStringContainsString('Orphan Owner', $names);
        $this->assertStringContainsString('With Owner', $names);
        $this->assertArrayNotHasKey('members_count', $payload['data'][0]);
        $this->assertArrayNotHasKey('total_time', $payload['data'][0]);
    }

    public function test_account_management_page_does_not_show_removed_columns(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $root = User::factory()->withPersonalTeam()->create();
        $root->assignRole('root');

        $html = $this->actingAs($root)
            ->get(route('account-management'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('title="Creación"', $html);
        $this->assertStringNotContainsString('title="Miembros"', $html);
        $this->assertStringNotContainsString('title="Tiempo"', $html);
        $this->assertStringContainsString('title="Acciones"', $html);
    }

    public function test_account_management_datatable_shows_token_usage_in_subscriptions_column(): void
    {
        config([
            'services.openrouter.cache_store' => 'array',
            'humano_pricing.token_billing.currency' => 'EUR',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
        ]);

        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $root = User::factory()->create();
        $root->assignRole('root');

        $account = Account::query()->create([
            'name' => 'Token Team',
            'user_id' => User::factory()->create()->id,
            'personal_team' => false,
        ]);

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $account->id,
            'module_id' => null,
            'service' => 'ContactSentimentAnalysisService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $response = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableBaseQuery()));

        $response->assertOk();
        $html = collect($response->json('data'))->pluck('subscriptions_count')->implode(' ');
        $this->assertStringContainsString('10.000.000 / 10,00 EUR', $html);
    }

    public function test_account_management_datatable_shows_owner_as_title_and_team_as_truncated_subtitle(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create([
            'name' => 'Diego Tester',
            'email' => 'diego.tester@example.com',
            'phone' => 34722372858,
        ]);
        $root = User::factory()->create();
        $root->assignRole('root');

        $longTeamName = 'Asociación de Empleados del Poder Judicial de la Ciudad Autónoma de Buenos Aires';

        Account::query()->create([
            'name' => $longTeamName,
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $payload = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableBaseQuery()))
            ->assertOk()
            ->json();

        $nameHtml = collect($payload['data'])->pluck('name')->implode(' ');
        $this->assertStringContainsString('Diego Tester', $nameHtml);
        $this->assertStringContainsString($longTeamName, $nameHtml);
        $this->assertStringContainsString('text-truncate', $nameHtml);
        $this->assertStringContainsString('title="'.$longTeamName.'"', $nameHtml);

        $contactHtml = collect($payload['data'])->pluck('owner_name')->implode(' ');
        $this->assertStringContainsString('diego.tester@example.com', $contactHtml);
        $this->assertStringContainsString('34722372858', $contactHtml);
        $this->assertStringNotContainsString('Diego Tester', $contactHtml);
    }

    public function test_account_management_datatable_shows_billing_contact_when_owner_name_is_the_company(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create([
            'name' => 'Respuestos AV',
            'email' => 'repuestosavcaseros@example.com',
        ]);
        $root = User::factory()->create();
        $root->assignRole('root');

        $rootWorkspace = Account::query()->create([
            'name' => 'Root Workspace',
            'user_id' => $root->id,
            'personal_team' => true,
        ]);
        $root->forceFill(['current_team_id' => $rootWorkspace->id])->save();

        $account = Account::query()->create([
            'name' => 'Respuestos AV',
            'user_id' => $owner->id,
            'personal_team' => false,
            'stripe_id' => 'cus_test_repuestos_av',
        ]);

        $enterpriseId = DB::table('enterprises')->insertGetId([
            'team_id' => $account->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Repuestos Avenida',
            'code' => 'cus_test_repuestos_av',
            'creator_id' => $root->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $account->id,
            'name' => 'Gustavo',
            'surname' => 'Bertani',
            'email' => 'info@repuestosav.example.com',
            'profile' => '',
            'responsible_id' => $root->id,
            'creator_id' => $root->id,
        ]);
        $contact->enterprises()->attach($enterpriseId);

        $otherContact = Contact::factory()->create([
            'team_id' => $account->id,
            'name' => 'Zulema',
            'surname' => 'Extra',
            'email' => 'zulema.extra@example.com',
            'profile' => '',
            'responsible_id' => $root->id,
            'creator_id' => $root->id,
        ]);
        $otherContact->enterprises()->attach($enterpriseId);

        $payload = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableBaseQuery()))
            ->assertOk()
            ->json();

        $nameHtml = collect($payload['data'])->pluck('name')->implode(' ');
        $this->assertStringContainsString('Gustavo Bertani', $nameHtml);
        $this->assertStringContainsString('Respuestos AV', $nameHtml);
        $this->assertStringContainsString('title="Respuestos AV"', $nameHtml);

        foreach (['Gustavo', 'Bertani', 'Gustavo Bertani'] as $term)
        {
            $hits = $this->actingAs($root)->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])->get(route('account-management').'?'.http_build_query($this->accountDataTableSearchQuery($term)))
                ->assertOk()
                ->json();

            $this->assertSame(1, (int) $hits['recordsFiltered'], 'Expected a match for '.$term);
            $this->assertStringContainsString('Gustavo Bertani', collect($hits['data'])->pluck('name')->implode(' '));
        }

        $wrongHits = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableSearchQuery('Zulema')))
            ->assertOk()
            ->json();

        $this->assertSame(0, (int) $wrongHits['recordsFiltered']);
    }

    public function test_account_management_datatable_search_matches_owner_email_and_phone(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create([
            'name' => 'Diego Tester',
            'email' => 'diego.tester@example.com',
            'phone' => 34722372858,
        ]);
        $other = User::factory()->create([
            'name' => 'Otro Dueño',
            'email' => 'otro@example.com',
            'phone' => 34911111111,
        ]);
        $root = User::factory()->create();
        $root->assignRole('root');

        Account::query()->create([
            'name' => 'Cuenta Diego',
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);
        Account::query()->create([
            'name' => 'Cuenta Otra',
            'user_id' => $other->id,
            'personal_team' => false,
        ]);

        $emailHits = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableSearchQuery('diego.tester@example.com')))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) $emailHits['recordsFiltered']);
        $this->assertStringContainsString('Cuenta Diego', collect($emailHits['data'])->pluck('name')->implode(' '));

        $phoneHits = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableSearchQuery('722372858')))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) $phoneHits['recordsFiltered']);
        $this->assertStringContainsString('Cuenta Diego', collect($phoneHits['data'])->pluck('name')->implode(' '));

        $nameHits = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableSearchQuery('Diego Tester')))
            ->assertOk()
            ->json();

        $this->assertSame(1, (int) $nameHits['recordsFiltered']);
        $this->assertStringContainsString('Diego Tester', collect($nameHits['data'])->pluck('name')->implode(' '));
    }

    public function test_account_management_datatable_paginates_like_other_lists(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $root = User::factory()->create();
        $root->assignRole('root');

        foreach (['Alpha Team', 'Beta Team', 'Gamma Team'] as $name)
        {
            $owner = User::factory()->create();
            Account::query()->create([
                'name' => $name,
                'user_id' => $owner->id,
                'personal_team' => false,
            ]);
        }

        $firstPage = $this->accountDataTableBaseQuery();
        $firstPage['length'] = 2;
        $firstPage['start'] = 0;

        $pageOne = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($firstPage))
            ->assertOk()
            ->json();

        $this->assertSame(3, (int) $pageOne['recordsTotal']);
        $this->assertCount(2, $pageOne['data']);

        $secondPage = $firstPage;
        $secondPage['start'] = 2;

        $pageTwo = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($secondPage))
            ->assertOk()
            ->json();

        $this->assertSame(3, (int) $pageTwo['recordsTotal']);
        $this->assertCount(1, $pageTwo['data']);
    }

    public function test_account_management_datatable_includes_change_password_action(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $root = User::factory()->create();
        $root->assignRole('root');

        $account = Account::query()->create([
            'name' => 'Cuenta Clave',
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $payload = $this->actingAs($root)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('account-management').'?'.http_build_query($this->accountDataTableBaseQuery()))
            ->assertOk()
            ->json();

        $actions = collect($payload['data'])->pluck('action')->implode(' ');
        $this->assertStringContainsString('changeAccountPassword('.$account->id.')', $actions);
        $this->assertStringContainsString('ti-key', $actions);
        $this->assertStringContainsString(route('account.rates.edit', $account->id), $actions);
        $this->assertStringContainsString('ti-currency-euro', $actions);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ($this->accountDataTableColumnDefinitions() as $def)
        {
            $columns[] = array_merge($def, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $columns,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountDataTableSearchQuery(string $keyword): array
    {
        $query = $this->accountDataTableBaseQuery();
        $query['search']['value'] = $keyword;

        return $query;
    }

    /**
     * @return array<int, array{data: string, name: string, searchable: string, orderable: string}>
     */
    private function accountDataTableColumnDefinitions(): array
    {
        return [
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'owner_name', 'name' => 'owner_name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'active_clients_count', 'name' => 'active_clients_count', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'subscriptions_count', 'name' => 'subscriptions_count', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ];
    }
}
