<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainCpanelPasswordTest extends TestCase
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

    public function test_reset_cpanel_password_calls_whm_passwd_and_sends_whatsapp(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'metadata' => [
                    'result' => 1,
                    'reason' => 'Password changed for user “siteuser”.',
                ],
            ]),
        ]);

        config(['whatsapp.driver' => 'twilio']);

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

        $response = $this->actingAs($user)->post(route('domain.cpanel-password', $domain->id), [
            'password' => 'NewSecure123!',
            'contact_id' => $contact->id,
            'notify_channel' => 'whatsapp',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $response->assertSessionHas('generated_password', 'NewSecure123!');
        $response->assertSessionHas('cpanel_password_reset', true);

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), '/json-api/passwd')
                && ($request['user'] ?? null) === 'siteuser'
                && ($request['pass'] ?? null) === 'NewSecure123!'
                && ($request['password'] ?? null) === 'NewSecure123!'
                && (int) ($request['db_pass_update'] ?? 0) === 1;
        });

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Usuario: siteuser', $sent[0]['message']);
        $this->assertStringContainsString('Contraseña: NewSecure123!', $sent[0]['message']);
        $this->assertStringContainsString('https://cpanel.test:2083/', $sent[0]['message']);
    }

    public function test_reset_cpanel_password_without_whatsapp_still_shows_password(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        [$user, $domain] = $this->createDomainWithServer();

        $response = $this->actingAs($user)->post(route('domain.cpanel-password', $domain->id), [
            'notify_channel' => 'none',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $response->assertSessionHas('generated_password');
        $response->assertSessionHas('cpanel_password_reset', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/json-api/passwd'));
    }

    public function test_reset_cpanel_password_can_notify_by_email(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        \Illuminate\Support\Facades\Queue::fake();
        $this->seed([\Database\Seeders\NotificationTypesSeeder::class]);

        [$user, $domain, $contact] = $this->createDomainWithClientContact();

        $response = $this->actingAs($user)->post(route('domain.cpanel-password', $domain->id), [
            'password' => 'EmailSecure123!',
            'contact_id' => $contact->id,
            'notify_channel' => 'email',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('email', (string) session('success'));

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendNotificationJob::class);
    }

    public function test_reset_cpanel_password_surfaces_whm_passwd_status_message(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'passwd' => [
                    [
                        'status' => 0,
                        'statusmsg' => 'No se suministró ninguna contraseña: “pass” es un argumento necesario.',
                    ],
                ],
            ]),
        ]);

        [$user, $domain] = $this->createDomainWithServer();

        $response = $this->actingAs($user)->post(route('domain.cpanel-password', $domain->id), [
            'password' => 'NewSecure123!',
            'notify_channel' => 'none',
        ]);

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('pass', (string) session('error'));
        $this->assertStringNotContainsString('create the hosting account', (string) session('error'));
    }

    public function test_domain_show_displays_reset_cpanel_password_button(): void
    {
        Http::fake();

        [$user, $domain] = $this->createDomainWithServer();

        $this->actingAs($user)
            ->get(route('domain.show', $domain->id))
            ->assertOk()
            ->assertSee('Nueva contraseña', false)
            ->assertSee(route('domain.cpanel-password', $domain->id), false);
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
