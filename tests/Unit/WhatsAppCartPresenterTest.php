<?php

namespace Tests\Unit;

use App\Helpers\WhatsAppCartPresenter;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\ShoppingCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppCartPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cart_omits_prices_when_the_store_hides_them(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Principal',
            'code' => 'MAIN',
            'status' => true,
            'is_main' => true,
            'data' => ['show_prices' => false],
        ]);
        $currencyId = Currency::query()->firstOrCreate(
            ['code' => 'ARS'],
            ['name' => 'Peso argentino', 'symbol' => '$', 'status' => true],
        )->id;
        $category = Category::withoutGlobalScopes()->create([
            'name' => 'Repuestos',
            'module_id' => null,
            'team_id' => $team->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);
        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'store_id' => $store->id,
            'name' => 'Bujía de iridio',
            'code' => 'ENC-030',
            'description' => 'Spark',
            'price' => 77.77,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $carts = app(ShoppingCartService::class);
        $carts->addProduct($carts->forWhatsApp((int) $team->id, '5491100000001'), $product, 2);

        $message = WhatsAppCartPresenter::customerMessage((int) $team->id, '5491100000001');

        $this->assertStringContainsString('Bujía de iridio', $message);
        $this->assertStringContainsString('2', $message);
        $this->assertStringNotContainsString('77.77', $message);
        $this->assertStringNotContainsString('TOTAL', $message);
    }
}
