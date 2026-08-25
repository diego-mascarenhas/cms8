<?php

namespace Tests\Feature;

use App\Models\DatabaseStorageModel;
use Darryldecode\Cart\CartCollection;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Darryldecode\Cart\ItemCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseStorageModelCartEncodingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_payload_is_stored_without_null_bytes_and_round_trips(): void
    {
        $phone = '5491199900200';
        Cart::session($phone)->clear();
        Cart::session($phone)->add([
            'id' => 21861,
            'name' => 'ABRAZADERA 8 X 16',
            'price' => 989.43,
            'quantity' => 2,
            'attributes' => [
                'team_id' => 7,
                'category_name' => 'Abrazadera',
            ],
        ]);

        $raw = DB::table('cart_storage')->where('id', $phone.'_cart_items')->value('cart_data');
        $this->assertIsString($raw);
        $this->assertStringStartsWith(DatabaseStorageModel::CART_DATA_PREFIX, $raw);
        $this->assertStringNotContainsString("\0", $raw);

        $phpSerialize = serialize(new CartCollection([
            21861 => new ItemCollection([
                'id' => 21861,
                'name' => 'ABRAZADERA 8 X 16',
                'price' => 989.43,
                'quantity' => 2,
                'attributes' => [],
            ]),
        ]));
        $this->assertStringContainsString("\0", $phpSerialize);

        Cart::session($phone);
        $item = Cart::getContent()->first();
        $this->assertNotNull($item);
        $this->assertSame('ABRAZADERA 8 X 16', $item->name);
        $this->assertSame(2, (int) $item->quantity);
        $this->assertEqualsWithDelta(1978.86, (float) Cart::getTotal(), 0.01);
    }

    public function test_truncated_php_serialize_is_skipped_like_pgsql_null_byte_cut(): void
    {
        $phpSerialize = serialize(new CartCollection(['keep' => true]));
        $truncated = explode("\0", $phpSerialize, 2)[0];
        $this->assertNotSame($phpSerialize, $truncated);
        $this->assertLessThan(60, strlen($truncated));

        DB::table('cart_storage')->insert([
            'id' => '34722372858_cart_items',
            'cart_data' => $truncated,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DatabaseStorageModel::query()->find('34722372858_cart_items');
        $this->assertNotNull($row);
        $this->assertSame([], $row->cart_data);
    }

    public function test_legacy_complete_serialize_rows_still_read(): void
    {
        $payload = serialize(['ok' => 1]);

        DB::table('cart_storage')->insert([
            'id' => 'legacy_cart_items',
            'cart_data' => $payload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DatabaseStorageModel::query()->find('legacy_cart_items');
        $this->assertSame(['ok' => 1], $row->cart_data);
    }
}
