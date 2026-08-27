<?php

namespace Tests\Feature\Api;

use App\Contracts\WhatsAppGateway;
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
                    'brands',
                    'currencies',
                    'stores',
                    'catalog_statuses',
                    'stock_statuses',
                    'payment_methods',
                    'payment_types',
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
            ->assertJsonPath('data.available_in_all_stores', true)
            ->assertJsonPath('data.manage_stock', true)
            ->assertJsonPath('data.stock_quantity', 8)
            ->assertJsonCount(2, 'data.options')
            ->assertJsonCount(2, 'data.variants');

        $this->assertEqualsCanonicalizing(['Talle', 'Color'], collect($create->json('data.options'))->pluck('name')->all());

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

    public function test_product_availability_can_target_all_or_specific_stores(): void
    {
        [, $team, $token] = $this->adminWithShopModules();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/lookups')
            ->assertOk();

        $mainStoreId = (int) Store::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('code', 'MAIN')
            ->value('id');

        $norte = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/stores', [
                'name' => 'Norte',
                'code' => 'NORTE',
                'status' => true,
                'is_main' => false,
                'checkout_payment_methods' => ['cash'],
                'checkout_fulfillment_types' => ['pickup'],
            ])
            ->assertCreated()
            ->json('data');

        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/categories', ['name' => 'Comida'])
            ->assertCreated()
            ->json('data');

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $shared = [
            'price' => 9800,
            'currency_id' => $currencyId,
            'category_id' => $category['id'],
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'manage_stock' => false,
            'whatsapp_enabled' => true,
        ];

        $empanada = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', array_merge($shared, [
                'name' => 'Docena de empanadas',
                'code' => 'EMP-ALL',
                'available_in_all_stores' => true,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.available_in_all_stores', true)
            ->json('data');

        $pad = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', array_merge($shared, [
                'name' => 'Pastilla Norte',
                'code' => 'PAD-NORTE',
                'available_in_all_stores' => false,
                'store_ids' => [$norte['id']],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.available_in_all_stores', false)
            ->assertJsonPath('data.store_ids.0', $norte['id'])
            ->json('data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products?store_id='.$norte['id'])
            ->assertOk()
            ->assertJsonPath('pagination.total', 2);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products?store_id='.$mainStoreId)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $empanada['id']);

        $this->assertDatabaseHas('product_store', [
            'product_id' => $pad['id'],
            'store_id' => $norte['id'],
            'team_id' => $team->id,
        ]);
    }

    public function test_product_import_schema_includes_brand_and_variant_examples(): void
    {
        [, , $token] = $this->adminWithShopModules();

        $schema = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products/import')
            ->assertOk()
            ->assertJsonPath('data.required_columns.0', 'code')
            ->assertJsonFragment(['brand'])
            ->assertJsonFragment(['flavor_options'])
            ->assertJsonFragment(['assortment_size']);

        $sample = (string) $schema->json('data.sample_csv');

        $this->assertStringContainsString('PAS-010', $sample);
        $this->assertStringContainsString('Bosch', $sample);
        $this->assertStringContainsString('S|M|L|XL', $sample);
        $this->assertStringContainsString('Carne|Pollo|JyQ|Cebolla', $sample);
        $this->assertStringContainsString('todas', $sample);
    }

    public function test_can_create_brand_and_filter_products(): void
    {
        [, , $token] = $this->adminWithShopModules();

        $brand = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/brands', ['name' => 'Bosch'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Bosch')
            ->json('data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/brands', ['name' => 'bosch'])
            ->assertOk()
            ->assertJsonPath('data.id', $brand['id']);

        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/categories', ['name' => 'Autopartes'])
            ->assertCreated()
            ->json('data');

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', [
                'name' => 'Pastilla de freno',
                'code' => 'BOSCH-PAD-001',
                'barcode' => '7791234567890',
                'oem' => '7H0 698 151 D',
                'price' => 45,
                'currency_id' => $currencyId,
                'category_id' => $category['id'],
                'brand_id' => $brand['id'],
                'catalog_status' => 'publish',
                'stock_status' => 'instock',
                'manage_stock' => false,
                'whatsapp_enabled' => true,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.brand.name', 'Bosch')
            ->assertJsonPath('data.barcode', '7791234567890')
            ->assertJsonPath('data.oem', '7H0 698 151 D')
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.is_default', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products?brand_id='.$brand['id'])
            ->assertOk()
            ->assertJsonPath('pagination.total', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products?brand_id=999999')
            ->assertOk()
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_product_code_is_unique_per_team(): void
    {
        [, , $token] = $this->adminWithShopModules();

        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/categories', ['name' => 'Ropa'])
            ->assertCreated()
            ->json('data');

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $shared = [
            'price' => 12.5,
            'currency_id' => $currencyId,
            'category_id' => $category['id'],
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'manage_stock' => false,
            'whatsapp_enabled' => true,
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', array_merge($shared, [
                'name' => 'Camiseta A',
                'code' => 'CAM-DUP-001',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.code', 'CAM-DUP-001');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', array_merge($shared, [
                'name' => 'Camiseta B',
                'code' => 'cam-dup-001',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'])
            ->assertJsonPath('errors.code.0', __('The code has already been used in this team.'));
    }

    public function test_product_barcode_is_unique_per_team(): void
    {
        [, , $token] = $this->adminWithShopModules();

        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/categories', ['name' => 'Autopartes'])
            ->assertCreated()
            ->json('data');

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $shared = [
            'price' => 45,
            'currency_id' => $currencyId,
            'category_id' => $category['id'],
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'manage_stock' => false,
            'whatsapp_enabled' => true,
            'barcode' => '7791234567890',
            'oem' => '7H0 698 151 D',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', array_merge($shared, [
                'name' => 'Pastilla A',
                'code' => 'PAD-A',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.barcode', '7791234567890')
            ->assertJsonPath('data.oem', '7H0 698 151 D');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', array_merge($shared, [
                'name' => 'Pastilla B',
                'code' => 'PAD-B',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['barcode']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', array_merge($shared, [
                'name' => 'Pastilla C',
                'code' => 'PAD-C',
                'barcode' => '',
                'oem' => '7H0 698 151 D',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.barcode', null)
            ->assertJsonPath('data.oem', '7H0 698 151 D');
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
                'checkout_fulfillment_types' => ['pickup', 'delivery'],
                'phone' => '1144556677',
                'whatsapp' => '5491144556677',
                'notes' => 'Timbre 2B',
                'delivery_area' => 'CABA',
                'delivery_cost' => 1500,
                'hours' => [
                    [
                        'day' => 'mon',
                        'open' => '09:00',
                        'close' => '13:00',
                        'afternoon_open' => '16:00',
                        'afternoon_close' => '20:00',
                        'closed' => false,
                    ],
                    ['day' => 'sun', 'open' => null, 'close' => null, 'closed' => true],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Sucursal Norte')
            ->assertJsonPath('data.is_main', true)
            ->assertJsonPath('data.show_prices', true)
            ->assertJsonPath('data.whatsapp_enabled', true)
            ->assertJsonPath('data.checkout_payment_methods.0', 'cash')
            ->assertJsonPath('data.phone', '1144556677')
            ->assertJsonPath('data.delivery_area', 'CABA')
            ->assertJsonPath('data.hours.0.day', 'mon')
            ->assertJsonPath('data.hours.0.close', '13:00')
            ->assertJsonPath('data.hours.0.afternoon_open', '16:00')
            ->assertJsonPath('data.hours.0.afternoon_close', '20:00')
            ->assertJsonPath('data.hours.6.closed', true);

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

    public function test_store_can_hide_prices_and_whatsapp(): void
    {
        [, $team, $token] = $this->adminWithShopModules();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/stores')
            ->assertOk();

        $mainId = (int) Store::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('is_main', true)
            ->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/shop/stores/'.$mainId, [
                'name' => 'Principal',
                'code' => 'MAIN',
                'status' => true,
                'is_main' => true,
                'checkout_payment_methods' => ['cash'],
                'checkout_fulfillment_types' => ['pickup'],
                'show_prices' => false,
                'whatsapp_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.show_prices', false)
            ->assertJsonPath('data.whatsapp_enabled', false);

        $store = Store::withoutGlobalScope('team')->find($mainId);
        $this->assertFalse($store->showsPrices());
        $this->assertFalse($store->whatsappEnabled());
    }

    public function test_products_module_is_enough_to_list_stores(): void
    {
        [, $team, $token] = $this->adminWithShopModules(false);
        $this->enableTeamModules($team, ['products']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/stores')
            ->assertOk()
            ->assertJsonPath('success', true);
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

    public function test_can_update_order_items(): void
    {
        [, $team, $token] = $this->adminWithShopModules();
        $spark = $this->createPricedProduct($team, 6900);
        $kit = $this->createPricedProduct($team, 22900);

        $order = Order::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'store_id' => null,
            'order_number' => 'WA-SHOPITEMS01',
            'contact_id' => null,
            'total_amount' => 20700,
            'currency_id' => $spark->currency_id,
            'payment_status' => 'pending',
            'delivery_status' => 'processing',
            'notes' => null,
            'metadata' => [
                'phone' => '5491112345678',
                'checkout_offered' => [
                    'chosen_fulfillment_label' => 'Retiro en tienda',
                    'chosen_payment_label' => 'Efectivo',
                ],
                'items' => [
                    [
                        'product_id' => $spark->id,
                        'name' => $spark->name,
                        'quantity' => 3,
                        'unit_price' => 6900,
                        'line_total' => 20700,
                    ],
                ],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.customer_phone', '5491112345678')
            ->assertJsonPath('data.checkout_chosen_fulfillment_label', 'Retiro en tienda')
            ->assertJsonPath('data.checkout_chosen_payment_label', 'Efectivo');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/shop/orders/'.$order->id, [
                'items' => [
                    [
                        'product_id' => $spark->id,
                        'name' => $spark->name,
                        'quantity' => 2,
                        'unit_price' => 6900,
                    ],
                    [
                        'product_id' => $kit->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_amount', 36700)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.line_total', 13800)
            ->assertJsonPath('data.items.1.product_id', $kit->id)
            ->assertJsonPath('data.items.1.name', $kit->name)
            ->assertJsonPath('data.items.1.quantity', 1)
            ->assertJsonPath('data.items.1.unit_price', 22900);
    }

    public function test_product_search_matches_spanish_accents_when_query_has_none(): void
    {
        [, $team, $token] = $this->adminWithShopModules();
        $this->createPricedProduct($team, 6900, 'Bujía de iridio');
        $this->createPricedProduct($team, 22900, 'Kit de service moto 150cc');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products?search=bujia')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Bujía de iridio');
    }

    public function test_can_send_order_whatsapp_quote(): void
    {
        [, $team, $token] = $this->adminWithShopModules();
        config(['whatsapp.driver' => 'twilio']);

        $spark = $this->createPricedProduct($team, 6900, 'Bujía de iridio');
        $order = Order::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'store_id' => null,
            'order_number' => 'WA-QUOTE01',
            'contact_id' => null,
            'total_amount' => 20700,
            'currency_id' => $spark->currency_id,
            'payment_status' => 'pending',
            'delivery_status' => 'processing',
            'notes' => null,
            'metadata' => [
                'phone' => '5491112345678',
                'items' => [
                    [
                        'product_id' => $spark->id,
                        'name' => $spark->name,
                        'quantity' => 3,
                        'unit_price' => 6900,
                        'line_total' => 20700,
                    ],
                ],
            ],
        ]);

        $sent = [];
        $this->app->instance(WhatsAppGateway::class, new class($sent) implements WhatsAppGateway
        {
            /**
             * @param  array<int, array{to: string, message: string}>  $sent
             */
            public function __construct(private array &$sent) {}

            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                $this->sent[] = ['to' => $to, 'message' => $message];

                return 'ok';
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
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/orders/'.$order->id.'/whatsapp-quote', [
                'items' => [
                    [
                        'product_id' => $spark->id,
                        'name' => $spark->name,
                        'quantity' => 2,
                        'unit_price' => 6900,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_amount', 13800)
            ->assertJsonPath('quote.phone', '5491112345678');

        $this->assertCount(1, $sent);
        $this->assertSame('5491112345678', $sent[0]['to']);
        $this->assertStringContainsString('Cotización actualizada', $sent[0]['message']);
        $this->assertStringContainsString('Bujía de iridio', $sent[0]['message']);
        $this->assertStringContainsString('2 ×', $sent[0]['message']);
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

    private function createPricedProduct(Team $team, float $price, ?string $name = null): Product
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

        $label = $name ?? 'Producto carrito '.$price;

        return Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => $label,
            'code' => 'CART-'.$team->id.'-'.$price.'-'.substr(md5($label), 0, 6),
            'description' => 'Test',
            'price' => $price,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
    }
}
