<?php

namespace Tests\Feature;

use App\Jobs\FetchWhatsAppProfilePhotoJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Team;
use App\Models\User;
use App\Services\ChatAssistantReplyService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppLocalWebhookTest extends TestCase
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

        if (DB::table('contact_statuses')->where('id', 1)->doesntExist())
        {
            DB::table('contact_statuses')->insert([
                'id' => 1,
                'name' => 'Lead',
                'label_class' => 'bg-label-success',
            ]);
        }
    }

    public function test_webhook_rejects_request_when_secret_configured_and_missing(): void
    {
        Config::set('whatsapp.local.webhook_secret', 'secret123');

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'body' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_valid_payload_and_creates_conversation(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Test message',
            'id' => 'msg_123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('conversations', [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Test message',
            'channel' => 'whatsapp',
            'direction' => 'inbound',
        ]);
    }

    public function test_webhook_is_idempotent_for_duplicate_message_sid(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $payload = [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Repeated message',
            'id' => 'msg_duplicate_123',
        ];

        $this->postJson(route('webhook.whatsapp-local'), $payload)
            ->assertStatus(200);

        $second = $this->postJson(route('webhook.whatsapp-local'), $payload);
        $second->assertStatus(200);
        $second->assertJson(['status' => 'success', 'duplicate' => true]);

        $this->assertSame(
            1,
            Conversation::query()->where('message_sid', 'msg_duplicate_123')->count(),
        );
        $this->assertDatabaseHas('conversations', [
            'message_sid' => 'msg_duplicate_123',
            'from' => '34600000000',
            'to' => '34600000001',
        ]);
    }

    public function test_webhook_returns_202_when_team_cannot_be_resolved(): void
    {
        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Test without team',
            'id' => 'msg_no_team',
        ]);

        $response->assertStatus(202);
        $response->assertJson(['status' => 'ignored', 'reason' => 'unresolved_team']);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_webhook_returns_422_when_payload_missing_from_or_body(): void
    {
        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'to' => '34600000001',
        ]);

        $response->assertStatus(422);
    }

    public function test_webhook_applies_push_name_from_baileys_payload(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000099',
            'to' => '34600000001',
            'body' => 'Hello',
            'id' => 'msg_push_name_1',
            'push_name' => 'María WhatsApp',
        ]);

        $response->assertStatus(200);

        $this->assertNull(
            User::withoutGlobalScopes()->where('email', 'wa-34600000099@chat.placeholder')->first(),
        );

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', 34600000099)
            ->first();
        $this->assertNotNull($contact);
        $this->assertNull($contact->user_id);
        $this->assertSame('María WhatsApp', $contact->name);
        $this->assertSame(34600000099, (int) $contact->phone);
    }

    public function test_webhook_creates_contact_automatically_on_first_inbound(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '5491100000099',
            'to' => '34600000001',
            'body' => 'Hola, busco un filtro',
            'id' => 'msg_auto_contact_1',
        ]);

        $response->assertStatus(200);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('phone', 5491100000099)
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Contacto 5491100000099', $contact->name);
        $this->assertSame($team->user_id, $contact->creator_id);
    }

    public function test_webhook_stores_whatsapp_profile_photo(): void
    {
        Storage::fake('public');

        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000099',
            'to' => '34600000001',
            'body' => 'Hello',
            'id' => 'msg_profile_pic_1',
            'profile_pic_base64' => $png,
            'profile_pic_content_type' => 'image/png',
        ]);

        $response->assertStatus(200);
        Storage::disk('public')->assertExists('whatsapp/avatars/'.$team->id.'/34600000099.jpg');
    }

    public function test_webhook_does_not_fetch_avatar_when_payload_has_no_photo(): void
    {
        Bus::fake();

        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '5491100000099',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_fetch_avatar_1',
        ]);

        $response->assertStatus(200);
        Bus::assertNotDispatched(FetchWhatsAppProfilePhotoJob::class);
    }

    public function test_fetch_whatsapp_profile_photo_job_stores_avatar(): void
    {
        Storage::fake('public');

        $team = Team::factory()->create();
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        Http::fake([
            'localhost:3000/*' => Http::response([
                'pictures' => [
                    '5491100000099' => [
                        'profile_pic_base64' => $png,
                        'profile_pic_content_type' => 'image/png',
                    ],
                ],
            ], 200),
        ]);

        (new FetchWhatsAppProfilePhotoJob((int) $team->id, '5491100000099'))->handle(
            app(\App\Services\WhatsApp\WhatsAppProfilePhotoStore::class),
        );

        Storage::disk('public')->assertExists('whatsapp/avatars/'.$team->id.'/5491100000099.jpg');
    }

    public function test_webhook_creates_document_ingestion_for_incoming_media(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000099',
            'to' => '34600000001',
            'body' => 'adjunto',
            'id' => 'msg_media_ingestion_1',
            'mediaUrl' => 'https://cdn.example.com/factura-2026.pdf',
            'mediaContentType' => 'application/pdf',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'document_ingestion' => true,
            'auto_ai_skipped' => 'document_ingestion_pending',
        ]);
        $conversation = Conversation::query()->where('message_sid', 'msg_media_ingestion_1')->first();
        $this->assertNotNull($conversation);
        $this->assertDatabaseHas('document_ingestions', [
            'conversation_id' => $conversation->id,
            'document_type' => 'invoice',
            'classification_status' => 'classified',
        ]);
        $this->assertGreaterThan(0, DocumentIngestion::query()->count());
        $this->assertSame(0, $this->documentIngestionAcknowledgementCount());
    }

    public function test_webhook_creates_document_ingestion_from_media_base64_payload(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $pngBase64 = base64_encode("\x89PNG\r\n\x1a\nfake");

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000099',
            'to' => '34600000001',
            'body' => '[Image]',
            'id' => 'msg_media_base64_ingestion_1',
            'media_base64' => $pngBase64,
            'media_content_type' => 'image/png',
            'media_file_name' => 'card.png',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'document_ingestion' => true,
            'auto_ai_skipped' => 'document_ingestion_pending',
        ]);

        $conversation = Conversation::query()->where('message_sid', 'msg_media_base64_ingestion_1')->first();
        $this->assertNotNull($conversation);
        $this->assertDatabaseHas('document_ingestions', [
            'conversation_id' => $conversation->id,
        ]);
        $this->assertGreaterThan(0, DocumentIngestion::query()->count());
        $this->assertSame(0, $this->documentIngestionAcknowledgementCount());
    }

    public function test_webhook_skips_document_ingestion_reply_when_contact_disables_assistant(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'name' => 'Test',
            'surname' => 'Client',
            'email' => 'test.client.media@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => false],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'ack_should_not_send'], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => '[Image]',
            'id' => 'msg_media_contact_opt_out_1',
            'mediaUrl' => 'https://cdn.example.com/card.png',
            'mediaContentType' => 'image/png',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'document_ingestion' => true,
        ]);
        $this->assertSame(0, $this->documentIngestionAcknowledgementCount());
        Http::assertNotSent(function ($request): bool
        {
            return str_contains($request->url(), '/send-message')
                && str_contains((string) $request['body'], 'Recibi tu documento');
        });
    }

    public function test_webhook_sends_document_ingestion_reply_when_assistant_is_enabled(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'ack_media_1'], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000099',
            'to' => '34600000001',
            'body' => '[Image]',
            'id' => 'msg_media_assistant_on_1',
            'mediaUrl' => 'https://cdn.example.com/card.png',
            'mediaContentType' => 'image/png',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'document_ingestion' => true,
        ]);
        $this->assertSame(1, $this->documentIngestionAcknowledgementCount());
    }

    public function test_webhook_skips_auto_ai_when_contact_disables_assistant_even_if_team_enabled(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'name' => 'Test',
            'surname' => 'Client',
            'email' => 'test.client@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => false],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Hello contact opted out',
            'id' => 'msg_contact_opt_out_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertSame(0, Conversation::query()->where('direction', 'outbound')->count());
        Http::assertNotSent(function ($request): bool
        {
            $body = (string) ($request['body'] ?? '');

            return str_contains($request->url(), '/send-message')
                && (str_contains($body, '¡Hola') || str_contains($body, 'Hola'));
        });
    }

    public function test_webhook_skips_auto_ai_when_contact_is_off_even_for_an_admin_sender(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $admin->teams()->attach($team->id, ['role' => 'admin']);

        $team->setSetting('whatsapp_from', '34694258947');
        $team->setSetting('assistant_auto_respond', '1');
        $team->setSetting('assistant_auto_respond_admins_when_off', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34722372858',
            'name' => 'Diego',
            'surname' => 'Tester',
            'email' => 'diego.tester.optout@example.com',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'user_id' => $admin->id,
            'data' => (object) ['chat_assistant_ai_enabled' => false],
        ]);
        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34722372858',
            'name' => 'Diego',
            'surname' => '',
            'email' => 'diego.duplicate.optout@example.com',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'data' => (object) ['chat_assistant_ai_enabled' => true],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372858',
            'to' => '34694258947',
            'body' => 'Me explicas el asistente?',
            'id' => 'msg_admin_opt_out_1',
        ])->assertOk();

        $this->assertSame(0, Conversation::query()->where('direction', 'outbound')->count());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/send-message'));
    }

    public function test_webhook_skips_auto_greeting_when_contact_disables_assistant(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34722372858',
            'name' => 'Usuario 34722372858',
            'surname' => '',
            'email' => 'usuario.34722372858@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => false],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'greet_should_not_send'], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372858',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_greeting_opt_out_1',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('conversations', [
            'message_sid' => 'msg_greeting_opt_out_1',
            'direction' => 'inbound',
            'body' => 'Hola',
        ]);
        $this->assertDatabaseMissing('conversations', [
            'direction' => 'outbound',
            'to' => '34722372858',
        ]);
        Http::assertNotSent(function ($request): bool
        {
            return str_contains($request->url(), '/send-message');
        });
    }

    public function test_auto_greeting_uses_crm_name_when_known(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')->andReturn([
                'message' => '',
                'tool_results' => [],
            ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600111222',
            'name' => 'María García',
            'surname' => '',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600111222',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_greeting_named_1',
        ])->assertOk();

        $this->assertTrue(
            Conversation::query()
                ->where('direction', 'outbound')
                ->where('to', '34600111222')
                ->pluck('body')
                ->contains('¡Hola María García! 👋'),
        );
    }

    public function test_auto_greeting_omits_placeholder_user_label(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')->andReturn([
                'message' => '',
                'tool_results' => [],
            ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34722372858',
            'name' => 'Usuario 34722372858',
            'surname' => '',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34722372858',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_greeting_placeholder_1',
        ])->assertOk();

        $outbound = Conversation::query()
            ->where('direction', 'outbound')
            ->where('to', '34722372858')
            ->pluck('body');

        $this->assertTrue($outbound->contains('¡Hola! 👋'));
        $this->assertFalse($outbound->contains(fn (string $body): bool => str_contains($body, 'Usuario 34722372858')));
    }

    public function test_webhook_skips_auto_ai_when_team_disabled_even_if_contact_enables_assistant(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');
        $team->setSetting('assistant_auto_respond_admins_when_off', '0');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => true],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Hello team off',
            'id' => 'msg_team_off_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_skips_auto_ai_when_team_disabled_even_with_funnel_slug_message(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');
        $team->setSetting('assistant_auto_respond_admins_when_off', '0');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => true],
        ]);

        \App\Models\Automation::factory()->funnel()->create([
            'team_id' => $team->id,
            'slug' => 'embudo-leads',
            'is_active' => true,
            'channels' => [\App\Models\Automation::CHANNEL_WHATSAPP],
            'settings' => [
                'entry_aliases' => ['embudo', 'leads'],
            ],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'embudo-leads',
            'id' => 'msg_team_off_funnel_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_auto_ai_uses_contact_prompt_key(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->withArgs(function (...$args): bool
                {
                    return ($args[6] ?? null) === 'chat:citas_y_ventas';
                })
                ->andReturn([
                    'success' => true,
                    'text' => 'Prompt pinned reply',
                    'tool_results' => [],
                ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000000',
            'name' => 'Test',
            'surname' => 'Client',
            'email' => 'prompt.client@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) [
                'chat_assistant_ai_enabled' => true,
                'chat_assistant_prompt_key' => 'chat:citas_y_ventas',
            ],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Quiero una cita',
            'id' => 'msg_contact_prompt_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_auto_ai_answers_when_the_linked_peer_is_a_person_without_a_bot(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => 'Perfecto, te ayudo.',
                    'tool_results' => [],
                ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34694258947');
        $team->setSetting('assistant_auto_respond', '1');
        config(['humano_pricing.plan_access_team_ids' => []]);

        $peer = Team::factory()->create();
        $peer->setSetting('assistant_auto_respond', '0');

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '5491147348879',
            'to' => '34694258947',
            'body' => 'Si dale perfecto muchas gracias',
            'id' => 'msg_peer_person_1',
            'peer_linked_team_id' => $peer->id,
        ])->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/send-message'));
    }

    public function test_webhook_auto_ai_answers_a_linked_peer_when_the_chat_has_a_pinned_prompt(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => 'Dale, te paso el enlace de Assistant.',
                    'tool_results' => [],
                ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34694258947');
        $team->setSetting('assistant_auto_respond', '1');
        config(['humano_pricing.plan_access_team_ids' => []]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '5491147348879',
            'name' => 'Respuestos',
            'surname' => 'AV',
            'email' => 'info@repuestosav.example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) [
                'chat_assistant_ai_enabled' => true,
                'chat_assistant_prompt_key' => 'products:humano_assistant',
            ],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '5491147348879',
            'to' => '34694258947',
            'body' => 'Si dale perfecto muchas gracias',
            'id' => 'msg_peer_pinned_sales_1',
            'peer_linked_team_id' => 99,
        ])->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/send-message'));
    }

    public function test_webhook_auto_ai_answers_a_linked_peer_on_an_internal_sales_team(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => 'Hola, soy Assistant.',
                    'tool_results' => [],
                ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34694258947');
        $team->setSetting('assistant_auto_respond', '1');
        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '5491157359506',
            'to' => '34694258947',
            'body' => 'Hola, quiero probar Assistant',
            'id' => 'msg_peer_internal_sales_1',
            'peer_linked_team_id' => 99,
        ])->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/send-message'));
    }

    public function test_webhook_auto_ai_uses_sender_contact_prompt_when_several_share_user(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'phone' => '34600000010',
            'name' => 'First',
            'surname' => 'Chat',
            'email' => 'first.chat@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) [
                'chat_assistant_ai_enabled' => true,
                'chat_assistant_prompt_key' => 'chat:citas_y_ventas',
            ],
        ]);
        $second = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'phone' => '34600000020',
            'name' => 'Second',
            'surname' => 'Chat',
            'email' => 'second.chat@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) [
                'chat_assistant_ai_enabled' => true,
                'chat_assistant_prompt_key' => 'invoices:collections',
            ],
        ]);

        $this->mock(ChatAssistantReplyService::class, function ($mock) use ($second): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->withArgs(function (...$args) use ($second): bool
                {
                    return ($args[6] ?? null) === 'invoices:collections'
                        && (int) ($args[7] ?? 0) === (int) $second->id;
                })
                ->andReturn([
                    'success' => true,
                    'text' => 'Second contact prompt',
                    'tool_results' => [],
                ]);
        });

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000020',
            'to' => '34600000001',
            'body' => 'Hola, cambio de prompt',
            'id' => 'msg_contact_prompt_second',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_auto_ai_when_team_enabled_and_contact_phone_is_national_digits_only(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => 'Team auto reply national',
                    'tool_results' => [],
                ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '600000000',
            'name' => 'Test',
            'surname' => 'Client',
            'email' => 'national.client@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['chat_assistant_ai_enabled' => true],
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Hello national phone mismatch',
            'id' => 'msg_team_wins_national',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_skips_all_auto_replies_for_blacklisted_sender_number(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldNotReceive('getReply');
        });

        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');
        $team->setSetting('assistant_whatsapp_blacklist_numbers', "34600000000\n34611111111");

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'Hola necesito ayuda',
            'id' => 'msg_blacklist_skip_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ignored',
            'reason' => 'blacklisted_sender',
        ]);
        $this->assertDatabaseMissing('conversations', [
            'message_sid' => 'msg_blacklist_skip_1',
        ]);
    }

    public function test_webhook_keeps_category_assignment_in_inbox_and_does_not_send_it(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => 'Contact Diego (id: 122) assigned to category: ESQUINA. Do not mention the tag name to the customer.',
                    'tool_results' => [
                        'Contact Diego (id: 122) assigned to category: ESQUINA. Do not mention the tag name to the customer.',
                    ],
                    'usage' => [
                        'prompt_tokens' => 100,
                        'completion_tokens' => 20,
                        'total_tokens' => 120,
                    ],
                ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');
        config(['humano_pricing.plan_access_team_ids' => []]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000099',
            'name' => 'Diego',
            'email' => 'diego.categoria@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        Conversation::create([
            'message_sid' => 'msg_category_internal_prev',
            'channel' => 'whatsapp',
            'from' => '34600000099',
            'to' => '34600000001',
            'body' => 'ayer',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000099',
            'to' => '34600000001',
            'body' => 'Hola, tengo un Fiat',
            'id' => 'msg_category_internal_1',
        ])->assertOk();

        $note = Conversation::query()
            ->where('direction', 'outbound')
            ->where('to', '34600000099')
            ->first();

        $this->assertNotNull($note);
        $this->assertSame('Contacto asignado a la categoría: ESQUINA', $note->body);
        $this->assertTrue((bool) ($note->metadata['internal_only'] ?? false));
        $this->assertSame('internal', $note->status);

        Http::assertNotSent(function ($request): bool
        {
            $body = (string) ($request['body'] ?? '');

            return str_contains($request->url(), '/send-message')
                && (str_contains($body, 'assigned to category')
                    || str_contains($body, 'ESQUINA')
                    || str_contains($body, 'Contacto asignado'));
        });
    }

    public function test_webhook_sends_customer_reply_without_the_category_assignment_echo(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => "Hola, ¿en qué te puedo ayudar?\n\nContact Diego (id: 122) assigned to category: ESQUINA. Do not mention the tag name to the customer.",
                    'tool_results' => [],
                ]);
        });

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '1');
        config(['humano_pricing.plan_access_team_ids' => []]);

        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => '34600000098',
            'name' => 'Diego',
            'email' => 'diego.mixed@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        Conversation::create([
            'message_sid' => 'msg_category_mixed_prev',
            'channel' => 'whatsapp',
            'from' => '34600000098',
            'to' => '34600000001',
            'body' => 'ayer',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        Http::fake([
            'localhost:3000/*' => Http::response(['success' => true, 'id' => 'wa_out_1'], 200),
        ]);

        $this->postJson(route('webhook.whatsapp-local'), [
            'from' => '34600000098',
            'to' => '34600000001',
            'body' => 'Hola',
            'id' => 'msg_category_mixed_1',
        ])->assertOk();

        $this->assertDatabaseHas('conversations', [
            'direction' => 'outbound',
            'to' => '34600000098',
            'body' => 'Contacto asignado a la categoría: ESQUINA',
            'status' => 'internal',
        ]);

        Http::assertSent(function ($request): bool
        {
            $body = (string) ($request['body'] ?? '');

            return str_contains($request->url(), '/send-message')
                && $body === 'Hola, ¿en qué te puedo ayudar?'
                && ! str_contains($body, 'ESQUINA');
        });
    }

    private function documentIngestionAcknowledgementCount(): int
    {
        return Conversation::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', 'Recibi tu documento%')
            ->count();
    }
}
