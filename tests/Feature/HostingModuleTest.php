<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Server;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\ControlPanel\PleskConnector;
use App\Services\WHMService;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\TeamDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_includes_hosting_modules(): void
    {
        $this->assertContains('hosting', TeamDemoSeeder::DEMO_DEV_MODULES);
        $this->assertContains('servers', TeamDemoSeeder::DEMO_DEV_MODULES);
    }

    public function test_cpanel_sync_persists_domains_with_server_id(): void
    {
        Http::fake([
            '*listaccts*' => Http::response([
                'acct' => [
                    [
                        'domain' => 'example.com',
                        'user' => 'example',
                        'plan' => 'default',
                        'suspended' => 0,
                    ],
                ],
            ]),
        ]);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => null,
            'name' => 'WHM Test',
            'server_url' => 'whm.example.com',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => false,
            'status_id' => 1,
        ]);

        $result = app(WHMService::class)->syncDomainsFromServer($server);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['domains_synced']);
        $this->assertDatabaseHas('domains', [
            'domain' => 'example.com',
            'server_id' => $server->id,
            'username' => 'example',
            'plan' => 'default',
            'suspended' => 0,
        ]);
    }

    public function test_authenticated_user_can_sync_server_domains(): void
    {
        Http::fake([
            '*listaccts*' => Http::response([
                'acct' => [
                    [
                        'domain' => 'client.test',
                        'user' => 'client',
                        'plan' => 'business',
                        'suspended' => 0,
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Production WHM',
            'server_url' => 'cpanel.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => false,
            'status_id' => 1,
        ]);

        $response = $this->actingAs($user)->postJson(route('server.syncDomains', $server->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'domains_synced' => 1,
            ]);

        $this->assertDatabaseHas('domains', [
            'domain' => 'client.test',
            'server_id' => $server->id,
        ]);
    }

    public function test_plesk_connector_is_prepared_but_not_implemented(): void
    {
        $server = Server::withoutGlobalScopes()->create([
            'team_id' => null,
            'name' => 'Plesk Test',
            'server_url' => 'plesk.test',
            'username' => 'admin',
            'control_panel' => 'plesk',
            'encrypted_token' => 'secret-token',
            'success' => false,
            'status_id' => 1,
        ]);

        $result = app(PleskConnector::class)->testConnection($server);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Plesk', $result['error']);
    }

    public function test_control_panel_manager_resolves_cpanel_and_plesk(): void
    {
        $manager = app(ControlPanelManager::class);

        $cpanel = Server::withoutGlobalScopes()->make(['control_panel' => 'cpanel']);
        $plesk = Server::withoutGlobalScopes()->make(['control_panel' => 'plesk']);

        $this->assertInstanceOf(\App\Services\ControlPanel\CpanelConnector::class, $manager->forServer($cpanel));
        $this->assertInstanceOf(PleskConnector::class, $manager->forServer($plesk));
    }

    public function test_cpanel_account_auth_syncs_single_domain(): void
    {
        Http::fake([
            'https://demo.test:2083/execute/Variables/get_user_information*' => Http::response([
                'status' => 1,
                'data' => [
                    'domain' => 'demo-cpanelrevisionalpha.net',
                    'user' => 'democpanel',
                    'plan' => 'default',
                    'ip' => '51.83.76.40',
                ],
            ]),
        ]);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => null,
            'name' => 'Account mode',
            'server_url' => 'demo.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => false,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $result = app(WHMService::class)->syncDomainsFromServer($server);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('domains', [
            'domain' => 'demo-cpanelrevisionalpha.net',
            'server_id' => $server->id,
            'username' => 'democpanel',
        ]);
    }

    public function test_cpanel_reseller_account_auth_lists_creatable_plans_via_whm(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/listpkgs*' => Http::response([
                'package' => [
                    ['name' => 'revision_beginner'],
                    ['name' => 'revision_enthusiast'],
                    ['name' => 'revision_explorer'],
                ],
            ]),
        ]);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => null,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $result = app(\App\Services\ControlPanel\CpanelConnector::class)->listPlans($server);

        $this->assertTrue($result['success']);
        $this->assertSame(
            ['revision_beginner', 'revision_enthusiast', 'revision_explorer'],
            $result['plans'],
        );
        $this->assertFalse($result['reseller_limited']);
    }

    public function test_cpanel_account_auth_falls_back_to_current_plan_when_whm_listpkgs_fails(): void
    {
        Http::fake([
            'https://account.test:2087/json-api/listpkgs*' => Http::response('Access denied', 403),
            'https://account.test:2083/execute/Variables/get_user_information*' => Http::response([
                'status' => 1,
                'data' => [
                    'domain' => 'example.com',
                    'user' => 'siteuser',
                    'plan' => 'default',
                ],
            ]),
        ]);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => null,
            'name' => 'Regular account',
            'server_url' => 'account.test',
            'username' => 'siteuser',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $result = app(\App\Services\ControlPanel\CpanelConnector::class)->listPlans($server);

        $this->assertTrue($result['success']);
        $this->assertSame(['default'], $result['plans']);
        $this->assertTrue($result['reseller_limited']);
    }

    public function test_authenticated_user_can_fetch_server_hosting_plans(): void
    {
        Http::fake([
            '*listpkgs*' => Http::response([
                'package' => [
                    ['name' => 'default'],
                    ['name' => 'business'],
                    ['name' => 'premium'],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller WHM',
            'server_url' => 'whm-reseller.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(route('server.plans', $server->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'limited_to_account' => false,
            ])
            ->assertJsonPath('plans', ['default', 'business', 'premium']);
    }

    public function test_hosting_store_provisions_cpanel_account_with_selected_plan(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->createHostingServiceType();
        $enterprise = $this->createHostingEnterprise($team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $response = $this->actingAs($user)->post(route('hosting.store'), [
            'domain' => 'cliente-demo.test',
            'server_id' => $server->id,
            'username' => 'clientedemo',
            'plan' => 'revision_beginner',
            'enterprise_id' => $enterprise->id,
        ]);

        $domain = Domain::query()->where('domain', 'cliente-demo.test')->first();

        $response->assertRedirect(route('domain.show', $domain->id));

        $this->assertDatabaseHas('domains', [
            'domain' => 'cliente-demo.test',
            'server_id' => $server->id,
            'username' => 'clientedemo',
            'plan' => 'revision_beginner',
        ]);

        $domain->refresh();
        $this->assertNotNull($domain->service_id);
        $this->assertDatabaseHas('services', [
            'id' => $domain->service_id,
            'enterprise_id' => $enterprise->id,
            'description' => 'Hosting cliente-demo.test',
        ]);

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), 'createacct')
                && $request['plan'] === 'revision_beginner'
                && $request['username'] === 'clientedemo'
                && $request['domain'] === 'cliente-demo.test'
                && strlen((string) ($request['password'] ?? '')) >= 8;
        });
    }

    public function test_hosting_store_saves_service_id(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $serviceType = \App\Models\ServiceType::query()->create([
            'name' => 'Web Hosting',
            'description' => 'Hosting plans',
            'currency_id' => 1,
            'frequency' => 12,
            'status' => 1,
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'service_type_id' => $serviceType->id,
            'operation' => 'sell',
            'description' => 'Hosting revision_beginner',
            'currency_id' => 1,
            'price' => 19.99,
            'discount' => 0,
            'frequency' => 12,
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $this->actingAs($user)->post(route('hosting.store'), [
            'domain' => 'servicio-demo.test',
            'server_id' => $server->id,
            'username' => 'serviciodemo',
            'plan' => 'revision_beginner',
            'service_id' => $service->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'domain' => 'servicio-demo.test',
            'service_id' => $service->id,
        ]);
    }

    public function test_hosting_store_uses_team_business_email_as_contact(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        $user = User::factory()->create(['email' => 'staff@example.com']);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $team->setSetting('business_config', json_encode([
            'business_email' => 'negocio@empresa.test',
        ]));

        $this->createHostingServiceType();
        $enterprise = $this->createHostingEnterprise($team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $this->actingAs($user)->post(route('hosting.store'), [
            'domain' => 'contacto-demo.test',
            'server_id' => $server->id,
            'username' => 'contactodemo',
            'plan' => 'revision_beginner',
            'enterprise_id' => $enterprise->id,
        ])->assertRedirect();

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), 'createacct')
                && ($request['contactemail'] ?? null) === 'negocio@empresa.test';
        });
    }

    public function test_hosting_store_validates_required_server_and_username(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->from(route('hosting.create'))->post(route('hosting.store'), [
            'domain' => 'pepe5',
            'username' => '1invalid',
            'plan' => '',
        ]);

        $response->assertRedirect(route('hosting.create'));
        $response->assertSessionHasErrors(['server_id', 'domain', 'username', 'plan', 'enterprise_id']);
        $domainErrors = session('errors')->get('domain');
        $this->assertNotEmpty($domainErrors);
        $this->assertStringContainsString('extensión', $domainErrors[0]);
    }

    public function test_hosting_store_configures_spf_after_account_creation(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
            'https://huginn.test:2087/json-api/cpanel*' => Http::response([
                'result' => [
                    'status' => 1,
                    'data' => [],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->createHostingServiceType();
        $enterprise = $this->createHostingEnterprise($team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $response = $this->actingAs($user)->post(route('hosting.store'), [
            'domain' => 'spf-demo.test',
            'server_id' => $server->id,
            'username' => 'spfdemo',
            'plan' => 'revision_beginner',
            'enterprise_id' => $enterprise->id,
        ]);

        $domain = Domain::query()->where('domain', 'spf-demo.test')->firstOrFail();

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('spf_configured', true);

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), '/json-api/cpanel')
                && ($request['cpanel_jsonapi_user'] ?? null) === 'spfdemo'
                && ($request['cpanel_jsonapi_func'] ?? null) === 'mass_edit_zone';
        });
    }

    public function test_hosting_store_shows_error_when_cpanel_provision_fails(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => ['result' => 0, 'reason' => 'Invalid plan selected'],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->createHostingServiceType();
        $enterprise = $this->createHostingEnterprise($team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $response = $this->actingAs($user)->from(route('hosting.create'))
            ->post(route('hosting.store'), [
                'domain' => 'cliente-demo.test',
                'server_id' => $server->id,
                'username' => 'clientedemo',
                'plan' => 'revision_beginner',
                'enterprise_id' => $enterprise->id,
            ]);

        $response->assertRedirect(route('hosting.create'));
        $response->assertSessionHasErrors('provision');
        $response->assertSessionHasErrors([
            'provision' => 'Invalid plan selected',
        ]);
        $this->assertDatabaseMissing('domains', ['domain' => 'cliente-demo.test']);
    }

    public function test_hosting_store_shows_whm_output_when_reason_is_empty(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => [
                    'result' => 0,
                    'reason' => '',
                    'output' => [
                        'raw' => [
                            '(XID abc123) (XID xyz) User "pepep" already exists.',
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->createHostingServiceType();
        $enterprise = $this->createHostingEnterprise($team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $response = $this->actingAs($user)->from(route('hosting.create'))
            ->post(route('hosting.store'), [
                'domain' => 'pepep.test',
                'server_id' => $server->id,
                'username' => 'pepep',
                'plan' => 'revision_beginner',
                'enterprise_id' => $enterprise->id,
            ]);

        $response->assertRedirect(route('hosting.create'));
        $response->assertSessionHasErrors('provision');
        $provisionErrors = session('errors')->get('provision');
        $this->assertNotEmpty($provisionErrors);
        $this->assertStringContainsString('User "pepep" already exists.', $provisionErrors[0]);
    }

    public function test_hosting_store_recognizes_whm_list_result_format(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'result' => [
                    [
                        'statusmsg' => 'Account Creation Ok',
                        'status' => 1,
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->createHostingServiceType();
        $enterprise = $this->createHostingEnterprise($team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $response = $this->actingAs($user)->post(route('hosting.store'), [
            'domain' => 'list-format.test',
            'server_id' => $server->id,
            'username' => 'listformat',
            'plan' => 'revision_beginner',
            'enterprise_id' => $enterprise->id,
        ]);

        $domain = Domain::query()->where('domain', 'list-format.test')->firstOrFail();

        $response->assertRedirect(route('domain.show', $domain->id));
    }

    public function test_hosting_store_shows_whm_statusmsg_from_list_result(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'result' => [
                    [
                        'statusmsg' => '(XID n88uue) “pepe5” es un nombre de usuario reservado en este sistema.',
                        'status' => 0,
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->createHostingServiceType();
        $enterprise = $this->createHostingEnterprise($team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $response = $this->actingAs($user)->from(route('hosting.create'))
            ->post(route('hosting.store'), [
                'domain' => 'pepe5.com',
                'server_id' => $server->id,
                'username' => 'pepe5',
                'plan' => 'revision_beginner',
                'enterprise_id' => $enterprise->id,
            ]);

        $response->assertRedirect(route('hosting.create'));
        $response->assertSessionHasErrors('provision');
        $provisionErrors = session('errors')->get('provision');
        $this->assertNotEmpty($provisionErrors);
        $this->assertStringContainsString('nombre de usuario reservado', $provisionErrors[0]);
    }

    public function test_domain_change_plan_updates_remote_and_local_record(): void
    {
        Http::fake([
            '*modifyacct*' => Http::response(['metadata' => ['result' => 1]]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'whm.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => 1,
        ]);

        $domain = Domain::factory()->create([
            'server_id' => $server->id,
            'username' => 'siteuser',
            'plan' => 'default',
        ]);

        $response = $this->actingAs($user)->post(route('domain.change-plan', $domain->id), [
            'plan' => 'premium',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $this->assertSame('premium', $domain->fresh()->plan);
    }

    private function createHostingEnterprise(Team $team): Enterprise
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        return Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));
    }

    private function createHostingServiceType(): \App\Models\ServiceType
    {
        return \App\Models\ServiceType::query()->firstOrCreate(
            ['name' => 'Web Hosting'],
            [
                'description' => 'Hosting plans',
                'currency_id' => 1,
                'frequency' => 12,
                'status' => 1,
            ],
        );
    }
}
