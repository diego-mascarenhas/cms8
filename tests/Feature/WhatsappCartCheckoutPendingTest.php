<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\WhatsAppMessageOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

class WhatsappCartCheckoutPendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_affirmative_without_checkout_pending_returns_null_and_does_not_send_whatsapp(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public bool $sendCalled = false;

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->sendCalled = true;

                return ['success' => true];
            }
        };

        $result = $service->processCartCommands('+573001112223', 'SÍ!');

        $this->assertNull($result);
        $this->assertFalse($service->sendCalled);
    }

    public function test_cerrar_pedido_triggers_checkout_with_empty_cart_message(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public string $lastMessage = '';

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->lastMessage = (string) $message;

                return ['success' => true];
            }
        };

        $result = $service->processCartCommands('+573001112223', 'cerrar pedido');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('vacío', $service->lastMessage);
    }

    public function test_affirmative_with_checkout_pending_and_empty_cart_sends_empty_cart_message(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $phone = '+573001112223';
        $digits = '573001112223';
        Cache::put('whatsapp_checkout_pending:'.$digits, true, now()->addMinutes(45));

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public string $lastMessage = '';

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->lastMessage = (string) $message;

                return ['success' => true];
            }
        };

        $result = $service->processCartCommands($phone, 'si');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('vacío', $service->lastMessage);
    }

    public function test_resolve_cart_team_id_prefers_webhook_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = new WhatsAppMessageOrchestrator($team);

        $method = (new ReflectionClass($service))->getMethod('resolveCartTeamId');
        $method->setAccessible(true);

        $this->assertSame((int) $team->id, $method->invoke($service, '+1234567890'));
    }

    public function test_process_product_commands_does_not_hijack_billing_service_question(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public bool $sendCalled = false;

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->sendCalled = true;

                return ['success' => true];
            }
        };

        $result = $service->processProductCommands('+573001112223', 'Una vez que pague se restablece el servicio?');

        $this->assertNull($result);
        $this->assertFalse($service->sendCalled);
    }

    public function test_process_product_commands_does_not_hijack_final_price_or_vat_question(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public bool $sendCalled = false;

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->sendCalled = true;

                return ['success' => true];
            }
        };

        $result = $service->processProductCommands(
            '+573001112224',
            'Es precio final o falta el iva?',
        );

        $this->assertNull($result);
        $this->assertFalse($service->sendCalled);
    }
}
