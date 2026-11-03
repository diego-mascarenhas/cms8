<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Server;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AssistantToolsService;
use App\Services\Hosting\HostingCustomerAssistantService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostingCustomerAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ContactStatusSeeder::class,
        ]);

        config(['whatsapp.driver' => 'twilio']);
        config(['whatsapp.customer_service_window.enabled' => false]);
    }

    public function test_lists_only_the_contact_cpanel_hosting(): void
    {
        [$user, , $contact] = $this->createHosting();

        $text = app(HostingCustomerAssistantService::class)->listForContact(
            (int) $user->current_team_id,
            (int) $contact->id,
        );

        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('cpanel', $text);
    }

    public function test_email_address_selects_one_domain_when_the_contact_has_several(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => ['status' => 1, 'data' => null],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, $domain, $contact] = $this->createHosting();
        $this->addDomain($domain, 'otro.test', 'otrousuario');
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->manageMailbox(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'create',
            null,
            'hola@otro.test',
        );

        $this->assertStringContainsString('hola@otro.test', $text);
        $this->assertStringNotContainsString('más de un hosting', $text);
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'add_pop'
            && ($request['domain'] ?? null) === 'otro.test'
            && ($request['cpanel_jsonapi_user'] ?? null) === 'otrousuario');
    }

    public function test_listing_mailboxes_includes_every_domain(): void
    {
        Http::fake(function ($request)
        {
            $user = (string) ($request['cpanel_jsonapi_user'] ?? '');
            $email = $user === 'otrousuario' ? 'hola@otro.test' : 'info@example.test';

            return Http::response([
                'result' => [
                    'status' => 1,
                    'data' => [
                        ['email' => $email],
                    ],
                ],
            ]);
        });

        [$user, $domain, $contact] = $this->createHosting();
        $this->addDomain($domain, 'otro.test', 'otrousuario');

        $text = app(HostingCustomerAssistantService::class)->manageMailbox(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'list',
            null,
            null,
        );

        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('info@example.test', $text);
        $this->assertStringContainsString('otro.test', $text);
        $this->assertStringContainsString('hola@otro.test', $text);
    }

    public function test_a_unique_fragment_selects_that_domain(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, $domain, $contact] = $this->createHosting();
        $this->addDomain($domain, 'otro.test', 'otrousuario');
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->resetCpanelAccess(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'otro',
        );

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/json-api/passwd')
            && ($request['user'] ?? null) === 'otrousuario');
    }

    public function test_reset_cpanel_password_notifies_the_same_whatsapp_thread(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        $sent = [];
        $this->bindGateway($sent);

        [$user, , $contact] = $this->createHosting();
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $tools = app(AssistantToolsService::class);
        $tools->setRequestContext($user->id, (int) $user->current_team_id, '34611223344', (int) $contact->id);

        $text = $tools->execute('reset_cpanel_password', []);

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        $this->assertStringNotContainsString('Contraseña:', $text);
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Usuario: siteuser', $sent[0]['message']);
        $this->assertStringContainsString('Contraseña:', $sent[0]['message']);
    }

    public function test_create_mailbox_sends_imap_and_smtp_settings(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => ['status' => 1, 'data' => null],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, , $contact] = $this->createHosting();
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->manageMailbox(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'create',
            null,
            'info',
        );

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        $this->assertStringContainsString('Puerto IMAP: 993', $sent[0]['message']);
        $this->assertStringContainsString('info@example.test', $sent[0]['message']);
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'add_pop'
            && ($request['email'] ?? null) === 'info');
    }

    public function test_create_ftp_sends_host_and_port(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => ['status' => 1, 'data' => null],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, , $contact] = $this->createHosting();
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->manageFtp(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'create',
            'example.test',
            'web',
        );

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        $this->assertStringContainsString('Puerto: 21', $sent[0]['message']);
        $this->assertStringContainsString('web@example.test', $sent[0]['message']);
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'add_ftp'
            && ($request['user'] ?? null) === 'web');
    }

    public function test_does_not_touch_another_contacts_hosting(): void
    {
        [$user, , $contact] = $this->createHosting();
        $other = Contact::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Otro',
            'email' => 'otro@example.test',
            'phone' => '600000001',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $text = app(HostingCustomerAssistantService::class)->resetCpanelAccess(
            (int) $user->current_team_id,
            (int) $other->id,
            $user,
            'example.test',
        );

        $this->assertStringContainsString('no está vinculado', $text);
        $this->assertNotSame((int) $contact->id, (int) $other->id);
    }

    public function test_dns_info_lists_every_domain_and_a_named_one_alone(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $domain->forceFill([
            'data' => [
                'nameservers' => ['ns1.example.test', 'ns2.example.test'],
                'web_ip' => '203.0.113.10',
                'mx_records' => [
                    ['priority' => 0, 'target' => 'example.test'],
                ],
            ],
        ])->save();
        $other = $this->addDomain($domain, 'otro.test', 'otrousuario');
        $other->forceFill([
            'data' => [
                'nameservers' => ['ns1.otro.test'],
                'mx_records' => [
                    ['priority' => 1, 'target' => 'smtp.google.com'],
                ],
            ],
        ])->save();

        $all = app(HostingCustomerAssistantService::class)->dnsInfo(
            (int) $user->current_team_id,
            (int) $contact->id,
            null,
        );
        $this->assertStringContainsString('DNS de example.test', $all);
        $this->assertStringContainsString('ns1.example.test', $all);
        $this->assertStringContainsString('203.0.113.10', $all);
        $this->assertStringContainsString('DNS de otro.test', $all);
        $this->assertStringContainsString('smtp.google.com', $all);

        $one = app(HostingCustomerAssistantService::class)->dnsInfo(
            (int) $user->current_team_id,
            (int) $contact->id,
            'otro',
        );
        $this->assertStringContainsString('DNS de otro.test', $one);
        $this->assertStringNotContainsString('DNS de example.test', $one);
        $this->assertStringContainsString('v=spf1 include:spf.revisionalpha.com -all', $one);
    }

    public function test_google_mail_setup_explains_mx_without_changing_dns(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $domain->forceFill([
            'data' => [
                'mx_records' => [
                    ['priority' => 0, 'target' => 'example.test'],
                ],
            ],
        ])->save();

        $text = app(HostingCustomerAssistantService::class)->googleMailSetup(
            (int) $user->current_team_id,
            (int) $contact->id,
            'example.test',
        );

        $this->assertStringContainsString('smtp.google.com', $text);
        $this->assertStringContainsString('v=spf1 include:spf.revisionalpha.com -all', $text);
        $this->assertStringContainsString('include:_spf.google.com', $text);
        $this->assertStringContainsString('No cambié los DNS.', $text);
        $this->assertStringContainsString('0 example.test', $text);
        $this->assertSame(
            [['priority' => 0, 'target' => 'example.test']],
            $domain->fresh()->data['mx_records'],
        );
    }

    public function test_product_info_sends_the_public_page_and_the_stack(): void
    {
        $hosting = app(HostingCustomerAssistantService::class)->productLinks('información del hosting');

        $this->assertStringContainsString('https://revisionalpha.com/hosting', $hosting);
        $this->assertStringContainsString('https://revisionalpha.com/hosting/stack', $hosting);
        $this->assertStringNotContainsString('https://revisionalpha.com/cloud/stack', $hosting);

        $agency = app(HostingCustomerAssistantService::class)->productLinks('hosting para agencias');

        $this->assertStringContainsString('https://revisionalpha.com/hosting-agency', $agency);
        $this->assertStringNotContainsString('https://revisionalpha.com/hosting/stack', $agency);

        $unknown = app(HostingCustomerAssistantService::class)->productLinks('dominio barato');

        $this->assertStringContainsString('No hay una página pública', $unknown);
        $this->assertStringContainsString('https://revisionalpha.com/hosting', $unknown);
        $this->assertStringNotContainsString('dominio-barato', $unknown);
    }

    public function test_asks_which_domain_before_changing_several_accounts(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        Domain::factory()->create([
            'domain' => 'otro.test',
            'server_id' => $domain->server_id,
            'service_id' => $domain->service_id,
            'username' => 'otrousuario',
            'suspended' => false,
        ]);

        $text = app(HostingCustomerAssistantService::class)->resetCpanelAccess(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            null,
        );

        $this->assertStringContainsString('más de un hosting', $text);
        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('otro.test', $text);
    }

    public function test_upgrade_request_opens_a_ticket_without_changing_the_plan(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        Module::query()->firstOrCreate(
            ['key' => 'tickets'],
            ['name' => 'Tickets', 'icon' => 'ticket', 'description' => 'Tickets', 'status' => 1],
        );
        $user->currentTeam->enableModule('tickets');

        $text = app(HostingCustomerAssistantService::class)->requestUpgrade(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'example.test',
            'Necesito más espacio',
        );

        $this->assertStringContainsString('No se cambió el plan', $text);
        $ticket = Ticket::withoutGlobalScopes()->first();
        $this->assertNotNull($ticket);
        $this->assertStringContainsString('example.test', (string) $ticket->subject);
        $this->assertSame($domain->plan, $domain->fresh()->plan);
    }

    /**
     * @param  list<array<string, mixed>>  $sent
     */
    private function bindGateway(array &$sent): void
    {
        $this->app->instance(WhatsAppGateway::class, new class($sent) implements WhatsAppGateway
        {
            public function __construct(private array &$sent) {}

            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                $this->sent[] = compact('to', 'message');

                return 'ok';
            }

            public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
            {
                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function getQrUrl(): ?string
            {
                return null;
            }

            public function getConnectionStatus(): ?array
            {
                return ['status' => 'connected'];
            }
        });
    }

    private function addDomain(Domain $domain, string $name, string $username): Domain
    {
        return Domain::factory()->create([
            'domain' => $name,
            'server_id' => $domain->server_id,
            'service_id' => $domain->service_id,
            'username' => $username,
            'plan' => 'essential',
            'suspended' => false,
        ]);
    }

    /**
     * @return array{0: User, 1: Domain, 2: Contact}
     */
    private function createHosting(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->setRelation('currentTeam', $team);

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

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Demo',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'operation' => 'sell',
            'description' => 'Hosting',
            'currency_id' => 1,
            'price' => 10,
            'discount' => 0,
            'frequency' => 12,
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'example.test',
            'server_id' => $server->id,
            'service_id' => $service->id,
            'username' => 'siteuser',
            'plan' => 'essential',
            'suspended' => false,
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente',
            'surname' => 'WhatsApp',
            'email' => 'cliente@example.test',
            'phone' => '611223344',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        $enterprise->contacts()->attach($contact->id);

        return [$user->fresh(), $domain->fresh(), $contact];
    }
}
