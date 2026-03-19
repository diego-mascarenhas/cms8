<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamSetting;
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
        $this->configureWooCommerceTeamSettings($team);

        $response = $this->actingAs($user)->get(route('product.index'));

        $response->assertOk();
        $response->assertSee('Test Product', false);
        $response->assertSee(__('Add product'), false);
    }

    public function test_product_index_without_woocommerce_shows_add_product_button_for_admin(): void
    {
        $user = $this->createUserWithTeamAndRole('admin');

        $response = $this->actingAs($user)->get(route('product.index'));

        $response->assertOk();
        $response->assertSee(__('Add product'), false);
    }

    private function configureWooCommerceTeamSettings(Team $team): void
    {
        foreach (
            [
                'woocommerce_url' => 'https://example.com',
                'woocommerce_consumer_key' => 'ck_test',
                'woocommerce_consumer_secret' => 'cs_test',
            ] as $key => $value
        ) {
            TeamSetting::query()->updateOrCreate(
                ['team_id' => $team->id, 'key' => $key],
                [
                    'value' => $value,
                    'type' => 'string',
                    'group' => 'general',
                    'is_encrypted' => false,
                ],
            );
        }

        $team->unsetRelation('settings');
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
