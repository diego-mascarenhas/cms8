<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\Domains\DomainCheckoutSessions;
use App\Services\Hosting\FlexHostingProvisioner;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DomainCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_registers_a_new_team_admin_and_keeps_flex_free(): void
    {
        Notification::fake();
        $this->seed([EnterpriseTypeSeeder::class, EnterpriseStatusSeeder::class]);
        $registrar = $this->registrar();
        Server::factory()->create([
            'team_id' => $registrar['team']->id,
            'name' => 'odin',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
        ]);
        $stripe = new FakeDomainCheckoutSessions;
        $this->app->instance(DomainCheckoutSessions::class, $stripe);
        $this->mock(FlexHostingProvisioner::class, function ($mock): void
        {
            $mock->shouldReceive('provision')->once()->andReturn([
                'password' => 'clave-flex',
                'plan' => 'Flex',
            ]);
        });
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/libre.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 10,
            ]),
            'https://api.porkbun.com/api/json/v3/domain/create/libre.com' => Http::response([
                'status' => 'SUCCESS',
            ]),
            'https://api.porkbun.com/api/json/v3/domain/updateNs/libre.com' => Http::response([
                'status' => 'SUCCESS',
            ]),
        ]);

        $start = $this->withHeader('Authorization', 'Bearer '.$registrar['token'])
            ->postJson('/api/domains/checkout', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.plan', 'Flex')
            ->assertJsonPath('data.plan_amount', 0)
            ->assertJsonPath('data.domain', 'libre.com')
            ->assertJsonPath('data.price', 13)
            ->assertJsonPath('data.account', 'created')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/c/pay')
            ->assertJsonMissingPath('data.cost');

        $user = User::query()->where('email', 'ana@ejemplo.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Team::query()->where('user_id', $user->id)->exists());
        $this->assertSame('cus_new', $stripe->customers[0]['email'] === 'ana@ejemplo.com' ? 'cus_new' : '');

        $this->withHeader('Authorization', 'Bearer '.$registrar['token'])
            ->postJson('/api/domains/checkout/complete', ['session_id' => 'cs_test_domain'])
            ->assertOk()
            ->assertJsonPath('data.domain', 'libre.com')
            ->assertJsonPath('data.plan', 'Flex')
            ->assertJsonPath('data.hosting.username', 'libre')
            ->assertJsonPath('data.hosting.password', 'clave-flex')
            ->assertJsonMissingPath('data.cost');

        $start->assertJsonMissingPath('data.markup_percent');
    }

    public function test_checkout_reuses_an_existing_team_admin(): void
    {
        Notification::fake();
        $this->seed([EnterpriseTypeSeeder::class, EnterpriseStatusSeeder::class]);
        $registrar = $this->registrar();
        $owner = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => true]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'email' => 'ana@ejemplo.com',
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));
        $enterprise->setStripeCustomerId('cus_kept');
        $enterprise->save();
        $stripe = new FakeDomainCheckoutSessions;
        $this->app->instance(DomainCheckoutSessions::class, $stripe);
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/libre.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 10,
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$registrar['token'])
            ->postJson('/api/domains/checkout', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.account', 'existing');

        $this->assertSame([], $stripe->customers);
        $this->assertSame(1, User::query()->where('email', 'ana@ejemplo.com')->count());
    }

    public function test_checkout_reuses_a_team_the_user_already_administers(): void
    {
        Notification::fake();
        $this->seed([EnterpriseTypeSeeder::class, EnterpriseStatusSeeder::class]);
        $registrar = $this->registrar();
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $admin = User::factory()->create(['email' => 'ana@ejemplo.com']);
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'email' => 'ana@ejemplo.com',
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));
        $enterprise->setStripeCustomerId('cus_kept');
        $enterprise->save();
        $stripe = new FakeDomainCheckoutSessions;
        $this->app->instance(DomainCheckoutSessions::class, $stripe);
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/libre.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 10,
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$registrar['token'])
            ->postJson('/api/domains/checkout', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.account', 'existing');

        $this->assertSame([], $stripe->customers);
        $this->assertSame(0, Team::query()->where('user_id', $admin->id)->count());
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'domain' => 'libre.com',
            'name' => 'Ana Pérez',
            'email' => 'ana@ejemplo.com',
            'phone' => '+59811111111',
            'country' => 'UY',
            'tax_id' => '123456789012',
            'business_name' => 'Ana SA',
            'success_url' => 'https://revisionalpha.test/success?session_id={CHECKOUT_SESSION_ID}&kind=domain',
            'cancel_url' => 'https://revisionalpha.test/cancel',
        ];
    }

    public function test_checkout_keeps_a_valid_referral_code(): void
    {
        Notification::fake();
        $this->seed([EnterpriseTypeSeeder::class, EnterpriseStatusSeeder::class]);
        $registrar = $this->registrar();
        Team::factory()->create([
            'user_id' => User::factory()->create()->id,
            'stripe_id' => 'cus_Referrer1',
        ]);
        $stripe = new FakeDomainCheckoutSessions;
        $this->app->instance(DomainCheckoutSessions::class, $stripe);
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/libre.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 10,
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$registrar['token'])
            ->postJson('/api/domains/checkout', $this->payload() + ['ref' => 'cus_Referrer1'])
            ->assertOk();

        $team = User::query()->where('email', 'ana@ejemplo.com')->first()?->currentTeam;
        $this->assertSame('cus_Referrer1', $team?->referred_by);
        $this->assertSame('cus_Referrer1', $stripe->sessions[0]['metadata']['referred_by'] ?? null);
        $this->assertSame('cus_Referrer1', $stripe->sessions[0]['subscription_data']['metadata']['referred_by'] ?? null);
    }

    /**
     * @return array{token: string, team: Team}
     */
    private function registrar(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->teams()->attach($team->id, ['role' => 'admin']);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return [
            'token' => $team->createApiToken('Revision Alpha')['plain'],
            'team' => $team,
        ];
    }
}

class FakeDomainCheckoutSessions extends DomainCheckoutSessions
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $customers = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $sessions = [];

    public function createCustomer(array $payload): string
    {
        $this->customers[] = $payload;

        return 'cus_new';
    }

    public function create(array $payload): object
    {
        $this->sessions[] = $payload;

        return (object) [
            'id' => 'cs_test_domain',
            'url' => 'https://checkout.stripe.test/c/pay',
        ];
    }

    public function retrieve(string $sessionId): object
    {
        $enterprise = Enterprise::withoutGlobalScope('team')->where('code', 'cus_new')->first();

        return (object) [
            'id' => $sessionId,
            'payment_status' => 'paid',
            'metadata' => [
                'type' => 'domain',
                'plan' => 'flex',
                'domain' => 'libre.com',
                'enterprise_id' => (string) ($enterprise?->id ?? 0),
                'user_id' => (string) ($enterprise?->creator_id ?? 0),
            ],
            'subscription' => (object) [
                'id' => 'sub_domain',
                'status' => 'active',
            ],
        ];
    }
}
