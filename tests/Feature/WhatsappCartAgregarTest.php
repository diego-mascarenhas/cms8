<?php

namespace Tests\Feature;

use App\Helpers\WhatsAppCartSessionKey;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsAppMessageOrchestrator;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsappCartAgregarTest extends TestCase
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

    public function test_agregar_tres_vestidos_iguales_al_carrito_adds_units_with_plural_name(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createMidiDressProduct($team);
        $phone = '5491199900021';

        Cart::session($phone)->clear();
        Cart::session($phone)->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->currentSellingPrice(),
            'quantity' => 1,
            'attributes' => [
                'team_id' => $team->id,
                'currency_id' => $product->currency_id,
                'description' => $product->description,
                'category_name' => $product->category->name ?? '',
            ],
        ]);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                return ['success' => true];
            }
        };

        $result = $service->processCartCommands($phone, 'Agregar 3 vestidos iguales al carrito');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        Cart::session($phone);
        $item = Cart::getContent()->first();
        $this->assertNotNull($item);
        $this->assertSame(4, (int) $item->quantity);
    }

    public function test_spanish_nine_and_eleven_digit_phones_share_same_cart_storage(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createMidiDressProduct($team);

        $intl = '34600000000';
        $national = '600000000';

        Cart::session(WhatsAppCartSessionKey::fromPhone($intl))->clear();
        Cart::session(WhatsAppCartSessionKey::fromPhone($intl))->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->currentSellingPrice(),
            'quantity' => 1,
            'attributes' => [
                'team_id' => $team->id,
                'currency_id' => $product->currency_id,
                'description' => $product->description,
                'category_name' => $product->category->name ?? '',
            ],
        ]);

        Cart::session(WhatsAppCartSessionKey::fromPhone($national));
        $this->assertSame(1, Cart::getContent()->count());
    }

    public function test_agregar_message_does_not_trigger_product_catalog(): void
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

        $result = $service->processProductCommands('+5491199900022', 'Agregar 3 vestidos iguales al carrito');

        $this->assertNull($result);
        $this->assertFalse($service->sendCalled);
    }
}
