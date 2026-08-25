<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\ShoppingCartService;
use App\Services\WhatsAppMessageOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsappCartRemoveItemTest extends TestCase
{
    use RefreshDatabase;

    private function createTeamWithOwner(): Team
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $team;
    }

    private function carts(): ShoppingCartService
    {
        return app(ShoppingCartService::class);
    }

    private function seedWhatsAppCart(Team $team, string $phone, Product $product, int $quantity = 1): void
    {
        $cart = $this->carts()->forWhatsApp((int) $team->id, $phone);
        $this->carts()->addProduct($cart, $product, $quantity);
    }

    private function createMidiDressProduct(Team $team): Product
    {
        $currencyId = Currency::query()->firstOrCreate(
            ['code' => 'ARS'],
            ['name' => 'Peso argentino', 'symbol' => '$', 'status' => true],
        )->id;

        $category = Category::withoutGlobalScopes()->create([
            'name' => 'Ropa Test',
            'module_id' => null,
            'team_id' => $team->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        return Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Vestido midi',
            'code' => 'VEST-MIDI',
            'description' => 'Test',
            'price' => 99900,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
    }

    public function test_quitar_one_reduces_quantity_when_cart_has_two(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createMidiDressProduct($team);
        $phone = '+5491199900011';

        $this->seedWhatsAppCart($team, $phone, $product, 2);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public bool $sent = false;

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->sent = true;

                return ['success' => true];
            }
        };

        $result = $service->processCartCommands($phone, 'Quitar 1 vestido midi del carrito');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertTrue($service->sent);

        $item = $this->carts()->whatsAppLines((int) $team->id, $phone)->first();
        $this->assertNotNull($item);
        $this->assertSame(1, (int) $item->quantity);
    }

    public function test_quitar_todo_removes_line(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createMidiDressProduct($team);
        $phone = '+5491199900012';

        $this->seedWhatsAppCart($team, $phone, $product, 2);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                return ['success' => true];
            }
        };

        $result = $service->processCartCommands($phone, 'eliminar todo vestido midi');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        $this->assertTrue($this->carts()->whatsAppLines((int) $team->id, $phone)->isEmpty());
    }

    public function test_process_product_commands_does_not_send_catalog_for_quitar_message(): void
    {
        $team = $this->createTeamWithOwner();

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public bool $sendCalled = false;

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->sendCalled = true;

                return ['success' => true];
            }
        };

        $result = $service->processProductCommands('+5491199900013', 'Quitar 1 vestido midi del carrito');

        $this->assertNull($result);
        $this->assertFalse($service->sendCalled);
    }
}
