<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Services\WhatsAppMessageOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class WhatsAppPingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_ping_replies_pong_even_when_the_assistant_is_off(): void
    {
        config()->set('whatsapp.driver', 'local');

        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        $service = $this->capturingOrchestrator($team);

        $response = $service->processIncomingMessage($this->inboundRequest('/ping'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue((bool) ($response->getData(true)['ping'] ?? false));
        $this->assertSame([
            ['to' => '34600000000', 'message' => 'pong'],
        ], $service->sent);
    }

    public function test_whatsapp_ping_is_case_insensitive(): void
    {
        config()->set('whatsapp.driver', 'local');

        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        $service = $this->capturingOrchestrator($team);

        $service->processIncomingMessage($this->inboundRequest('/PING', 'msg_ping_upper'));

        $this->assertSame('pong', $service->sent[0]['message'] ?? null);
    }

    public function test_plain_ping_without_slash_is_not_a_command(): void
    {
        config()->set('whatsapp.driver', 'local');

        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');
        $team->setSetting('assistant_auto_respond', '0');

        $service = $this->capturingOrchestrator($team);

        $response = $service->processIncomingMessage($this->inboundRequest('ping', 'msg_plain_ping'));

        $this->assertFalse((bool) ($response->getData(true)['ping'] ?? false));
        $this->assertNotContains('pong', array_column($service->sent, 'message'));
    }

    private function capturingOrchestrator(Team $team): WhatsAppMessageOrchestrator
    {
        return new class($team) extends WhatsAppMessageOrchestrator
        {
            /** @var list<array{to: string, message: string}> */
            public array $sent = [];

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->sent[] = [
                    'to' => (string) $to,
                    'message' => (string) $message,
                ];

                return ['success' => true];
            }
        };
    }

    private function inboundRequest(string $body, string $sid = 'msg_ping_1'): Request
    {
        return Request::create('/', 'POST', [
            'MessageSid' => $sid,
            'From' => 'whatsapp:34600000000',
            'To' => 'whatsapp:34600000001',
            'Body' => $body,
            'NumMedia' => 0,
        ]);
    }
}
