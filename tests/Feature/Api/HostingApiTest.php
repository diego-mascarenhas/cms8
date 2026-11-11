<?php

namespace Tests\Feature\Api;

use App\Contracts\ControlPanelConnector;
use App\Enums\ServerStatus;
use App\Mail\HostingFailureMail;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Server;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\Hosting\StripeHostingPlanGateway;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_google_mail_replaces_mx_and_skips_local_mailboxes(): void
    {
        [$user, $team] = $this->adminTeam();
        Server::factory()->create([
            'team_id' => $team->id,
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $enterprise = $this->enterprise($team);
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('createAccount')->once()->andReturn(['success' => true]);
        $connector->shouldReceive('ensureSpfRecord')->once()->andReturn(['success' => true]);
        $connector->shouldReceive('createEmailAccount')->never();
        $connector->shouldReceive('updateMxRecords')
            ->once()
            ->withArgs(function (Server $server, Domain $domain, array $records): bool
            {
                return $domain->domain === 'correo.test'
                    && ($records[0]['target'] ?? '') === 'aspmx.l.google.com'
                    && count($records) === 5;
            })
            ->andReturn(['success' => true]);
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('hosting')->plainTextToken)
            ->postJson('/api/hosting', [
                'enterprise_id' => $enterprise->id,
                'domain' => 'correo.test',
                'username' => 'correo',
                'mailboxes' => ['info'],
                'mail_provider' => 'google',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mailboxes', [])
            ->assertJsonPath('data.mail_dns_configured', true);

        $this->assertSame('aspmx.l.google.com', Domain::query()->where('domain', 'correo.test')->first()?->data['mx_records'][0]['target'] ?? null);
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
            'error' => 'Sorry, unable to use package revision_flex. Verify that the package exists and that you have not exceeded your reseller restrictions.',
        ]);
        $connector->shouldReceive('ensureSpfRecord')->never();
        $connector->shouldReceive('createEmailAccount')->never();
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        Mail::fake();

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting', [
                'enterprise_id' => $enterprise->id,
                'domain' => 'rechazado.test',
                'username' => 'rechazado',
                'mailboxes' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No pudimos crear el hosting. El plan todavía no está disponible en el servidor. Avisamos al equipo para resolverlo.');

        Mail::assertSent(HostingFailureMail::class, function (HostingFailureMail $mail): bool
        {
            return $mail->hasTo('webmaster@revisionalpha.cloud')
                && str_contains($mail->technical, 'unable to use package');
        });

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

    public function test_hosting_detail_links_the_client_and_subscription(): void
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
        ]);
        $enterprise = $this->enterprise($team);
        $enterprise->forceFill(['code' => 'cus_lizama'])->save();
        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_lizama_hosting',
            'team_id' => $team->id,
            'customer_id' => 'cus_lizama',
            'status' => 'active',
            'plan_name' => 'Hosting lizama.test',
            'amount_total' => 19.9,
            'price_currency' => 'eur',
            'plan_interval' => 'month',
            'plan_interval_count' => 1,
            'data' => [
                'category' => 'hosting',
                'domain' => 'lizama.test',
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('hosting')->plainTextToken)
            ->getJson('/api/hosting/'.$domain->id)
            ->assertOk()
            ->assertJsonPath('data.enterprise.id', $enterprise->id)
            ->assertJsonPath('data.subscription.id', $sync->id)
            ->assertJsonPath('data.subscription.name', 'Hosting lizama.test')
            ->assertJsonPath('data.subscription.amount', 19.9)
            ->assertJsonPath('data.subscription.currency', 'eur')
            ->assertJsonPath('data.subscription.interval', '1 mes')
            ->assertJsonPath('data.subscription.status', 'active');
    }

    public function test_admin_changes_the_account_and_mailbox_passwords(): void
    {
        [$user, $team] = $this->adminTeam();
        $server = Server::factory()->create([
            'team_id' => $team->id,
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $domain = Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'suspended' => false,
        ]);
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('changeAccountPassword')
            ->once()
            ->withArgs(fn (Server $server, Domain $account, string $password): bool => $password === 'Nueva-Clave-12!')
            ->andReturn(['success' => true]);
        $connector->shouldReceive('changeEmailPassword')
            ->once()
            ->withArgs(fn (Server $server, Domain $account, string $email, string $password): bool => $email === 'info@lizama.test' && $password === 'Otra-Clave-12!')
            ->andReturn(['success' => true]);
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/password', [
                'password' => 'Nueva-Clave-12!',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('password', 'Nueva-Clave-12!');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/email-password', [
                'email' => 'info@lizama.test',
                'password' => 'Otra-Clave-12!',
            ])
            ->assertOk()
            ->assertJsonPath('password', 'Otra-Clave-12!');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/email-password', [
                'email' => 'otro@otro.test',
                'password' => 'Otra-Clave-12!',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esa cuenta no pertenece a este dominio.');
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

    public function test_flex_hosting_cannot_change_plan(): void
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
        ]);
        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/hosting/'.$domain->id)
            ->assertOk()
            ->assertJsonPath('data.can_change_plan', false)
            ->assertJsonPath('data.plans', []);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/plan', [
                'plan' => 'rs-s3',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Flex no cambia de plan.');
    }

    public function test_plan_downgrade_requires_the_usage_to_fit(): void
    {
        [$user, $team] = $this->adminTeam();
        $server = Server::factory()->create([
            'team_id' => $team->id,
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $domain = Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'plan' => 'rs-s7',
            'data' => [
                'account_disk' => [
                    'used_mb' => 125,
                    'limit_mb' => 1024000,
                    'unlimited' => false,
                    'usage_percent' => 1,
                ],
            ],
        ]);
        $packages = [
            ['name' => 'rs-s7', 'quota_mb' => 1024000, 'unlimited' => false],
            ['name' => 'rs-s1', 'quota_mb' => 100, 'unlimited' => false],
            ['name' => 'rs-s3', 'quota_mb' => 1024, 'unlimited' => false],
        ];
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('phpVersion')->andReturn(['version' => null]);
        $connector->shouldReceive('siteType')->andReturn(['site_type' => null]);
        $connector->shouldReceive('listPlans')->andReturn([
            'success' => true,
            'plans' => ['rs-s7', 'rs-s1', 'rs-s3'],
            'packages' => $packages,
        ]);
        $connector->shouldReceive('changePlan')
            ->once()
            ->withArgs(fn (Server $called, Domain $account, string $plan): bool => $plan === 'rs-s3')
            ->andReturn(['success' => true]);
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('supports')->andReturn(true);
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/hosting/'.$domain->id)
            ->assertOk()
            ->assertJsonPath('data.can_change_plan', true)
            ->assertJsonPath('data.plans.1.name', 'rs-s1')
            ->assertJsonPath('data.plans.1.allowed', false)
            ->assertJsonPath('data.plans.2.name', 'rs-s3')
            ->assertJsonPath('data.plans.2.allowed', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/plan', [
                'plan' => 'rs-s1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El uso actual no entra en la capacidad de ese plan.');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/plan', [
                'plan' => 'rs-s3',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan', 'rs-s3');

        $this->assertSame('rs-s3', $domain->fresh()->plan);
    }

    public function test_plan_change_updates_the_stripe_subscription(): void
    {
        [$user, $team] = $this->adminTeam();
        $server = Server::factory()->create([
            'team_id' => $team->id,
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $domain = Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'plan' => 'rs-s7',
            'data' => [
                'account_disk' => [
                    'used_mb' => 10,
                    'limit_mb' => 1024000,
                    'unlimited' => false,
                ],
            ],
        ]);
        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_lizama',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Hosting S7',
            'amount_total' => 19.9,
            'price_currency' => 'eur',
            'plan_interval' => 'month',
            'plan_interval_count' => 1,
            'data' => [
                'category' => 'hosting',
                'domain' => 'lizama.test',
                'metadata' => ['category' => 'hosting', 'domain' => 'lizama.test', 'plan' => 'rs-s7'],
            ],
        ]);
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('listPlans')->andReturn([
            'success' => true,
            'plans' => ['rs-s7', 'rs-s3'],
            'packages' => [
                ['name' => 'rs-s7', 'quota_mb' => 1024000, 'unlimited' => false],
                ['name' => 'rs-s3', 'quota_mb' => 10240, 'unlimited' => false],
            ],
        ]);
        $connector->shouldReceive('changePlan')->once()->andReturn(['success' => true]);
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('supports')->andReturn(true);
            $mock->shouldReceive('forServer')->andReturn($connector);
        });
        $gateway = new class extends StripeHostingPlanGateway
        {
            /** @var list<array{0: string, 1: string, 2: string}> */
            public array $swaps = [];

            /** @var array<string, string> */
            public array $metadata = [];

            public function catalog(Team $team): array
            {
                return [[
                    'price_id' => 'price_s3',
                    'product_name' => 'Hosting S3',
                    'plan' => 'rs-s3',
                    'currency' => 'eur',
                    'interval' => 'month',
                    'interval_count' => 1,
                    'unit_amount' => 990,
                    'whm_package' => 'rs-s3',
                ]];
            }

            public function currentItem(Team $team, string $subscriptionId): ?array
            {
                return [
                    'item_id' => 'si_current',
                    'price_id' => 'price_s7',
                    'currency' => 'eur',
                    'interval' => 'month',
                    'interval_count' => 1,
                ];
            }

            public function swap(Team $team, string $subscriptionId, string $itemId, string $priceId): void
            {
                $this->swaps[] = [$subscriptionId, $itemId, $priceId];
            }

            public function replaceMetadata(Team $team, string $subscriptionId, array $metadata): bool
            {
                $this->metadata = $metadata;

                return true;
            }
        };
        $this->app->instance(StripeHostingPlanGateway::class, $gateway);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('hosting')->plainTextToken)
            ->postJson('/api/hosting/'.$domain->id.'/plan', ['plan' => 'rs-s3'])
            ->assertOk()
            ->assertJsonPath('data.plan', 'rs-s3');

        $sync->refresh();
        $this->assertSame([['sub_lizama', 'si_current', 'price_s3']], $gateway->swaps);
        $this->assertSame('rs-s3', $gateway->metadata['plan']);
        $this->assertSame('hosting', $gateway->metadata['type']);
        $this->assertSame('lizama.test', $sync->data['domain']);
        $this->assertSame('9.90', $sync->amount_total);
    }

    public function test_mailbox_quota_can_only_increase(): void
    {
        [$user, $team] = $this->adminTeam();
        $server = Server::factory()->create([
            'team_id' => $team->id,
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $domain = Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'suspended' => false,
            'data' => [
                'email_accounts' => [[
                    'email' => 'info@lizama.test',
                    'diskused_mb' => 4.3,
                    'diskquota_mb' => 50,
                    'unlimited' => false,
                ]],
            ],
        ]);
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('changeEmailQuota')
            ->once()
            ->withArgs(fn (Server $called, Domain $account, string $email, int $quota): bool => $email === 'info@lizama.test' && $quota === 100)
            ->andReturn(['success' => true]);
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        $token = $user->createToken('hosting')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/email-quota', [
                'email' => 'info@lizama.test',
                'quota_mb' => 40,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La nueva cuota tiene que ser mayor que la actual.');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/hosting/'.$domain->id.'/email-quota', [
                'email' => 'info@lizama.test',
                'quota_mb' => 100,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $saved = $domain->fresh()->getCachedEmailAccounts()[0];
        $this->assertSame(100, $saved['diskquota_mb']);
        $this->assertFalse($saved['unlimited']);
    }

    public function test_hosting_detail_detects_php_and_site_type_when_they_are_missing(): void
    {
        [$user, $team] = $this->adminTeam();
        $server = Server::factory()->create([
            'team_id' => $team->id,
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $domain = Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'site_type' => null,
            'php_version' => null,
        ]);
        $connector = \Mockery::mock(ControlPanelConnector::class);
        $connector->shouldReceive('phpVersion')->once()->andReturn(['success' => true, 'version' => '8.2']);
        $connector->shouldReceive('siteType')->once()->andReturn(['success' => true, 'site_type' => 'WordPress']);
        $connector->shouldReceive('listPlans')->andReturn(['success' => true, 'plans' => [], 'packages' => []]);
        $this->mock(ControlPanelManager::class, function ($mock) use ($connector)
        {
            $mock->shouldReceive('supports')->andReturn(true);
            $mock->shouldReceive('forServer')->andReturn($connector);
        });

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('hosting')->plainTextToken)
            ->getJson('/api/hosting/'.$domain->id)
            ->assertOk()
            ->assertJsonPath('data.php_version', '8.2')
            ->assertJsonPath('data.site_type', 'WordPress');

        $saved = $domain->fresh();
        $this->assertSame('8.2', $saved->php_version);
        $this->assertSame('WordPress', $saved->site_type);
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
