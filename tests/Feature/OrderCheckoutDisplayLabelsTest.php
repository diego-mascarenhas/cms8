<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCheckoutDisplayLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_labels_from_snapshot_payment_method_keys_include_paypal_and_bizum(): void
    {
        $team = Team::factory()->create();
        $order = Order::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'order_number' => 'WA-TEST123456',
            'contact_id' => null,
            'total_amount' => 10,
            'currency_id' => null,
            'payment_status' => 'pending',
            'delivery_status' => 'processing',
            'metadata' => [
                'checkout_offered' => [
                    'payment_methods' => ['paypal', 'bizum'],
                    'fulfillment_types' => ['pickup'],
                ],
            ],
        ]);

        $labels = $order->checkoutPaymentMethodDisplayLabels();
        $this->assertContains(__('PayPal'), $labels);
        $this->assertContains(__('Bizum'), $labels);

        $fulfillment = $order->checkoutFulfillmentDisplayLabels();
        $this->assertContains(__('Retiro en el local'), $fulfillment);
    }

    public function test_display_labels_fallback_to_store_when_no_snapshot(): void
    {
        $team = Team::factory()->create();
        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Local',
            'code' => 'L1',
            'status' => true,
            'is_main' => true,
            'data' => [
                'checkout' => [
                    'payment_methods' => [Store::CHECKOUT_PAYMENT_PAYPAL],
                    'fulfillment_types' => [Store::CHECKOUT_FULFILLMENT_DELIVERY],
                ],
            ],
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'store_id' => $store->id,
            'order_number' => 'WA-TEST999999',
            'contact_id' => null,
            'total_amount' => 5,
            'currency_id' => null,
            'payment_status' => 'pending',
            'delivery_status' => 'processing',
            'metadata' => [],
        ]);

        $order->load('store');

        $this->assertSame([__('PayPal')], $order->checkoutPaymentMethodDisplayLabels());
        $this->assertSame([__('Envío a domicilio')], $order->checkoutFulfillmentDisplayLabels());
    }
}
