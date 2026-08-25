<?php

namespace Tests\Feature\Api;

use App\Enums\ShoppingCartChannel;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\ShoppingCartService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(CurrencySeeder::class);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithShopModules(bool $enableModules = true): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['products', 'stores', 'orders'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst($key),
                    'icon' => 'shopping-cart',
                    'description' => $key,
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }

        if ($enableModules)
        {
            $this->enableTeamModules($team, ['products', 'stores', 'orders']);
        }

        $token = $user->createToken('idoneo-shop-test')->plainTextToken;

        return [$user->fresh(), $team->fresh(), $token];
    }

    public function test_module_missing_returns_forbidden(): void
    {
        [, , $token] = $this->adminWithShopModules(false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/lookups')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_lookups_and_dashboard(): void
    {
        [, $team, $token] = $this->adminWithShopModules();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/lookups')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'currencies',
                    'stores',
                    'catalog_statuses',
                    'stock_statuses',
                    'payment_methods',
                    'fulfillment_types',
                    'payment_statuses',
                    'delivery_statuses',
                    'cart_channels',
                ],
            ]);

        $this->assertDatabaseHas('stores', [
            'team_id' => $team->id,
            'code' => 'MAIN',
            'is_main' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.products_count', 0)
            ->assertJsonPath('data.published_products_count', 0)
            ->assertJsonPath('data.stores_count', 1)
            ->assertJsonPath('data.orders_count', 0)
            ->assertJsonPath('data.orders_this_month', 0)
            ->assertJsonPath('data.pending_orders', 0)
            ->assertJsonPath('data.pending_orders_total', 0)
            ->assertJsonPath('data.open_carts_count', 0)
            ->assertJsonPath('data.open_carts_items', 0)
            ->assertJsonPath('data.open_carts_total', 0);
    }

    public function test_can_crud_product_and_create_category(): void
    {
        [, $team, $token] = $this->adminWithShopModules();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/lookups')
            ->assertOk();

        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/categories', ['name' => 'Ropa'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ropa')
            ->json('data');

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $mainStoreId = Store::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('code', 'MAIN')
            ->value('id');
        $this->assertNotNull($mainStoreId);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', [
                'name' => 'Camiseta shop',
                'code' => 'CAM-SHOP-001',
                'description' => 'Algodón',
                'short_description' => 'Resumen',
                'price' => 12.5,
                'sale_price' => 10,
                'currency_id' => $currencyId,
                'category_id' => $category['id'],
                'store_id' => $mainStoreId,
                'catalog_status' => 'publish',
                'stock_status' => 'instock',
                'manage_stock' => true,
                'stock_quantity' => 8,
                'size_options' => ['S', 'M'],
                'color_options' => ['Negro'],
                'whatsapp_enabled' => true,
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Camiseta shop')
            ->assertJsonPath('data.code', 'CAM-SHOP-001')
            ->assertJsonPath('data.store_id', $mainStoreId)
            ->assertJsonPath('data.manage_stock', true)
            ->assertJsonPath('data.stock_quantity', 8);

        $id = (int) $create->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products/'.$id)
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Ropa');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/shop/products/'.$id, [
                'name' => 'Camiseta shop updated',
                'code' => 'CAM-SHOP-001',
                'description' => 'Algodón',
                'price' => 15,
                'currency_id' => $currencyId,
                'category_id' => $category['id'],
                'store_id' => $mainStoreId,
                'catalog_status' => 'draft',
                'stock_status' => 'outofstock',
                'manage_stock' => false,
                'whatsapp_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Camiseta shop updated')
            ->assertJsonPath('data.catalog_status', 'draft')
            ->assertJsonPath('data.manage_stock', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products?search=Camiseta')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/shop/products/'.$id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_store_main_flag_and_cannot_delete_main(): void
    {
        [, $team, $token] = $this->adminWithShopModules();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/stores')
            ->assertOk();

        $mainId = (int) Store::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('is_main', true)
            ->value('id');

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/stores', [
                'name' => 'Sucursal Norte',
                'code' => 'NORTE',
                'address' => 'Av. Norte 100',
                'status' => true,
                'is_main' => true,
                'checkout_payment_methods' => ['cash', 'mercadopago'],
                'checkout_fulfillment_types' => ['pickup'],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Sucursal Norte')
            ->assertJsonPath('data.is_main', true)
            ->assertJsonPath('data.checkout_payment_methods.0', 'cash');

        $branchId = (int) $create->json('data.id');

        $this->assertDatabaseHas('stores', [
            'id' => $mainId,
            'is_main' => false,
        ]);
        $this->assertDatabaseHas('stores', [
            'id' => $branchId,
            'is_main' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/shop/stores/'.$branchId)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/shop/stores/'.$mainId, [
                'name' => 'Principal',
                'code' => 'MAIN',
                'status' => true,
                'is_main' => false,
                'checkout_payment_methods' => ['cash'],
                'checkout_fulfillment_types' => ['delivery'],
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/shop/stores/'.$mainId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('stores', ['id' => $mainId]);
    }

    public function test_can_update_order_status(): void
    {
        [, $team, $token] = $this->adminWithShopModules();

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Local',
            'code' => 'L1',
            'status' => true,
            'is_main' => false,
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'store_id' => $store->id,
            'order_number' => 'WA-SHOPTEST01',
            'contact_id' => null,
            'total_amount' => 20,
            'currency_id' => null,
            'payment_status' => 'pending',
            'delivery_status' => 'processing',
            'notes' => null,
            'metadata' => [
                'items' => [
                    [
                        'product_id' => 1,
                        'name' => 'Camiseta',
                        'quantity' => 2,
                        'unit_price' => 10,
                        'line_total' => 20,
                    ],
                ],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/orders')
            ->assertOk()
            ->assertJsonPath('data.0.order_number', 'WA-SHOPTEST01')
            ->assertJsonPath('data.0.items.0.name', 'Camiseta');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/shop/orders/'.$order->id, [
                'payment_status' => 'paid',
                'delivery_status' => 'dispatched',
                'notes' => 'Listo para envío',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.delivery_status', 'dispatched')
            ->assertJsonPath('data.notes', 'Listo para envío');
    }

    public function test_can_list_show_and_delete_open_carts(): void
    {
        [, $team, $token] = $this->adminWithShopModules();
        $otherTeam = Team::factory()->create();

        $carts = app(ShoppingCartService::class);
        $ownProduct = $this->createPricedProduct($team, 25);
        $otherProduct = $this->createPricedProduct($otherTeam, 99);
        $ownCart = $carts->forWhatsApp((int) $team->id, '5491199908800');
        $carts->addProduct($ownCart, $ownProduct, 2);
        $carts->addProduct($carts->forWhatsApp((int) $otherTeam->id, '5491199908801'), $otherProduct, 1);
        $carts->addProduct($carts->forPublicShop((int) $team->id, 'shop-session-1'), $ownProduct, 1);

        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/carts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 2);

        $ids = collect($list->json('data'))->pluck('id')->all();
        $channels = collect($list->json('data'))->pluck('channel')->all();
        $this->assertContains($ownCart->id, $ids);
        $this->assertContains(ShoppingCartChannel::WhatsApp->value, $channels);
        $this->assertContains(ShoppingCartChannel::PublicShop->value, $channels);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/carts?channel=whatsapp')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.channel', ShoppingCartChannel::WhatsApp->value)
            ->assertJsonPath('data.0.phone', '5491199908800')
            ->assertJsonPath('data.0.quantity', 2);

        $show = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/carts/'.$ownCart->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownCart->id)
            ->assertJsonPath('data.items.0.name', $ownProduct->name)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertEqualsWithDelta(50.0, (float) $show->json('data.total'), 0.01);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/dashboard')
            ->assertOk()
            ->assertJsonPath('data.open_carts_count', 2);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/shop/carts/'.$ownCart->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/carts/'.$ownCart->id)
            ->assertNotFound();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/carts')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_carts_require_orders_module(): void
    {
        [, , $token] = $this->adminWithShopModules(false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/carts')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function createPricedProduct(Team $team, float $price): Product
    {
        $currencyId = Currency::query()->firstOrCreate(
            ['code' => 'ARS'],
            ['name' => 'Peso argentino', 'symbol' => '$', 'status' => true],
        )->id;

        $category = Category::withoutGlobalScopes()->create([
            'name' => 'Shop API',
            'module_id' => null,
            'team_id' => $team->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        return Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Producto carrito '.$price,
            'code' => 'CART-'.$team->id.'-'.$price,
            'description' => 'Test',
            'price' => $price,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
    }
}
