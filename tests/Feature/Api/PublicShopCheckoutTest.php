<?php

namespace Tests\Feature\Api;

use App\Enums\ProductCatalogStatus;
use App\Enums\ShoppingCartChannel;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_cart_persists_public_shop_cart(): void
    {
        $team = $this->makeCatalogTeam();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Pizza muzzarella',
            'code' => 'PIZ-1',
            'price' => 1000,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $guestId = (string) Str::uuid();

        $this->putJson('/api/public-shop/www.repuestosav.com/cart', [
            'guest_id' => $guestId,
            'items' => [
                ['code' => 'PIZ-1', 'qty' => 2, 'detail' => 'Extra queso'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lines', 2);

        $cart = ShoppingCart::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('channel', ShoppingCartChannel::PublicShop)
            ->first();

        $this->assertNotNull($cart);
        $item = $cart->items()->withoutGlobalScope('team')->first();
        $this->assertNotNull($item);
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame('Extra queso', $item->option_label);
    }

    public function test_checkout_creates_order_and_clears_cart(): void
    {
        $team = $this->makeCatalogTeam();
        $team->setSetting('whatsapp_from', '5491155687732', [
            'group' => 'integrations',
            'type' => 'string',
            'is_encrypted' => false,
        ]);

        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Empanada',
            'code' => 'EMP-1',
            'price' => 500,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $guestId = (string) Str::uuid();

        $response = $this->postJson('/api/public-shop/www.repuestosav.com/checkout', [
            'guest_id' => $guestId,
            'customer_name' => 'Ana',
            'customer_phone' => '5491112345678',
            'items' => [
                ['code' => 'EMP-1', 'qty' => 3],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $orderNumber = $response->json('data.order_number');
        $this->assertIsString($orderNumber);
        $this->assertStringStartsWith('WEB-', $orderNumber);
        $this->assertStringContainsString('wa.me/5491155687732', (string) $response->json('data.whatsapp_url'));

        $order = Order::withoutGlobalScopes()->where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertSame('public_shop', data_get($order->metadata, 'source'));
        $this->assertEquals(1500.0, (float) $order->total_amount);

        $openCarts = ShoppingCart::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('channel', ShoppingCartChannel::PublicShop)
            ->whereHas('items', fn ($q) => $q->withoutGlobalScope('team'))
            ->count();

        $this->assertSame(0, $openCarts);
    }

    public function test_checkout_persists_fulfillment_payment_store_and_coupon(): void
    {
        $team = $this->makeCatalogTeam();
        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Casa central',
            'code' => 'MAIN',
            'status' => true,
            'is_main' => true,
            'data' => [
                'checkout' => [
                    'payment_methods' => ['cash', 'mercadopago'],
                    'fulfillment_types' => ['pickup', 'delivery'],
                ],
            ],
        ]);

        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Empanada',
            'code' => 'EMP-2',
            'price' => 500,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $guestId = (string) Str::uuid();

        $response = $this->postJson('/api/public-shop/www.repuestosav.com/checkout', [
            'guest_id' => $guestId,
            'customer_name' => 'Ana',
            'customer_phone' => '5491112345678',
            'store_id' => $store->id,
            'fulfillment_type' => 'pickup',
            'payment_method' => 'cash',
            'coupon_code' => 'verano10',
            'items' => [
                ['code' => 'EMP-2', 'qty' => 1],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $order = Order::withoutGlobalScopes()
            ->where('order_number', $response->json('data.order_number'))
            ->first();

        $this->assertNotNull($order);
        $this->assertSame($store->id, (int) $order->store_id);
        $this->assertSame('VERANO10', data_get($order->metadata, 'coupon_code'));
        $this->assertSame('pickup', data_get($order->metadata, 'checkout_offered.chosen_fulfillment'));
        $this->assertSame('cash', data_get($order->metadata, 'checkout_offered.chosen_payment'));
        $this->assertStringContainsString('Cupón: VERANO10', (string) $response->json('data.whatsapp_text'));
        $this->assertStringContainsString('Entrega:', (string) $response->json('data.whatsapp_text'));
        $this->assertStringContainsString('Pago:', (string) $response->json('data.whatsapp_text'));
    }

    public function test_checkout_requires_delivery_address_for_delivery(): void
    {
        $team = $this->makeCatalogTeam();
        Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Casa central',
            'code' => 'MAIN',
            'status' => true,
            'is_main' => true,
            'data' => [
                'checkout' => [
                    'payment_methods' => ['cash'],
                    'fulfillment_types' => ['delivery'],
                ],
            ],
        ]);

        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Empanada',
            'code' => 'EMP-3',
            'price' => 500,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $guestId = (string) Str::uuid();

        $this->postJson('/api/public-shop/www.repuestosav.com/checkout', [
            'guest_id' => $guestId,
            'customer_name' => 'Ana',
            'customer_phone' => '5491112345678',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'items' => [
                ['code' => 'EMP-3', 'qty' => 1],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_address']);

        $response = $this->postJson('/api/public-shop/www.repuestosav.com/checkout', [
            'guest_id' => $guestId,
            'customer_name' => 'Ana',
            'customer_phone' => '5491112345678',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'delivery_address' => 'Calle Falsa 123, CABA',
            'items' => [
                ['code' => 'EMP-3', 'qty' => 1],
            ],
        ])
            ->assertOk();

        $order = Order::withoutGlobalScopes()
            ->where('order_number', $response->json('data.order_number'))
            ->first();

        $this->assertNotNull($order);
        $this->assertSame('Calle Falsa 123, CABA', data_get($order->metadata, 'delivery_address'));
        $this->assertStringContainsString('Dirección: Calle Falsa 123, CABA', (string) $response->json('data.whatsapp_text'));
    }

    private function makeCatalogTeam(): Team
    {
        $team = Team::factory()->create();
        $team->setSetting('business_config', [
            'business_name' => 'Repuestos Avenida',
            'business_website' => 'https://www.repuestosav.com',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team->setSetting('public_catalog_enabled', true, [
            'group' => 'public_shop',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        return $team->fresh();
    }
}
