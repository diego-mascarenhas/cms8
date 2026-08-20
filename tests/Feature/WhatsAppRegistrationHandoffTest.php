<?php

namespace Tests\Feature;

use App\Http\Controllers\ChatController;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use App\Services\ChatAssistantReplyService;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppInboundContactRegistrationService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppRegistrationHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://localhost:3000');
        Config::set('whatsapp.local.webhook_secret', null);

        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
        Role::findOrCreate('admin');
        Bus::fake();
        Mail::fake();
    }

    public function test_webhook_skips_registration_for_team_admin_phone_on_users_table(): void
    {
        $admin = User::factory()->create(['phone' => 722372858]);
        $admin->assignRole('admin');
        $team = Team::factory()->create(['user_id' => $admin->id]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Conversation::create([
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'from' => '34722372858',
            'to' => '34600000001',
            'body' => 'Earlier today',
            'created_at' => now(),
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'out_admin_skip_reg'], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372858',
            'to' => '34600000001',
            'body' => 'Hola, soy el admin',
            'id' => 'msg_admin_skip_registration',
            'team_id' => $team->id,
        ]);

        $response->assertOk();
        $response->assertJsonMissing(['registration' => true]);

        Http::assertNotSent(function ($request): bool
        {
            if (! str_contains($request->url(), '/send-message'))
            {
                return false;
            }

            $body = (string) ($request->data()['body'] ?? '');

            return str_contains($body, 'nombre y apellido');
        });
    }

    public function test_process_registration_returns_handoff_when_team_auto_respond_disabled(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Conversation::create([
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'from' => '34600000001',
            'to' => '34722372851',
            'body' => __('app.whatsapp_registration_ask_email', ['name' => 'Pepe']),
            'metadata' => [
                'registration_step' => 'email',
                'contact_first_name' => 'Pepe',
                'contact_last_name' => 'Suárez',
                'contact_full_name' => 'Pepe Suárez',
            ],
        ]);

        Http::fake([
            'localhost:3000/*' => fn () => Http::response(['success' => true, 'id' => 'out_'.uniqid('', true)], 200),
        ]);

        $gateway = new LocalWhatsAppGateway('http://localhost:3000', null, $team->id);

        $usersBefore = User::count();

        $result = app(ChatController::class)->processRegistration(
            '34722372851',
            'pepe.handoff@example.com',
            $gateway,
            $team,
        );

        $this->assertSame($usersBefore, User::count());
        $this->assertIsArray($result);
        $this->assertSame('Contact registered', $result['message'] ?? null, json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertTrue($result['handoff'] ?? false, json_encode($result, JSON_THROW_ON_ERROR));

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'pepe.handoff@example.com',
            'phone' => '34722372851',
            'name' => 'Pepe',
            'surname' => 'Suárez',
        ]);
    }

    public function test_webhook_completes_registration_with_handoff_when_auto_respond_disabled(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Conversation::create([
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'from' => '34600000001',
            'to' => '34722372852',
            'body' => __('app.whatsapp_registration_ask_email', ['name' => 'Pepe']),
            'metadata' => [
                'registration_step' => 'email',
                'contact_first_name' => 'Pepe',
                'contact_last_name' => 'Suárez',
                'contact_full_name' => 'Pepe Suárez',
            ],
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        Http::fake([
            'localhost:3000/*' => fn () => Http::response(['success' => true, 'id' => 'out_'.uniqid('', true)], 200),
        ]);

        $handoffText = __('app.whatsapp_registration_complete_handoff', ['name' => 'Pepe']);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372852',
            'to' => '34600000001',
            'body' => 'pepe@pepe.com',
            'id' => 'msg_registration_handoff',
            'team_id' => $team->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'pepe@pepe.com',
            'phone' => '34722372852',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'pepe@pepe.com',
        ]);
        $response->assertJson([
            'status' => 'success',
            'registration' => true,
            'registration_handoff' => true,
        ]);

        Http::assertSent(function ($request) use ($handoffText): bool
        {
            if (! str_contains($request->url(), '/send-message'))
            {
                return false;
            }

            $body = $request->data()['body'] ?? '';

            return str_contains((string) $body, $handoffText);
        });
    }

    public function test_webhook_starts_registration_when_auto_respond_disabled_and_contact_unknown(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'out_reg_start'], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372859',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_registration_start',
            'team_id' => $team->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'registration' => true,
        ]);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', '34722372859')
            ->first();
        $this->assertNotNull($contact);
        $this->assertFalse(
            app(WhatsAppInboundContactRegistrationService::class)
                ->contactHasCompletedRegistration($contact),
        );

        Http::assertSent(function ($request): bool
        {
            if (! str_contains($request->url(), '/send-message'))
            {
                return false;
            }

            $body = (string) ($request->data()['body'] ?? '');

            return str_contains($body, 'nombre y apellido');
        });
    }

    public function test_webhook_skips_registration_when_contact_assistant_is_off(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Contacto 34722372860',
            'phone' => '34722372860',
            'data' => (object) ['chat_assistant_ai_enabled' => false],
            'status_id' => 1,
            'creator_id' => $team->user_id,
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'out_reg_skip_toggle'], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372860',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_registration_toggle_off',
            'team_id' => $team->id,
        ])->assertOk()->assertJsonMissing(['registration' => true]);

        Http::assertNotSent(function ($request): bool
        {
            return str_contains($request->url(), '/send-message')
                && str_contains((string) ($request->data()['body'] ?? ''), 'nombre y apellido');
        });
    }

    public function test_webhook_skips_registration_for_staff_named_contact(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Ana',
            'surname' => 'Pérez',
            'phone' => '34722372861',
            'status_id' => 1,
            'creator_id' => $team->user_id,
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'out_reg_skip_named'], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372861',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_registration_named',
            'team_id' => $team->id,
        ])->assertOk()->assertJsonMissing(['registration' => true]);

        Http::assertNotSent(function ($request): bool
        {
            return str_contains($request->url(), '/send-message')
                && str_contains((string) ($request->data()['body'] ?? ''), 'mesa de ayuda');
        });
    }

    public function test_webhook_skips_registration_when_peer_is_another_linked_team(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');
        config(['humano_pricing.plan_access_team_ids' => []]);

        $peer = Team::factory()->create();
        $peer->setSetting('assistant_auto_respond', '1');
        $peer->setSetting(\App\Services\TeamSiteAssistantPromptService::SETTING_KEY, 'calendar:assistant_citas');

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'out_reg_skip_peer'], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372862',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_registration_peer',
            'team_id' => $team->id,
            'peer_linked_team_id' => $peer->id,
        ])->assertOk()->assertJsonMissing(['registration' => true]);

        Http::assertNotSent(function ($request): bool
        {
            return str_contains($request->url(), '/send-message');
        });
    }

    public function test_registration_does_not_resend_the_same_welcome(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $welcome = __('app.whatsapp_registration_ask_full_name');

        Conversation::create([
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'from' => '34600000001',
            'to' => '34722372863',
            'body' => $welcome,
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'out_reg_dedup'], 200),
        ]);

        $result = app(ChatController::class)->processRegistration(
            '34722372863',
            'Hola',
            new LocalWhatsAppGateway('http://localhost:3000', null, $team->id),
            $team,
        );

        $this->assertSame('Registration already sent', $result['message'] ?? null);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/send-message'));
    }
}
