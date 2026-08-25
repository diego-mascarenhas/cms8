<?php

namespace Tests\Feature;

use App\Helpers\WhatsAppCartSessionKey;
use App\Models\Team;
use App\Models\User;
use App\Services\OpenCartListingService;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpenCartListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->teams()->attach($this->team->id, ['role' => 'admin']);
        $this->user->current_team_id = $this->team->id;
        $this->user->save();
        $this->user->assignRole($role);
    }

    public function test_orders_index_links_to_open_carts(): void
    {
        $this->addCartItem($this->team->id, '5491199900100', 'ABRAZADERA 8 X 16', 4, 989.43);

        $this->assertSame(1, app(OpenCartListingService::class)->countForTeam((int) $this->team->id));

        $response = $this->actingAs($this->user)->get(route('order.index'));

        $response->assertOk();
        $response->assertSee(__('Carritos abiertos'), false);
        $response->assertSee(route('order.carts'), false);
    }

    public function test_open_carts_page_lists_team_cart_and_hides_other_teams(): void
    {
        $otherTeam = Team::factory()->create();
        $this->addCartItem($this->team->id, '5491199900101', 'ABRAZADERA 8 X 16', 4, 989.43);
        $this->addCartItem((int) $otherTeam->id, '5491199900102', 'Producto ajeno', 1, 10);

        $response = $this->actingAs($this->user)->get(route('order.carts'));
        $response->assertOk();
        $response->assertSee(__('Carritos abiertos'), false);

        $ajax = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->openCartDataTableUrl());

        $ajax->assertOk();
        $this->assertSame(1, (int) $ajax->json('recordsFiltered'));
        $this->assertStringContainsString('ABRAZADERA 8 X 16', (string) $ajax->json('data.0.items_label'));
        $this->assertStringContainsString('4', (string) $ajax->json('data.0.items_label'));
        $this->assertStringNotContainsString('Producto ajeno', json_encode($ajax->json('data')));
    }

    public function test_orders_index_survives_corrupt_cart_storage_rows(): void
    {
        $this->addCartItem($this->team->id, '5491199900104', 'ABRAZADERA 8 X 16', 2, 989.43);

        DB::table('cart_storage')->insert([
            'id' => 'corrupt_cart_items',
            'cart_data' => 'O:8:"stdClass":1:{s:4:"oops";s:10:"truncated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, app(OpenCartListingService::class)->countForTeam((int) $this->team->id));

        $response = $this->actingAs($this->user)->get(route('order.index'));

        $response->assertOk();
        $response->assertSee(__('Carritos abiertos'), false);
    }

    public function test_listing_service_skips_empty_carts(): void
    {
        $phone = WhatsAppCartSessionKey::fromPhone('5491199900103');
        Cart::session($phone)->clear();

        $rows = app(OpenCartListingService::class)->forTeam((int) $this->team->id);

        $this->assertSame(0, $rows->count());
    }

    private function addCartItem(int $teamId, string $phone, string $name, int $quantity, float $price): void
    {
        $session = WhatsAppCartSessionKey::fromPhone($phone);
        Cart::session($session)->clear();
        Cart::session($session)->add([
            'id' => crc32($name.$teamId),
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
            'attributes' => [
                'team_id' => $teamId,
                'category_name' => 'Abrazaderas',
            ],
        ]);
    }

    private function openCartDataTableUrl(): string
    {
        $columnKeys = ['customer', 'channel', 'items_label', 'quantity', 'updated_at', 'total', 'action'];
        $columns = [];
        foreach ($columnKeys as $data)
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return route('order.carts').'?'.http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 4, 'dir' => 'desc']],
            'columns' => $columns,
        ]);
    }
}
