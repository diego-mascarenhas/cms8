<?php

namespace Tests\Feature;

use App\Enums\ShoppingCartChannel;
use App\Helpers\WhatsAppCartSessionKey;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\Team;
use App\Services\ShoppingCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingCartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_product_increments_same_line_and_keeps_total(): void
    {
        $team = Team::factory()->create();
        $product = $this->createPricedProduct($team, 989.43);
        $carts = app(ShoppingCartService::class);
        $cart = $carts->forWhatsApp((int) $team->id, '5491199900200');

        $carts->addProduct($cart, $product, 1);
        $line = $carts->addProduct($cart, $product, 1);

        $this->assertSame(2, (int) $line->quantity);
        $this->assertEqualsWithDelta(1978.86, $carts->total($cart), 0.01);
        $this->assertSame(1, ShoppingCart::withoutGlobalScope('team')->where('team_id', $team->id)->count());
    }

    public function test_whatsapp_carts_are_isolated_by_team(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $phone = '5491199900201';
        $productA = $this->createPricedProduct($teamA, 10);
        $productB = $this->createPricedProduct($teamB, 20);
        $carts = app(ShoppingCartService::class);

        $carts->addProduct($carts->forWhatsApp((int) $teamA->id, $phone), $productA, 1);
        $carts->addProduct($carts->forWhatsApp((int) $teamB->id, $phone), $productB, 3);

        $this->assertSame(1, $carts->whatsAppLines((int) $teamA->id, $phone)->count());
        $this->assertSame(1, $carts->whatsAppLines((int) $teamB->id, $phone)->count());
        $this->assertEqualsWithDelta(10.0, $carts->total($carts->forWhatsApp((int) $teamA->id, $phone)), 0.01);
        $this->assertEqualsWithDelta(60.0, $carts->total($carts->forWhatsApp((int) $teamB->id, $phone)), 0.01);
        $this->assertSame(ShoppingCartChannel::WhatsApp, $carts->findWhatsApp((int) $teamA->id, $phone)?->channel);
    }

    public function test_spanish_nine_and_eleven_digit_phones_share_the_same_whatsapp_cart(): void
    {
        $team = Team::factory()->create();
        $product = $this->createPricedProduct($team, 15);
        $carts = app(ShoppingCartService::class);

        $carts->addProduct($carts->forWhatsApp((int) $team->id, '34600000001'), $product, 1);

        $this->assertSame(
            WhatsAppCartSessionKey::fromPhone('34600000001'),
            WhatsAppCartSessionKey::fromPhone('600000001'),
        );
        $this->assertSame(1, $carts->whatsAppLines((int) $team->id, '600000001')->count());
    }

    private function createPricedProduct(Team $team, float $price): Product
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
            'name' => 'ABRAZADERA 8 X 16',
            'code' => '21861-'.$team->id,
            'description' => 'Test',
            'price' => $price,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
    }
}
