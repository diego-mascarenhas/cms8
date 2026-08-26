<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Order;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsAppCheckoutOrderService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class WhatsAppCheckoutOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);

        if (DB::table('contact_statuses')->where('id', 1)->doesntExist())
        {
            DB::table('contact_statuses')->insert([
                'id' => 1,
                'name' => 'Lead',
                'label_class' => 'bg-label-success',
            ]);
        }

        if (DB::table('currencies')->where('id', 1)->doesntExist())
        {
            DB::table('currencies')->insert([
                'id' => 1,
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_creates_order_with_whatsapp_metadata_and_contact(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'creator_id' => $owner->id,
            'name' => 'Cliente WA',
            'phone' => 600111222,
            'status_id' => 1,
        ]);

        $items = new Collection([
            (object) [
                'id' => 55,
                'name' => 'Bolso tote',
                'price' => 34.5,
                'quantity' => 2,
                'attributes' => (object) [
                    'team_id' => $team->id,
                    'currency_id' => 1,
                    'category_name' => 'Accesorios',
                ],
            ],
        ]);

        $service = app(WhatsAppCheckoutOrderService::class);
        $order = $service->createFromWhatsAppCart((int) $team->id, '600111222', $items, 69.0);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertStringStartsWith('WA-', $order->order_number);
        $this->assertSame(1, (int) $order->currency_id);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('processing', $order->delivery_status);
        $this->assertSame('Pedido realizado por WhatsApp', $order->notes);
        $this->assertSame(69.0, (float) $order->total_amount);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $team->id)->where('phone', 600111222)->first();
        $this->assertNotNull($contact);
        $this->assertSame($contact->id, $order->contact_id);

        $this->assertSame('whatsapp', $order->metadata['source'] ?? null);
        $this->assertSame('600111222', $order->metadata['phone'] ?? null);
        $this->assertCount(1, $order->metadata['items'] ?? []);
        $this->assertSame('Bolso tote', $order->metadata['items'][0]['name'] ?? null);
        $this->assertSame(55, (int) ($order->metadata['items'][0]['product_id'] ?? 0));
        $this->assertSame(69.0, (float) ($order->metadata['items'][0]['line_total'] ?? 0));
    }

    public function test_throws_when_cart_item_team_mismatch(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $items = new Collection([
            (object) [
                'id' => 1,
                'name' => 'X',
                'price' => 1.0,
                'quantity' => 1,
                'attributes' => ['team_id' => 99999],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(WhatsAppCheckoutOrderService::class)->createFromWhatsAppCart((int) $team->id, '600111222', $items, 1.0);
    }

    public function test_creates_order_with_store_id_and_checkout_snapshot(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Sucursal Norte',
            'code' => 'NORTE',
            'status' => true,
            'is_main' => false,
        ]);

        $items = new Collection([
            (object) [
                'id' => 12,
                'name' => 'Item',
                'price' => 10.0,
                'quantity' => 1,
                'attributes' => (object) [
                    'team_id' => $team->id,
                    'currency_id' => 1,
                ],
            ],
        ]);

        $snapshot = [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'payment_method_labels' => [__('Efectivo')],
        ];

        $order = app(WhatsAppCheckoutOrderService::class)->createFromWhatsAppCart(
            (int) $team->id,
            '600999888',
            $items,
            10.0,
            $store->id,
            $snapshot,
        );

        $this->assertSame($store->id, $order->store_id);
        $this->assertSame($snapshot, $order->metadata['checkout_offered'] ?? null);
    }
}
