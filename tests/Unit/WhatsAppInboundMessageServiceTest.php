<?php

namespace Tests\Unit;

use App\Contracts\WhatsAppGateway;
use App\Models\Conversation;
use App\Models\Team;
use App\Services\WhatsApp\WhatsAppInboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class WhatsAppInboundMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_duplicate_message_sid_idempotently(): void
    {
        config()->set('whatsapp.driver', 'local');

        $team = Team::factory()->create();
        $team->setSetting('whatsapp_from', '34600000001');

        $service = new WhatsAppInboundMessageService($team);
        $gateway = new class implements WhatsAppGateway
        {
            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                return ['ok' => true];
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
        };

        $request = Request::create('/', 'POST', [
            'MessageSid' => 'msg_unit_duplicate_123',
            'From' => 'whatsapp:34600000000',
            'To' => 'whatsapp:34600000001',
            'Body' => 'Hello from unit test',
            'NumMedia' => 0,
        ]);

        $first = $service->processIncomingMessage($request, $gateway);
        $second = $service->processIncomingMessage($request, $gateway);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame(
            1,
            Conversation::query()->where('message_sid', 'msg_unit_duplicate_123')->count(),
        );
        $this->assertSame(true, (bool) ($second->getData(true)['duplicate'] ?? false));
    }
}
