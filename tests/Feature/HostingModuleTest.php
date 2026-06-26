<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\ControlPanel\PleskConnector;
use App\Services\WHMService;
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
}
