<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Mail\CpanelAccessCredentialsMail;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainMailboxPasswordTest extends TestCase
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
    }

    public function test_reset_mailbox_password_calls_passwd_pop_and_sends_whatsapp(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => [
                    'status' => 1,
                ],
            ]),
        ]);

        config(['whatsapp.driver' => 'twilio']);
        config(['whatsapp.customer_service_window.enabled' => false]);

        $sent = [];

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

        [$user, $domain, $contact] = $this->createDomainWithClientContact();
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $response = $this->actingAs($user)->post(route('domain.email-password', $domain->id), [
            'email' => 'info@example.test',
            'password' => 'NewSecure123!',
            'notify_to' => (string) $contact->id,
            'notify_channel' => 'whatsapp',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $response->assertSessionHas('generated_mailbox_password', 'NewSecure123!');
        $response->assertSessionHas('mailbox_password_reset', true);
        $response->assertSessionHas('mailbox_access_message');
        $this->assertStringContainsString('info@example.test', (string) session('mailbox_access_message'));
        $this->assertStringContainsString('Contraseña: NewSecure123!', (string) session('mailbox_access_message'));
        $this->assertStringContainsString('webmail', strtolower((string) session('mailbox_access_message')));

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), '/json-api/cpanel')
                && ($request['cpanel_jsonapi_func'] ?? null) === 'passwd_pop'
                && ($request['email'] ?? null) === 'info'
                && ($request['domain'] ?? null) === 'example.test'
                && ($request['password'] ?? null) === 'NewSecure123!';
        });

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('info@example.test', $sent[0]['message']);
        $this->assertStringContainsString('Contraseña: NewSecure123!', $sent[0]['message']);
    }

    public function test_reset_mailbox_password_without_notify_still_shows_password(): void
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
            'notify_channel' => 'none',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $response->assertSessionHas('generated_mailbox_password');
        $response->assertSessionHas('mailbox_password_reset', true);
        $response->assertSessionHas('mailbox_access_message');

        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'passwd_pop');
    }

    public function test_reset_mailbox_password_can_notify_by_email(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => [
                    'status' => 1,
                ],
            ]),
        ]);

        Queue::fake();
        $this->seed([NotificationTypesSeeder::class]);

        [$user, $domain, $contact] = $this->createDomainWithClientContact();

        $response = $this->actingAs($user)->post(route('domain.email-password', $domain->id), [
            'email' => 'info@example.test',
            'password' => 'EmailSecure123!',
            'notify_to' => (string) $contact->id,
            'notify_channel' => 'email',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('email', (string) session('success'));

        Queue::assertPushed(\App\Jobs\SendNotificationJob::class);
    }

    public function test_reset_mailbox_password_can_notify_hosting_plan_email(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => [
                    'status' => 1,
                ],
            ]),
        ]);

        Mail::fake();

        [$user, $domain] = $this->createDomainWithServer([
            'data' => [
                'email' => 'jmgaraban@hotmail.com',
                'plan' => 'revision_beginner',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('domain.email-password', $domain->id), [
            'email' => 'info@example.test',
            'password' => 'HostingSecure123!',
            'notify_to' => 'hosting',
            'notify_channel' => 'email',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('jmgaraban@hotmail.com', (string) session('success'));
        $this->assertStringContainsString('plan de hosting', (string) session('success'));

        Mail::assertSent(CpanelAccessCredentialsMail::class, function ($mail)
        {
            return $mail->hasTo('jmgaraban@hotmail.com')
                && str_contains($mail->bodyText, 'info@example.test');
        });
    }

    public function test_domain_show_email_password_modal_offers_notify_options(): void
    {
        Http::fake();

        [$user, $domain] = $this->createDomainWithServer([
            'data' => [
                'email' => 'jmgaraban@hotmail.com',
                'email_accounts' => [
                    [
                        'email' => 'info@example.test',
                        'diskused_mb' => 1,
                        'diskquota_mb' => 100,
                        'usage_percent' => 1,
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('domain.show', $domain->id))
            ->assertOk()
            ->assertSee('Nueva contraseña de correo', false)
            ->assertSee('generate-mailbox-password', false)
            ->assertSee('mailbox_notify_channel_whatsapp', false)
            ->assertSee('Email del plan de hosting', false);
    }

    /**
     * @return array{0: User, 1: Domain}
     */
    private function createDomainWithServer(array $domainAttributes = []): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
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
            'suspended' => false,
        ], $domainAttributes));

        return [$user, $domain];
    }

    /**
     * @return array{0: User, 1: Domain, 2: Contact}
     */
    private function createDomainWithClientContact(): array
    {
        [$user, $domain] = $this->createDomainWithServer();
        $team = $user->currentTeam;

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

        $domain->forceFill(['service_id' => $service->id])->save();

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

        return [$user, $domain->fresh(), $contact];
    }
}
