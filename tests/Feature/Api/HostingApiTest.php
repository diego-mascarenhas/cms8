<?php

namespace Tests\Feature\Api;

use App\Contracts\ControlPanelConnector;
use App\Enums\ServerStatus;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\ControlPanel\ControlPanelManager;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_the_team_hosting_accounts(): void
    {
        [$user, $team] = $this->adminTeam();
        $server = Server::query()->create([
            'name' => 'Odin',
            'server_url' => 'odin.test',
            'username' => 'revision',
            'control_panel' => 'cpanel',
            'success' => true,
            'status_id' => ServerStatus::Active,
            'team_id' => $team->id,
        ]);
        Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'plan' => 'revision_enthusiast',
            'site_type' => 'WordPress',
            'php_version' => '8.3',
            'suspended' => false,
        ]);

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/hosting')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('nameservers.0', 'NS1.REVISIONALPHA.COM')
            ->assertJsonPath('nameservers.1', 'NS2.REVISIONALPHA.COM')
            ->assertJsonPath('data.data.0.domain', 'lizama.test')
            ->assertJsonPath('data.data.0.server', 'Odin')
            ->assertJsonPath('data.data.0.suspended', false);
    }

    public function test_admin_provisions_a_flex_account_and_mailbox(): void
    {
        [$user, $team] = $this->adminTeam();
        Server::factory()->create([
            'team_id' => $team->id,
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $enterprise = $this->enterprise($team);
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('createAccount')
            ->once()
            ->withArgs(function (Server $server, string $username, string $domain, string $plan): bool
            {
                return $username === 'cliente' && $domain === 'cliente.test' && $plan === 'revision_flex';
            })
            ->andReturn(['success' => true]);
        $connector->shouldReceive('ensureSpfRecord')->once()->andReturn(['success' => true]);
        $connector->shouldReceive('createEmailAccount')
            ->once()
            ->withArgs(fn (Server $server, Domain $domain, string $local): bool => $local === 'info')
            ->andReturn(['success' => true]);
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting', [
                'enterprise_id' => $enterprise->id,
                'domain' => 'Cliente.Test',
                'username' => 'cliente',
                'mailboxes' => ['info'],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('plan', 'Flex')
            ->assertJsonPath('data.domain', 'cliente.test')
            ->assertJsonPath('data.username', 'cliente')
            ->assertJsonPath('data.mailboxes.0.email', 'info@cliente.test')
            ->assertJsonPath('data.mailboxes.0.created', true);

        $this->assertDatabaseHas('domains', [
            'domain' => 'cliente.test',
            'username' => 'cliente',
            'plan' => 'revision_flex',
        ]);
    }

    public function test_provision_does_not_store_the_domain_when_cpanel_rejects_it(): void
    {
        [$user, $team] = $this->adminTeam();
        Server::factory()->create([
            'team_id' => $team->id,
            'encrypted_token' => 'test-token',
        ]);
        $enterprise = $this->enterprise($team);
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('createAccount')->once()->andReturn([
            'success' => false,
            'error' => 'El paquete no existe.',
        ]);
        $connector->shouldReceive('ensureSpfRecord')->never();
        $connector->shouldReceive('createEmailAccount')->never();
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting', [
                'enterprise_id' => $enterprise->id,
                'domain' => 'rechazado.test',
                'username' => 'rechazado',
                'mailboxes' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El paquete no existe.');

        $this->assertDatabaseMissing('domains', ['domain' => 'rechazado.test']);
    }

    public function test_admin_opens_a_team_hosting_account(): void
    {
        [$user, $team] = $this->adminTeam();
        $server = Server::query()->create([
            'name' => 'Odin',
            'server_url' => 'https://odin.test:2083',
            'username' => 'revision',
            'control_panel' => 'cpanel',
            'success' => true,
            'status_id' => ServerStatus::Active,
            'team_id' => $team->id,
        ]);
        $domain = Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'plan' => 'revision_flex',
            'site_type' => 'WordPress',
            'php_version' => '8.3',
            'suspended' => false,
            'data' => [
                'email' => 'info@lizama.test',
                'email_accounts' => [
                    ['email' => 'info@lizama.test'],
                ],
            ],
        ]);
        $otherTeam = Team::factory()->create();
        $otherServer = Server::query()->create([
            'name' => 'Ajeno',
            'server_url' => 'https://ajeno.test:2083',
            'username' => 'revision',
            'control_panel' => 'cpanel',
            'success' => true,
            'status_id' => ServerStatus::Active,
            'team_id' => $otherTeam->id,
        ]);
        $other = Domain::query()->create([
            'domain' => 'ajeno.test',
            'server_id' => $otherServer->id,
            'username' => 'ajeno',
        ]);

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/hosting/'.$domain->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.domain', 'lizama.test')
            ->assertJsonPath('data.plan', 'revision_flex')
            ->assertJsonPath('data.site_type', 'WordPress')
            ->assertJsonPath('data.php_version', '8.3')
            ->assertJsonPath('data.contact_email', 'info@lizama.test')
            ->assertJsonPath('data.emails.0.email', 'info@lizama.test');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/hosting/'.$other->id)
            ->assertNotFound();
    }

    public function test_collaborator_cannot_list_hosting(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'editor']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/hosting')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting', [
                'enterprise_id' => 1,
                'domain' => 'no.test',
                'username' => 'no',
                'mailboxes' => [],
            ])
            ->assertForbidden();
    }

    private function enterprise(Team $team): Enterprise
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

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminTeam(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team];
    }
}
