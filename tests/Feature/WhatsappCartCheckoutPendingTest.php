<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
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

    public function test_detect_whatsapp_intent_routes_agregar_cita_to_assistant(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = new WhatsAppMessageOrchestrator($team);

        $method = (new ReflectionClass($service))->getMethod('detectWhatsAppIntent');
        $method->setAccessible(true);

        $intent = $method->invoke($service, 'Agregar cita para hoy a las 15 hs');

        $this->assertSame('assistant', $intent);
        $this->assertSame('cart', $method->invoke($service, 'Agregame 2'));
        $this->assertSame('cart', $method->invoke($service, 'Quiero ver mi carrito'));
        $this->assertSame('cart', $method->invoke($service, 'Ver carrito'));
        $this->assertSame('cart', $method->invoke($service, 'Qué hay en el carrito'));
    }

    public function test_catalog_command_lists_categories_not_every_product(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $currencyId = Currency::query()->firstOrCreate(
            ['code' => 'ARS'],
            ['name' => 'Peso argentino', 'symbol' => '$', 'status' => true],
        )->id;
        $frenos = Category::withoutGlobalScopes()->create([
            'name' => 'Frenos',
            'module_id' => null,
            'team_id' => $team->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        foreach (range(1, 12) as $index)
        {
            Product::withoutGlobalScope('team')->create([
                'team_id' => $team->id,
                'name' => 'Pastilla secreto '.$index,
                'code' => 'CAT-'.$index,
                'description' => 'No listar',
                'price' => 10 + $index,
                'currency_id' => $currencyId,
                'category_id' => $frenos->id,
                'status' => true,
                'whatsapp_enabled' => true,
            ]);
        }

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public ?string $sent = null;

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->sent = (string) $message;

                return ['success' => true];
            }
        };

        $result = $service->processProductCommands('+5491199900099', 'Catálogo');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Frenos', (string) $service->sent);
        $this->assertStringContainsString('12', (string) $service->sent);
        $this->assertStringContainsString('qué buscás', mb_strtolower((string) $service->sent));
        $this->assertStringNotContainsString('Pastilla secreto 1', (string) $service->sent);
        $this->assertNull($service->processProductCommands('+5491199900099', 'Quiero un pedido urgente'));
    }
}
