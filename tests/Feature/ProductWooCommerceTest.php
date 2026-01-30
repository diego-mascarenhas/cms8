<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductWooCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_create_redirects_to_index_when_woocommerce_not_configured(): void
    {
        $user = $this->createUserWithTeamAndRole('admin');

        $response = $this->actingAs($user)->get(route('product.create'));

        $response->assertRedirect(route('product.index'));
        $response->assertSessionHas('error');
    }

    public function test_product_index_shows_woocommerce_list_when_configured(): void
    {
        Http::fake([
            '*wp-json/wc/v3/products*' => Http::response([['id' => 1, 'name' => 'Test Product', 'price' => '10.00', 'status' => 'publish', 'stock_status' => 'instock']], 200),
        ]);

        $user = $this->createUserWithTeamAndRole('admin');
        $team = $user->currentTeam;
        $team->setSetting('woocommerce_url', 'https://example.com');
        $team->setSetting('woocommerce_consumer_key', 'ck_test');
        $team->setSetting('woocommerce_consumer_secret', 'cs_test');

        $response = $this->actingAs($user)->get(route('product.index'));

        $response->assertStatus(200);
        $response->assertViewIs('product.woocommerce-list');
        $response->assertSee('Test Product');
    }

    private function createUserWithTeamAndRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }
}
