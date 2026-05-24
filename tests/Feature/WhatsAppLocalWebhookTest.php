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

        $user = User::withoutGlobalScopes()->where('email', 'wa-34600000099@chat.placeholder')->first();
        $this->assertNotNull($user);
        $this->assertSame('María WhatsApp', $user->name);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($contact);
        $this->assertSame('María WhatsApp', $contact->name);
        $this->assertSame(34600000099, (int) $contact->phone);
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
    }

    public function test_webhook_auto_ai_responds_when_team_enabled_even_if_contact_disables_assistant(): void
    {
        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => 'Team auto reply',
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
            'body' => 'Hello team wins',
            'id' => 'msg_team_wins_1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $response->assertJsonMissing(['auto_ai_skipped' => 'contact_assistant_disabled']);
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
            'body' => 'Hello national phone mismatch',
            'id' => 'msg_team_wins_national',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $response->assertJsonMissing(['auto_ai_skipped' => 'contact_assistant_disabled']);
    }
}
