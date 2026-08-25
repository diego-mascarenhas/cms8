<?php

namespace Tests\Feature;

use App\Helpers\WhatsAppCartSessionKey;
use App\Helpers\WhatsAppLastOfferedProduct;
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

    private function carts(): ShoppingCartService
    {
        return app(ShoppingCartService::class);
    }

    private function firstCartLine(int $teamId, string $phone): ?object
    {
        return $this->carts()->whatsAppLines($teamId, $phone)->first();
    }

    private function seedWhatsAppCart(Team $team, string $phone, Product $product, int $quantity = 1): void
    {
        $cart = $this->carts()->forWhatsApp((int) $team->id, $phone);
        $this->carts()->addProduct($cart, $product, $quantity);
    }

    private function silentOrchestrator(Team $team): WhatsAppMessageOrchestrator
    {
        return new class($team) extends WhatsAppMessageOrchestrator
        {
            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                return ['success' => true];
            }
        };
    }

    private function createClampProduct(Team $team): Product
    {
        $currencyId = Currency::query()->firstOrCreate(
            ['code' => 'ARS'],
            ['name' => 'Peso argentino', 'symbol' => '$', 'status' => true],
        )->id;

        $category = Category::withoutGlobalScopes()->create([
            'name' => 'Abrazaderas',
            'module_id' => null,
            'team_id' => $team->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        return Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'ABRAZADERA 16 X 27',
            'code' => '21861',
            'description' => 'Marca Perfecto',
            'price' => 989.93,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
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

        $this->seedWhatsAppCart($team, $phone, $product, 1);

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

        $item = $this->firstCartLine((int) $team->id, $phone);
        $this->assertNotNull($item);
        $this->assertSame(4, (int) $item->quantity);
    }

    public function test_spanish_nine_and_eleven_digit_phones_share_same_cart_storage(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createMidiDressProduct($team);

        $intl = '34600000000';
        $national = '600000000';

        $this->seedWhatsAppCart($team, $intl, $product, 1);

        $this->assertSame(1, $this->carts()->whatsAppLines((int) $team->id, $national)->count());
        $this->assertSame(
            WhatsAppCartSessionKey::fromPhone($intl),
            WhatsAppCartSessionKey::fromPhone($national),
        );
    }

    public function test_agregame_two_adds_last_offered_product(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createMidiDressProduct($team);
        $phone = '5491199900023';

        WhatsAppLastOfferedProduct::remember($phone, (int) $team->id, (int) $product->id);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                return ['success' => true];
            }
        };

        $result = $service->processCartCommands($phone, 'Agregame 2');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        $item = $this->firstCartLine((int) $team->id, $phone);
        $this->assertNotNull($item);
        $this->assertSame((int) $product->id, (int) $item->id);
        $this->assertSame(2, (int) $item->quantity);
    }

    public function test_comprar_by_code_and_accented_name_adds_product(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createClampProduct($team);
        $phone = '5491199900024';

        $service = $this->silentOrchestrator($team);

        $byCode = $service->processCartCommands($phone, 'Comprar producto 21861');
        $this->assertTrue($byCode['success']);
        $this->assertSame(1, (int) $this->firstCartLine((int) $team->id, $phone)->quantity);

        $this->carts()->clear($this->carts()->forWhatsApp((int) $team->id, $phone));
        $byName = $service->processCartCommands($phone, 'comprar abrazadera 16 x 27');
        $this->assertTrue($byName['success']);
        $this->assertSame((int) $product->id, (int) $this->firstCartLine((int) $team->id, $phone)->id);
        $this->assertSame(1, (int) $this->firstCartLine((int) $team->id, $phone)->quantity);
    }

    public function test_agregame_abbreviation_and_spanish_quantity_add_clamp(): void
    {
        $team = $this->createTeamWithOwner();
        $sixteen = $this->createClampProduct($team);
        $eight = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'ABRAZADERA 8 X 16',
            'code' => '21870',
            'description' => 'Marca Perfecto',
            'price' => 989.43,
            'currency_id' => $sixteen->currency_id,
            'category_id' => $sixteen->category_id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
        $phone = '5491199900027';
        $service = $this->silentOrchestrator($team);

        $short = $service->processCartCommands($phone, 'Agregame 2 abraz de 8');
        $this->assertTrue($short['success'] ?? false);
        $this->assertSame((int) $eight->id, (int) $this->firstCartLine((int) $team->id, $phone)->id);
        $this->assertSame(2, (int) $this->firstCartLine((int) $team->id, $phone)->quantity);

        $this->carts()->clear($this->carts()->forWhatsApp((int) $team->id, $phone));
        $words = $service->processCartCommands($phone, 'agregame dos ABRAZADERA 8 X 16');
        $this->assertTrue($words['success'] ?? false);
        $this->assertSame((int) $eight->id, (int) $this->firstCartLine((int) $team->id, $phone)->id);
        $this->assertSame(2, (int) $this->firstCartLine((int) $team->id, $phone)->quantity);

        $this->carts()->clear($this->carts()->forWhatsApp((int) $team->id, $phone));
        $priced = $service->processCartCommands($phone, 'Comprar  2 ABRAZADERA 8 X 16 a $989.43 c/u');
        $this->assertTrue($priced['success'] ?? false);
        $this->assertSame((int) $eight->id, (int) $this->firstCartLine((int) $team->id, $phone)->id);
        $this->assertSame(2, (int) $this->firstCartLine((int) $team->id, $phone)->quantity);
    }

    public function test_add_to_cart_succeeds_when_category_was_soft_deleted(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createClampProduct($team);
        $product->category->delete();
        $product->unsetRelation('category');

        $phone = '5491199900028';

        $result = $this->silentOrchestrator($team)->processCartCommands($phone, 'comprar '.$product->code);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame((int) $product->id, (int) $this->firstCartLine((int) $team->id, $phone)->id);
    }

    public function test_comprar_ranks_closest_size_when_measure_is_off(): void
    {
        $team = $this->createTeamWithOwner();
        $closer = $this->createClampProduct($team);
        $farther = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'ABRAZADERA 12 X 22',
            'code' => '25259',
            'description' => 'Marca Perfecto',
            'price' => 500,
            'currency_id' => $closer->currency_id,
            'category_id' => $closer->category_id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
        $phone = '5491199900026';

        $result = $this->silentOrchestrator($team)->processCartCommands($phone, 'comprar abrazadera 16 x 28');

        $this->assertTrue($result['success']);
        $this->assertSame((int) $closer->id, (int) $this->firstCartLine((int) $team->id, $phone)->id);
        $this->assertNotSame((int) $farther->id, (int) $this->firstCartLine((int) $team->id, $phone)->id);
    }

    public function test_comprar_two_of_these_uses_last_offered_product(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createClampProduct($team);
        $phone = '5491199900025';
        WhatsAppLastOfferedProduct::remember($phone, (int) $team->id, (int) $product->id);

        $service = $this->silentOrchestrator($team);
        $result = $service->processCartCommands($phone, 'Comprar 2 de estas unidades');

        $this->assertTrue($result['success']);
        $item = $this->firstCartLine((int) $team->id, $phone);
        $this->assertSame((int) $product->id, (int) $item->id);
        $this->assertSame(2, (int) $item->quantity);
    }

    public function test_quiero_ver_mi_carrito_shows_cart_contents(): void
    {
        $team = $this->createTeamWithOwner();
        $product = $this->createClampProduct($team);
        $phone = '5491199900029';
        $this->seedWhatsAppCart($team, $phone, $product, 4);

        $service = new class($team) extends WhatsAppMessageOrchestrator
        {
            public string $lastMessage = '';

            public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
            {
                $this->lastMessage = (string) $message;

                return ['success' => true];
            }
        };

        $result = $service->processCartCommands($phone, 'Ver carrito');

        $this->assertTrue($result['success'] ?? false);
        $this->assertStringContainsString('ABRAZADERA 16 X 27', $service->lastMessage);
        $this->assertStringContainsString('4', $service->lastMessage);
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
