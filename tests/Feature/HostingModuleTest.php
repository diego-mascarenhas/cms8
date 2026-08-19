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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

    public function test_cpanel_sync_merges_existing_domain_data(): void
    {
        Http::fake([
            '*listaccts*' => Http::response([
                'acct' => [
                    [
                        'domain' => 'example.com',
                        'user' => 'example',
                        'plan' => 'default',
                        'suspended' => 0,
                        'diskused' => 100,
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

        Domain::factory()->create([
            'domain' => 'example.com',
            'server_id' => $server->id,
            'username' => 'example',
            'plan' => 'legacy',
            'data' => [
                'ssl_status' => ['valid' => true],
                'email_accounts' => [['email' => 'info@example.com']],
            ],
        ]);

        app(WHMService::class)->syncDomainsFromServer($server);

        $domain = Domain::query()->where('domain', 'example.com')->first();

        $this->assertNotNull($domain);
        $this->assertTrue($domain->data['ssl_status']['valid'] ?? false);
        $this->assertSame('info@example.com', $domain->data['email_accounts'][0]['email'] ?? null);
        $this->assertSame(100, $domain->data['disk_used'] ?? $domain->data['diskused'] ?? null);
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

        [$user, $team] = $this->adminWithTeam();

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
            'https://demo.test:2087/json-api/listaccts*' => Http::response('Access denied', 403),
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

    public function test_cpanel_reseller_account_auth_lists_all_accounts_via_whm(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/listaccts*' => Http::response([
                'acct' => [
                    [
                        'domain' => 'revisionalpha.es',
                        'user' => 'revision',
                        'plan' => 'revision_enthusiast',
                        'suspended' => 0,
                        'diskused' => 100,
                        'disklimit' => 10240,
                    ],
                    [
                        'domain' => 'idoneo.dev',
                        'user' => 'idoneo',
                        'plan' => 'revision_mycloud',
                        'suspended' => 0,
                        'diskused' => 250,
                        'disklimit' => 20480,
                    ],
                    [
                        'domain' => 'example.org',
                        'user' => 'example',
                        'plan' => 'revision_wordpress',
                        'suspended' => 0,
                    ],
                ],
            ]),
        ]);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => null,
            'name' => 'Reseller account',
            'server_url' => 'huginn.test',
            'username' => 'revision',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $result = app(WHMService::class)->syncDomainsFromServer($server);

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['domains_synced']);
        $this->assertDatabaseHas('domains', [
            'domain' => 'revisionalpha.es',
            'server_id' => $server->id,
            'plan' => 'revision_enthusiast',
        ]);
        $this->assertDatabaseHas('domains', [
            'domain' => 'idoneo.dev',
            'server_id' => $server->id,
            'plan' => 'revision_mycloud',
        ]);
        $this->assertDatabaseHas('domains', [
            'domain' => 'example.org',
            'server_id' => $server->id,
            'plan' => 'revision_wordpress',
        ]);
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

        [$user, $team] = $this->adminWithTeam();

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

        [$user, $team] = $this->adminWithTeam();

        $this->createHostingCategory();
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
                && ($request['mxcheck'] ?? null) === 0
                && strlen((string) ($request['password'] ?? '')) >= 8;
        });
    }

    public function test_hosting_store_works_without_enterprise_or_service(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        [$user, $team] = $this->adminWithTeam();

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
            'domain' => 'sin-cliente.test',
            'server_id' => $server->id,
            'username' => 'sincliente',
            'plan' => 'revision_beginner',
        ])->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'domain' => 'sin-cliente.test',
            'service_id' => null,
        ]);
    }

    public function test_hosting_store_saves_service_id(): void
    {
        Http::fake([
            'https://huginn.test:2087/json-api/createacct*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        [$user, $team] = $this->adminWithTeam();

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

        $category = $this->createHostingCategory();

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
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

        [$user, $team] = $this->adminWithTeam(['email' => 'staff@example.com']);

        $team->setSetting('business_config', json_encode([
            'business_email' => 'negocio@empresa.test',
        ]));

        $this->createHostingCategory();
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
        [$user, $team] = $this->adminWithTeam();

        $response = $this->actingAs($user)->from(route('hosting.create'))->post(route('hosting.store'), [
            'domain' => 'pepe5',
            'username' => '1invalid',
            'plan' => '',
        ]);

        $response->assertRedirect(route('hosting.create'));
        $response->assertSessionHasErrors(['server_id', 'domain', 'username', 'plan']);
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

        [$user, $team] = $this->adminWithTeam();

        $this->createHostingCategory();
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

        [$user, $team] = $this->adminWithTeam();

        $this->createHostingCategory();
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

        [$user, $team] = $this->adminWithTeam();

        $this->createHostingCategory();
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

        [$user, $team] = $this->adminWithTeam();

        $this->createHostingCategory();
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

        [$user, $team] = $this->adminWithTeam();

        $this->createHostingCategory();
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
            '*changepackage*' => Http::response(['metadata' => ['result' => 1]]),
        ]);

        [$user, $team] = $this->adminWithTeam();

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

    public function test_domain_change_plan_works_with_cpanel_account_auth(): void
    {
        Http::fake([
            '*changepackage*' => Http::response(['metadata' => ['result' => 1]]),
        ]);

        [$user, $team] = $this->adminWithTeam();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller',
            'server_url' => 'reseller.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'reseller-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'server_id' => $server->id,
            'username' => 'siteuser',
            'plan' => 'revision_beginner',
        ]);

        $response = $this->actingAs($user)->post(route('domain.change-plan', $domain->id), [
            'plan' => 'revision_pro',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertSame('revision_pro', $domain->fresh()->plan);

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), 'changepackage')
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('democpanel:reseller-password'));
        });
    }

    public function test_domain_change_plan_falls_back_to_modifyacct_when_changepackage_fails(): void
    {
        Http::fake([
            '*changepackage*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'Permission Denied']]),
            '*modifyacct*' => Http::response(['metadata' => ['result' => 1]]),
        ]);

        [$user, $team] = $this->adminWithTeam();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Reseller',
            'server_url' => 'reseller.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'reseller-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'server_id' => $server->id,
            'username' => 'siteuser',
            'plan' => 'revision_beginner',
            'data' => ['available_plans' => ['revision_beginner', 'revision_pro']],
        ]);

        $response = $this->actingAs($user)->post(route('domain.change-plan', $domain->id), [
            'plan' => 'revision_pro',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertSame('revision_pro', $domain->fresh()->plan);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'changepackage'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'modifyacct'));
    }

    public function test_hosting_index_shows_spanish_summary_and_table_labels(): void
    {
        [$user, $team] = $this->adminWithTeam();

        Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'whm.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => 1,
        ]);

        Domain::factory()->create([
            'domain' => 'activo.test',
            'username' => 'activo',
            'plan' => 'revision_enthusiast',
            'suspended' => false,
            'server_id' => Server::withoutGlobalScopes()->first()->id,
        ]);

        Domain::factory()->create([
            'domain' => 'suspendido.test',
            'username' => 'suspendido',
            'plan' => 'undefined',
            'suspended' => true,
            'server_id' => Server::withoutGlobalScopes()->first()->id,
        ]);

        $response = $this->actingAs($user)->get(route('hosting.index'));

        $response->assertOk();
        $response->assertSee('Total dominios', false);
        $response->assertSee('Dominios activos', false);
        $response->assertSee('Plan sin definir', false);
        $response->assertSee('Dominios suspendidos', false);
        $response->assertSee('>2<', false);
        $response->assertSee('Dominio', false);
        $response->assertSee('Estado', false);
        $response->assertSee('Acciones', false);
    }

    public function test_hosting_list_domain_column_links_to_detail_with_hosting_show_permission(): void
    {
        [$user, $team] = $this->adminWithTeam();

        Permission::firstOrCreate(['name' => 'hosting.show', 'guard_name' => 'web']);
        $user->givePermissionTo('hosting.show');

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
            'domain' => 'pepe8.com',
            'username' => 'pepe8',
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('hosting.index').'?'.http_build_query($this->hostingDataTableQuery()));

        $response->assertOk();
        $domainColumn = collect($response->json('data'))->first()['domain'] ?? '';
        $this->assertStringContainsString(route('domain.show', $domain->id), $domainColumn);
        $this->assertStringContainsString('pepe8.com', $domainColumn);
        $this->assertStringContainsString('<a href=', $domainColumn);
    }

    public function test_hosting_datatable_shows_server_name_and_filters_by_server(): void
    {
        [$user, $team] = $this->adminWithTeam();

        $serverA = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Huginn',
            'server_url' => 'huginn.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => 1,
        ]);

        $serverB = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Muninn',
            'server_url' => 'muninn.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => 1,
        ]);

        Domain::factory()->create([
            'server_id' => $serverA->id,
            'domain' => 'alpha.test',
            'username' => 'alpha',
            'site_type' => 'WordPress',
            'php_version' => '8.2',
            'suspended' => false,
        ]);

        Domain::factory()->create([
            'server_id' => $serverB->id,
            'domain' => 'beta.test',
            'username' => 'beta',
            'site_type' => 'Laravel',
            'php_version' => '8.3',
            'suspended' => true,
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('hosting.index').'?'.http_build_query(array_merge(
            $this->hostingDataTableQuery(),
            ['server_filter' => $serverA->id],
        )));

        $response->assertOk();
        $response->assertJsonPath('recordsTotal', 1);
        $this->assertStringContainsString('Huginn', (string) collect($response->json('data'))->first()['server_name']);

        $statusResponse = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('hosting.index').'?'.http_build_query(array_merge(
            $this->hostingDataTableQuery(),
            ['status_filter' => 'suspended'],
        )));

        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('recordsTotal', 1);
        $this->assertStringContainsString('beta.test', (string) collect($statusResponse->json('data'))->first()['domain']);

        $typeResponse = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('hosting.index').'?'.http_build_query(array_merge(
            $this->hostingDataTableQuery(),
            ['site_type_filter' => 'WordPress'],
        )));

        $typeResponse->assertOk();
        $typeResponse->assertJsonPath('recordsTotal', 1);
        $this->assertSame('WordPress', collect($typeResponse->json('data'))->first()['site_type'] ?? null);

        $phpResponse = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('hosting.index').'?'.http_build_query(array_merge(
            $this->hostingDataTableQuery(),
            ['php_version_filter' => '8.3'],
        )));

        $phpResponse->assertOk();
        $phpResponse->assertJsonPath('recordsTotal', 1);
        $this->assertSame('8.3', collect($phpResponse->json('data'))->first()['php_version'] ?? null);
    }

    /**
     * Servers and hosting sit behind the `access-infrastructure-modules` gate, which reads a
     * Spatie role ({@see \App\Models\User::canAccessInfrastructure}). The team pivot role is a
     * different thing and does not open that gate.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: Team}
     */
    private function adminWithTeam(array $attributes = []): array
    {
        $user = User::factory()->create($attributes);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']));

        return [$user->refresh(), $team];
    }

    /**
     * @return array<string, mixed>
     */
    private function hostingDataTableQuery(): array
    {
        $columnNames = ['id', 'domain', 'username', 'server_name', 'site_type', 'php_version', 'suspended', 'action'];

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => collect($columnNames)->map(fn (string $name) => [
                'data' => $name,
                'name' => $name,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ])->all(),
        ];
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

    private function createHostingCategory(): \App\Models\Category
    {
        $team = auth()->user()?->currentTeam;
        $module = \App\Models\Module::query()->firstOrCreate(
            ['key' => 'services'],
            [
                'name' => 'Services',
                'icon' => 'ti-briefcase',
                'description' => null,
                'is_core' => false,
                'status' => 1,
            ],
        );

        return \App\Models\Category::query()->firstOrCreate(
            [
                'name' => 'Web Hosting',
                'team_id' => $team?->id,
                'module_id' => $module->id,
            ],
            [
                'description' => 'Hosting plans',
                'status' => 1,
            ],
        );
    }
}
