<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainEmailAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_email_account_creates_pop_via_cpanel(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => [
                    'status' => 1,
                    'data' => null,
                ],
            ]),
        ]);

        [$user, $domain] = $this->createDomainWithServer();

        $response = $this->actingAs($user)->post(route('domain.emails.store', $domain->id), [
            'email' => 'info',
            'password' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), '/json-api/cpanel')
                && ($request['cpanel_jsonapi_func'] ?? null) === 'add_pop'
                && ($request['email'] ?? null) === 'info'
                && ($request['domain'] ?? null) === 'example.test';
        });
    }

    public function test_store_email_account_requires_strong_password(): void
    {
        [$user, $domain] = $this->createDomainWithServer();

        $response = $this->actingAs($user)->from(route('domain.show', $domain->id))->post(route('domain.emails.store', $domain->id), [
            'email' => 'info',
            'password' => 'weak',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHasErrors(['password']);
    }

    public function test_update_email_password_calls_passwdpop(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => [
                    'status' => 1,
                ],
            ]),
        ]);

        [$user, $domain] = $this->createDomainWithServer();

        $response = $this->actingAs($user)->post(route('domain.email-password', $domain->id), [
            'email' => 'info@example.test',
            'password' => 'NewSecure123!',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), '/json-api/cpanel')
                && ($request['cpanel_jsonapi_func'] ?? null) === 'passwdpop'
                && ($request['email'] ?? null) === 'info'
                && ($request['domain'] ?? null) === 'example.test';
        });
    }

    public function test_domain_show_displays_email_accounts_with_capacity(): void
    {
        Http::fake();

        [$user, $domain] = $this->createDomainWithServer([
            'data' => [
                'email_accounts' => [
                    [
                        'email' => 'info@example.test',
                        'diskused_mb' => 25.5,
                        'diskquota_mb' => 100.0,
                        'usage_percent' => 26,
                        'unlimited' => false,
                    ],
                ],
                'account_disk' => [
                    'used_mb' => 0.3,
                    'limit_mb' => 2048.0,
                    'unlimited' => false,
                    'usage_percent' => 0,
                ],
                'last_refreshed' => now()->toIso8601String(),
                'nameservers' => ['nsg1.namebrightdns.com', 'nsg2.namebrightdns.com'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('domain.show', $domain->id));

        $response->assertOk();
        $response->assertSee('Detalle del dominio');
        $response->assertSee('Configuración DNS');
        $response->assertSee('Servidores DNS requeridos');
        $response->assertSee('NS1.REVISIONALPHA.COM');
        $response->assertSee('NS2.REVISIONALPHA.COM');
        $response->assertSee('DNS actuales (públicos)');
        $response->assertSee('nsg1.namebrightdns.com');
        $response->assertSee('ti-alert-triangle', false);
        $response->assertDontSee('Los DNS no coinciden', false);
        $response->assertSee('Dominio');
        $response->assertSee('Capacidad');
        $response->assertSee('Servidor');
        $response->assertSee('Sitio');
        $response->assertSee('Plan de hosting');
        $response->assertSee('Dominio:');
        $response->assertSee('Usuario cPanel:');
        $response->assertSee('Tipo de sitio:');
        $response->assertSee('Versión PHP:');
        $response->assertSee('Estado SSL:');
        $response->assertSee('Observaciones:');
        $response->assertSee('Notas:');
        $response->assertSee('Cuentas de correo');
        $response->assertSee('info@example.test');
        $response->assertSee('Crear email');
        $response->assertSee('25.5 / 100 MB');
        $response->assertDontSee('Domain Details');
        $response->assertDontSee('Change Plan');
        $response->assertDontSee('Status Flags');
        Http::assertNothingSent();
    }

    public function test_domain_show_hides_dns_suggestions_when_public_records_are_correct(): void
    {
        Http::fake();

        [$user, $domain] = $this->createDomainWithServer([
            'data' => [
                'nameservers' => ['ns1.revisionalpha.com', 'ns2.revisionalpha.com'],
                'public_spf_check' => [
                    'exists' => true,
                    'has_mailbaby' => true,
                    'record' => 'v=spf1 include:spf.revisionalpha.com -all',
                ],
                'last_refreshed' => now()->toIso8601String(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('domain.show', $domain->id));

        $response->assertOk();
        $response->assertSee('DNS actuales (públicos)');
        $response->assertSee('ns1.revisionalpha.com');
        $response->assertSee('SPF público (DNS)');
        $response->assertDontSee('Servidores DNS requeridos');
        $response->assertDontSee('SPF recomendado');
        $response->assertDontSee('Configura estos NS en el registrador del dominio');
    }

    /**
     * @param  array<string, mixed>  $domainAttributes
     * @return array{0: User, 1: Domain}
     */
    private function createDomainWithServer(array $domainAttributes = []): array
    {
        $user = User::factory()->create();
        $team = \App\Models\Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create(array_merge([
            'domain' => 'example.test',
            'server_id' => $server->id,
            'username' => 'siteuser',
        ], $domainAttributes));

        return [$user, $domain];
    }
}
