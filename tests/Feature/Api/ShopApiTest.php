<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\Module;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
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
            ->assertJsonPath('data.stores_count', 1)
            ->assertJsonPath('data.orders_count', 0);
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
}
