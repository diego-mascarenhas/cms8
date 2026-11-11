<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_reports_the_porkbun_price(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
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
            ->assertJsonPath('data.price', 9.73)
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_register_buys_the_domain_and_points_the_nameservers(): void
    {
        $user = $this->admin();
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.default_nameservers' => ['NS1.REVISIONALPHA.COM', 'NS2.REVISIONALPHA.COM'],
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

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/register', ['domain' => 'ejemplo.com'])
            ->assertOk()
            ->assertJsonPath('data.domain', 'ejemplo.com')
            ->assertJsonPath('data.nameservers_set', true)
            ->assertJsonPath('data.nameservers.0', 'ns1.revisionalpha.com');

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

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/register', ['domain' => 'ejemplo.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ese dominio no está disponible.');

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/domain/create/'));
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
