<?php

namespace Tests\Feature;

use App\Mail\ClientLoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_email_is_not_registered(): void
    {
        $this->postJson('/api/client/auth/email', [
            'email' => 'nadie@example.com',
        ])->assertNotFound()
            ->assertJsonPath('registered', false)
            ->assertJsonPath('message', 'Este email no está registrado.');
    }

    public function test_registered_client_can_verify_a_login_code(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
        ]);
        $user->forceFill([
            'current_team_id' => $user->ownedTeams()->first()->id,
        ])->save();

        $this->postJson('/api/client/auth/email', [
            'email' => 'Ana@example.com',
        ])->assertOk()
            ->assertJsonPath('registered', true);

        $code = null;
        Mail::assertSent(ClientLoginCodeMail::class, function (ClientLoginCodeMail $mail) use (&$code)
        {
            $code = $mail->code;

            return $mail->hasTo('ana@example.com');
        });

        $this->postJson('/api/client/auth/verify', [
            'email' => 'ana@example.com',
            'code' => '000000',
        ])->assertStatus(422);

        $this->postJson('/api/client/auth/verify', [
            'email' => 'ana@example.com',
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('user.name', 'Ana')
            ->assertJsonStructure(['token']);
    }

    public function test_dashboard_requires_authentication_and_starts_empty(): void
    {
        config(['services.revisionalpha.portal_secret' => '']);

        $this->getJson('/api/client/dashboard')->assertUnauthorized();

        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/client/dashboard')
            ->assertOk()
            ->assertJsonPath('invoices', [])
            ->assertJsonPath('services', [])
            ->assertJsonPath('payments', [])
            ->assertJsonPath('leads', []);

        $this->withToken($token)
            ->getJson('/api/client/tickets')
            ->assertOk()
            ->assertJsonPath('tickets', []);

        $this->withToken($token)
            ->getJson('/api/client/profile')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_dashboard_returns_revision_alpha_billing(): void
    {
        config([
            'services.revisionalpha.url' => 'https://revisionalpha.test',
            'services.revisionalpha.portal_secret' => 'portal-secret',
        ]);

        Http::fake([
            'https://revisionalpha.test/api/portal/billing' => Http::response([
                'invoices' => [[
                    'id' => 'in_1',
                    'number' => '0005-1119',
                    'date' => '14/09/2026',
                    'amount' => 24.65,
                    'balance' => 0,
                    'currency' => 'EUR',
                    'status' => 'paid',
                    'status_label' => 'Pagada',
                ]],
                'services' => [[
                    'id' => 'sub_1',
                    'name' => 'VPS Value',
                    'amount' => 49.30,
                    'currency' => 'EUR',
                    'status' => 'active',
                    'status_label' => 'Activo',
                ]],
                'payments' => [[
                    'id' => 'in_1',
                    'date' => '14/09/2026',
                    'amount' => 24.65,
                    'currency' => 'EUR',
                    'status' => 'paid',
                    'status_label' => 'Aprobado',
                    'method' => 'Visa •••• 4242',
                ]],
            ]),
        ]);

        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'cliente@example.com',
        ]);
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/client/dashboard')
            ->assertOk()
            ->assertJsonPath('invoices.0.number', '0005-1119')
            ->assertJsonPath('services.0.name', 'VPS Value')
            ->assertJsonPath('payments.0.status_label', 'Aprobado');

        $this->withToken($token)
            ->getJson('/api/client/invoices')
            ->assertOk()
            ->assertJsonPath('invoices.0.status_label', 'Pagada');

        Http::assertSent(function ($request)
        {
            return $request->url() === 'https://revisionalpha.test/api/portal/billing'
                && $request['email'] === 'cliente@example.com'
                && $request->hasHeader('X-Portal-Secret', 'portal-secret');
        });
    }

    public function test_login_code_mail_uses_mailpit_locally(): void
    {
        $this->app['env'] = 'local';
        Mail::fake();

        User::factory()->withPersonalTeam()->create([
            'email' => 'ana@example.com',
        ]);

        $this->postJson('/api/client/auth/email', [
            'email' => 'ana@example.com',
        ])->assertOk()
            ->assertJsonPath('registered', true);

        Mail::assertSent(ClientLoginCodeMail::class, function (ClientLoginCodeMail $mail)
        {
            return $mail->mailer === 'mailpit' && $mail->hasTo('ana@example.com');
        });
    }

    public function test_login_code_email_uses_the_revision_alpha_template(): void
    {
        $mail = new ClientLoginCodeMail('ana@example.com', '687149');
        $html = $mail->render();

        $this->assertSame('Acceso al Área de Clienes - REVSION ALPHA', $mail->envelope()->subject);
        $this->assertSame('administracion@revisionalpha.com', $mail->envelope()->from->address);
        $this->assertSame('REVISION ALPHA', $mail->envelope()->from->name);
        $this->assertStringContainsString('687149', $html);
        $this->assertStringContainsString('Inicio de Sesión', $html);
        $this->assertStringContainsString('#36f1cd', $html);
        $this->assertStringContainsString('#FF1A1D', $html);
        $this->assertStringContainsString('www.revisionalpha.com', $html);
        $this->assertStringContainsString('revision-alpha-new-logo-color.svg', $html);
    }
}
