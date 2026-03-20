<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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
}
