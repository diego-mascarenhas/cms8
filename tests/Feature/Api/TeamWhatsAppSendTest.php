<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamWhatsAppSendTest extends TestCase
{
    use RefreshDatabase;

    private function teamApiToken(Team $team): string
    {
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        return $tokenValue;
    }

    public function test_whatsapp_send_returns_401_without_token(): void
    {
        $this->postJson('/api/team/whatsapp/send', [
            'to' => '34600111222',
            'message' => 'Hola',
        ])->assertStatus(401)
            ->assertJson(['message' => 'Token not provided']);
    }

    public function test_whatsapp_send_returns_401_with_invalid_token(): void
    {
        $this->postJson('/api/team/whatsapp/send', [
            'to' => '34600111222',
            'message' => 'Hola',
        ], [
            'Authorization' => 'Bearer invalid-token',
        ])->assertStatus(401)
            ->assertJson(['message' => 'Invalid token']);
    }

    public function test_whatsapp_send_returns_validation_error_when_fields_missing(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $token = $this->teamApiToken($team);

        $this->postJson('/api/team/whatsapp/send', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(422);
    }

    public function test_whatsapp_send_returns_422_for_invalid_phone(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $token = $this->teamApiToken($team);

        $this->postJson('/api/team/whatsapp/send', [
            'to' => '123',
            'message' => 'Hola',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_whatsapp_send_sends_message_with_local_driver(): void
    {
        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://baileys.test']);
        config(['whatsapp.local.webhook_secret' => '']);

        Http::fake([
            'http://baileys.test/status*' => Http::response(['status' => 'connected'], 200),
            'http://baileys.test/send-message' => Http::response(['id' => 'msg-api-1', 'success' => true], 200),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34999000111');
        $team->setSetting('whatsapp_service_url', 'http://baileys.test');
        $token = $this->teamApiToken($team);

        $recipient = '34600111222';

        Conversation::create([
            'message_sid' => 'wa_team_api_in_1',
            'channel' => 'whatsapp',
            'from' => $recipient,
            'to' => '34999000111',
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $response = $this->postJson('/api/team/whatsapp/send', [
            'to' => $recipient,
            'message' => 'Hola desde API',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'WhatsApp message sent',
                'to' => $recipient,
            ]);

        Http::assertSent(function ($request) use ($team, $recipient): bool
        {
            if (! str_contains($request->url(), '/send-message'))
            {
                return false;
            }
            $data = $request->data();

            return (int) ($data['team_id'] ?? 0) === $team->id
                && ($data['to'] ?? '') === $recipient
                && ($data['body'] ?? '') === 'Hola desde API';
        });

        $this->assertDatabaseHas('conversations', [
            'channel' => 'whatsapp',
            'to' => $recipient,
            'body' => 'Hola desde API',
            'direction' => 'outbound',
        ]);
    }

    public function test_whatsapp_send_returns_503_when_local_whatsapp_not_connected(): void
    {
        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://baileys.test']);
        config(['whatsapp.local.webhook_secret' => '']);

        Http::fake([
            'http://baileys.test/status*' => Http::response(['status' => 'waiting_qr'], 200),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_service_url', 'http://baileys.test');
        $token = $this->teamApiToken($team);

        $this->postJson('/api/team/whatsapp/send', [
            'to' => '34600111222',
            'message' => 'Hola',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(503)
            ->assertJson(['success' => false]);
    }
}
