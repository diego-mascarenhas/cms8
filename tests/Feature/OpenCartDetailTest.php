<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\Team;
use App\Models\User;
use App\Services\ShoppingCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpenCartDetailTest extends TestCase
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

    public function test_admin_can_view_and_edit_cart_detail(): void
    {
        $cart = $this->addCartItem($this->team->id, '5491199900101', 'ABRAZADERA 8 X 16', 3, 989.43);
        $item = $cart->items()->withoutGlobalScope('team')->first();

        $response = $this->actingAs($this->user)->get(route('order.carts.show', $cart->id));

        $response->assertOk();
        $response->assertSee(__('Detalle y edición de productos del carrito'), false);
        $response->assertSee('ABRAZADERA 8 X 16', false);
        $response->assertSee('name="items[0][quantity]"', false);
        $response->assertSee((string) $item->quantity, false);
        $response->assertSee(__('Guardar cambios'), false);
    }

    public function test_admin_can_update_item_quantity(): void
    {
        $cart = $this->addCartItem($this->team->id, '5491199900102', 'ABRAZADERA 8 X 16', 3, 989.43);
        $item = $cart->items()->withoutGlobalScope('team')->first();

        $response = $this->actingAs($this->user)->put(route('order.carts.update', $cart->id), [
            'items' => [
                ['id' => $item->id, 'quantity' => 7],
            ],
        ]);

        $response->assertRedirect(route('order.carts.show', $cart->id));
        $this->assertSame(7, (int) $item->fresh()->quantity);
    }

    public function test_admin_can_remove_item_and_is_sent_back_when_cart_empties(): void
    {
        $cart = $this->addCartItem($this->team->id, '5491199900103', 'ABRAZADERA 8 X 16', 2, 989.43);
        $item = $cart->items()->withoutGlobalScope('team')->first();

        $response = $this->actingAs($this->user)->delete(route('order.carts.items.destroy', [$cart->id, $item->id]));

        $response->assertRedirect(route('order.carts'));
        $this->assertDatabaseMissing('shopping_cart_items', ['id' => $item->id]);
    }

    public function test_admin_can_delete_cart(): void
    {
        $cart = $this->addCartItem($this->team->id, '5491199900104', 'ABRAZADERA 8 X 16', 1, 989.43);

        $response = $this->actingAs($this->user)->delete(route('order.carts.destroy', $cart->id));

        $response->assertRedirect(route('order.carts'));
        $this->assertDatabaseMissing('shopping_carts', ['id' => $cart->id]);
    }

    public function test_other_team_cart_is_not_visible(): void
    {
        $otherTeam = Team::factory()->create();
        $cart = $this->addCartItem((int) $otherTeam->id, '5491199900105', 'Producto ajeno', 1, 10);

        $this->actingAs($this->user)
            ->get(route('order.carts.show', $cart->id))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->put(route('order.carts.update', $cart->id), [
                'items' => [['id' => 1, 'quantity' => 2]],
            ])
            ->assertNotFound();
    }

    public function test_update_rejects_invalid_quantity(): void
    {
        $cart = $this->addCartItem($this->team->id, '5491199900106', 'ABRAZADERA 8 X 16', 1, 989.43);
        $item = $cart->items()->withoutGlobalScope('team')->first();

        $this->actingAs($this->user)
            ->from(route('order.carts.show', $cart->id))
            ->put(route('order.carts.update', $cart->id), [
                'items' => [
                    ['id' => $item->id, 'quantity' => -1],
                ],
            ])
            ->assertRedirect(route('order.carts.show', $cart->id))
            ->assertSessionHasErrors('items.0.quantity');
    }

    private function addCartItem(int $teamId, string $phone, string $name, int $quantity, float $price): ShoppingCart
    {
        $carts = app(ShoppingCartService::class);
        $cart = $carts->forWhatsApp($teamId, $phone);
        $product = Product::factory()->create([
            'team_id' => $teamId,
            'name' => $name,
            'price' => $price,
        ]);
        $carts->addProduct($cart, $product, $quantity);

        return $cart->fresh();
    }
}
