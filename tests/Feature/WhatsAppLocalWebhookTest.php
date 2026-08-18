<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Team;
use App\Models\User;
use App\Services\ChatAssistantReplyService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    private function documentIngestionAcknowledgementCount(): int
    {
        return Conversation::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', 'Recibi tu documento%')
            ->count();
    }
}
