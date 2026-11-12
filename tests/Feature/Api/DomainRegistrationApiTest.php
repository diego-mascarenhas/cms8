<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use App\Services\Domains\EnterpriseDomainCharge;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_reports_the_marked_up_price(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 9.73,
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/check', ['domain' => 'https://www.ejemplo.com/'])
            ->assertOk()
            ->assertJsonPath('data.domain', 'ejemplo.com')
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.cost', 9.73)
            ->assertJsonPath('data.price', 12.65)
            ->assertJsonPath('data.markup_percent', 30)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.suggestions', []);

        Http::assertSentCount(1);
    }

    public function test_check_suggests_free_alternatives_when_the_domain_is_taken(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'no',
            ]),
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.net' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 12.5,
            ]),
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/*' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'no',
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/check', ['domain' => 'ejemplo.com'])
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.suggestions.0.domain', 'ejemplo.net')
            ->assertJsonPath('data.suggestions.0.available', true)
            ->assertJsonPath('data.suggestions.0.cost', 12.5)
            ->assertJsonPath('data.suggestions.0.price', 16.25)
            ->assertJsonCount(1, 'data.suggestions');
    }

    public function test_check_reads_the_nested_porkbun_quote(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
                'sandbox' => true,
                'response' => [
                    'avail' => 'yes',
                    'price' => '11.81',
                ],
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/check', ['domain' => 'ejemplo.com'])
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.cost', 11.81)
            ->assertJsonPath('data.price', 15.35);
    }

    public function test_register_charges_then_buys_the_domain_and_points_the_nameservers(): void
    {
        $user = $this->admin();
        $enterprise = $this->enterprise($user->currentTeam);
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.default_nameservers' => ['NS1.REVISIONALPHA.COM', 'NS2.REVISIONALPHA.COM'],
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 9.73,
            ]),
            'https://api.porkbun.com/api/json/v3/domain/create/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
            ]),
            'https://api.porkbun.com/api/json/v3/domain/updateNs/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
            ]),
        ]);
        $this->mock(EnterpriseDomainCharge::class, function ($mock) use ($enterprise)
        {
            $mock->shouldReceive('charge')
                ->once()
                ->withArgs(function (Enterprise $found, int $amountCents, string $currency) use ($enterprise): bool
                {
                    return $found->id === $enterprise->id
                        && $amountCents === 1265
                        && strtoupper($currency) === 'USD';
                })
                ->andReturn([
                    'payment_intent_id' => 'pi_test_domain',
                    'amount_cents' => 1265,
                    'currency' => 'USD',
                ]);
            $mock->shouldReceive('refund')->never();
        });

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/register', [
                'domain' => 'ejemplo.com',
                'enterprise_id' => $enterprise->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.domain', 'ejemplo.com')
            ->assertJsonPath('data.price', 12.65)
            ->assertJsonPath('data.cost', 9.73)
            ->assertJsonPath('data.charged', true)
            ->assertJsonPath('data.payment_intent_id', 'pi_test_domain')
            ->assertJsonPath('data.nameservers_set', true)
            ->assertJsonPath('data.nameservers.0', 'ns1.revisionalpha.com');

        $this->assertDatabaseHas('services', [
            'enterprise_id' => $enterprise->id,
            'description' => 'Dominio ejemplo.com (anual)',
        ]);

        Http::assertSent(function ($request): bool
        {
            $body = $request->data();

            return $request->url() === 'https://api.porkbun.com/api/json/v3/domain/create/ejemplo.com'
                && $body['cost'] === 973
                && $body['agreeToTerms'] === 'yes'
                && $request->header('Idempotency-Key')[0] === 'register-ejemplo.com';
        });
        Http::assertSent(function ($request): bool
        {
            return $request->url() === 'https://api.porkbun.com/api/json/v3/domain/updateNs/ejemplo.com'
                && $request->data()['ns'] === ['ns1.revisionalpha.com', 'ns2.revisionalpha.com'];
        });
    }

    public function test_register_does_not_buy_a_taken_domain(): void
    {
        $user = $this->admin();
        $enterprise = $this->enterprise($user->currentTeam);
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'no',
                'price' => 9.73,
            ]),
        ]);
        $this->mock(EnterpriseDomainCharge::class, function ($mock)
        {
            $mock->shouldReceive('charge')->never();
        });

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/register', [
                'domain' => 'ejemplo.com',
                'enterprise_id' => $enterprise->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ese dominio no está disponible.');

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/domain/create/'));
    }

    public function test_register_requires_enterprise_id(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
        ]);
        Http::fake();

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/register', ['domain' => 'ejemplo.com'])
            ->assertStatus(422);
    }

    public function test_check_requires_the_porkbun_keys(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => '',
            'services.porkbun.secret' => '',
        ]);
        Http::fake();

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/check', ['domain' => 'ejemplo.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Porkbun no está configurado. Faltan la API key y la secret API key.');

        Http::assertNothingSent();
    }

    public function test_check_translates_a_restricted_api_key(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/tester.com' => Http::response([
                'status' => 'ERROR',
                'code' => 'DOMAIN_NOT_ALLOWED',
                'message' => 'This API key is restricted to specific domains and `tester.com` is not in the allowed list.',
            ], 403),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/check', ['domain' => 'tester.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta clave de API solo puede usarse en ciertos dominios y tester.com no está en la lista permitida.');
    }

    public function test_check_hides_an_unknown_english_error(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/tester.com' => Http::response([
                'status' => 'ERROR',
                'message' => 'Something unexpected happened on the registrar.',
            ], 400),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/check', ['domain' => 'tester.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Porkbun no pudo completar la operación.');
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

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user;
    }
}
