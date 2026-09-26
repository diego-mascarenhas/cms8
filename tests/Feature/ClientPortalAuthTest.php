<?php

namespace Tests\Feature;

use App\Mail\ClientLoginCodeMail;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\MailerUsageLog;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    public function test_profile_includes_the_tax_id_and_payment_method(): void
    {
        config([
            'services.revisionalpha.url' => 'https://revisionalpha.test',
            'services.revisionalpha.portal_secret' => 'portal-secret',
        ]);

        Http::fake([
            'https://revisionalpha.test/api/portal/profile' => Http::response([
                'name' => 'Ignacio Escobar',
                'phone' => '+5493413661548',
                'company_name' => 'Ignacio Escobar',
                'tax_id' => '20-2733040-11',
                'payment_method' => [
                    'type' => 'card',
                    'brand' => 'VISA',
                    'last4' => '2012',
                    'expires' => '09/2028',
                    'verified' => true,
                    'label' => 'VISA •••• 2012',
                ],
            ]),
        ]);

        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Local',
            'email' => 'cliente@example.com',
        ]);
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/client/profile')
            ->assertOk()
            ->assertJsonPath('tax_id', '20-2733040-11')
            ->assertJsonPath('company_name', 'Ignacio Escobar')
            ->assertJsonPath('payment_method.last4', '2012')
            ->assertJsonPath('payment_method.label', 'VISA •••• 2012');
    }

    public function test_client_sees_tickets_for_their_email(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'cliente@example.com',
        ]);
        $otherTeam = Team::factory()->create();
        Ticket::factory()->create([
            'team_id' => $otherTeam->id,
            'user_id' => $user->id,
            'subject' => 'No funciona la web',
            'status' => 'closed',
            'priority' => 'urgent',
        ]);

        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/client/tickets')
            ->assertOk()
            ->assertJsonPath('tickets.0.subject', 'No funciona la web')
            ->assertJsonPath('tickets.0.status_label', 'Cerrado')
            ->assertJsonPath('tickets.0.priority_label', 'Urgente');
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

    public function test_staff_replies_use_the_agent_name_instead_of_the_company(): void
    {
        $client = User::factory()->withPersonalTeam()->create([
            'name' => 'Ignacio Escobar',
            'email' => 'cliente@example.com',
        ]);
        $company = User::factory()->create([
            'name' => 'REVISION ALPHA S.A.S.',
            'email' => 'administracion@revisionalpha.es',
        ]);
        $agent = User::factory()->create([
            'name' => 'Danny Chicco',
            'email' => 'danny@revisionalpha.es',
        ]);
        $team = Team::factory()->create();
        $ticket = Ticket::factory()->create([
            'team_id' => $team->id,
            'user_id' => $client->id,
            'subject' => 'Sitio caído',
            'status' => 'closed',
        ]);
        DB::table('languages')->insert(['code' => 'es', 'name' => 'Español']);
        DB::table('countries')->insert(['id' => 724, 'name' => 'España', 'code' => 'ES']);
        DB::table('contact_statuses')->insert(['name' => 'Activo']);
        Contact::query()->create([
            'team_id' => $team->id,
            'user_id' => $company->id,
            'name' => 'Diego',
            'surname' => 'Mascarenhas',
            'email' => 'administracion@revisionalpha.es',
            'creator_id' => $company->id,
            'country' => 724,
            'language' => 'es',
            'status_id' => 1,
        ]);
        TicketResponse::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $company->id,
            'message' => 'Lo estamos revisando.',
        ]);
        TicketResponse::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'message' => 'Ya quedó en línea.',
        ]);

        $token = $client->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/client/tickets')
            ->assertOk()
            ->assertJsonFragment([
                'author' => 'Diego Mascarenhas',
                'message' => 'Lo estamos revisando.',
                'is_staff' => true,
            ])
            ->assertJsonFragment([
                'author' => 'Danny Chicco',
                'message' => 'Ya quedó en línea.',
            ]);
    }

    public function test_client_can_report_a_manual_invoice_payment(): void
    {
        Storage::fake('public');
        config([
            'services.revisionalpha.url' => 'https://revisionalpha.test',
            'services.revisionalpha.portal_secret' => 'portal-secret',
        ]);
        Http::fake([
            'https://revisionalpha.test/api/portal/billing' => Http::response([
                'invoices' => [[
                    'id' => 'in_open',
                    'number' => '0005-1025',
                    'date' => '02/08/2026',
                    'amount' => 10.5,
                    'balance' => 10.5,
                    'currency' => 'EUR',
                    'status' => 'open',
                    'status_label' => 'Pendiente',
                ]],
                'services' => [],
                'payments' => [],
            ]),
        ]);

        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'cliente@example.com',
        ]);
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->post('/api/client/invoices/in_open/payment', [
                'paid_on' => '2026-09-26',
                'amount' => '10.50',
                'method' => 'Transferencia',
                'notes' => 'Pago por transferencia',
                'receipt' => UploadedFile::fake()->create('recibo.pdf', 20, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('payment.method', 'Transferencia')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.status_label', 'Pendiente')
            ->assertJsonPath('payment.date', '26/09/2026')
            ->assertJsonPath('payment.receipt_name', 'recibo.pdf');

        $this->assertDatabaseHas('payments', [
            'source_provider' => 'manual',
            'source_reference_id' => 'portal:in_open',
            'status' => 3,
        ]);

        $this->withToken($token)
            ->getJson('/api/client/invoices')
            ->assertOk()
            ->assertJsonPath('invoices.0.payment.status_label', 'Pendiente')
            ->assertJsonPath('invoices.0.payment.receipt_name', 'recibo.pdf');
    }

    public function test_dashboard_returns_the_team_consumption(): void
    {
        config(['services.revisionalpha.portal_secret' => '']);

        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'repuestos@example.com',
        ]);
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('whatsapp_from', '34999000111');

        MailerUsageLog::query()->create([
            'team_id' => $team->id,
            'source' => 'mailer',
            'count' => 3,
            'sent_at' => now(),
        ]);
        Conversation::query()->create([
            'message_sid' => 'SM_client_usage',
            'channel' => 'whatsapp',
            'from' => '34999000111',
            'to' => '34600111222',
            'body' => 'Hola',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);
        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/client/dashboard')
            ->assertOk()
            ->assertJsonPath('usage.emails', 3)
            ->assertJsonPath('usage.whatsapp', 1)
            ->assertJsonPath('usage.ai_tokens', 10_000_000);
    }

    public function test_client_can_create_a_ticket(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'cliente@example.com',
        ]);
        $team = Team::factory()->create();
        config(['organization.revision_alpha_team_id' => $team->id]);
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/client/tickets', [
                'subject' => 'Prune local',
                'description' => 'ok',
                'priority' => 'low',
            ])
            ->assertCreated()
            ->assertJsonPath('subject', 'Prune local')
            ->assertJsonPath('priority', 'low')
            ->assertJsonPath('status_label', 'Abierto');

        $this->withToken($token)
            ->getJson('/api/client/tickets')
            ->assertOk()
            ->assertJsonPath('tickets.0.subject', 'Prune local');
    }
}
