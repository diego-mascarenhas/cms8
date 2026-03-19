<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductLocalCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_local_product(): void
    {
        $this->seed(CurrencySeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $module = Module::query()->create([
            'name' => 'Products',
            'key' => 'products',
            'level' => 1,
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);

        $category = Category::query()->create([
            'name' => 'Test category',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $currencyId = Currency::query()->where('code', 'EUR')->value('id');
        $this->assertNotNull($currencyId);

        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Product to delete',
            'description' => 'Description',
            'price' => 10.00,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $response = $this->actingAs($user)->from(route('product.index'))
            ->delete(route('product.destroy', $product->id));

        $response->assertRedirect(route('product.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
