<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Store;
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

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Product to delete',
            'code' => 'DEL-001',
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

    public function test_admin_can_open_local_create_store_and_update_product(): void
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
            'name' => 'Ropa',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_team_id' => $team->id,
        ]);

        $this->actingAs($user->fresh())->get(route('product.create'))->assertOk();

        $this->assertDatabaseHas('stores', [
            'team_id' => $team->id,
            'code' => 'MAIN',
            'name' => 'Principal',
            'is_main' => true,
        ]);
        $mainStoreId = Store::withoutGlobalScope('team')->where('team_id', $team->id)->where('code', 'MAIN')->value('id');
        $this->assertNotNull($mainStoreId);

        $this->actingAs(User::query()->findOrFail($user->id))->post(route('product.store'), [
            'name' => 'Camiseta test',
            'code' => 'CAM-TEST-001',
            'description' => '<p>Algodón</p>',
            'short_description' => '<p>Resumen</p>',
            'price' => '12.50',
            'sale_price' => '10.00',
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'manage_stock' => '0',
            'whatsapp_enabled' => '1',
            'store_id' => (string) $mainStoreId,
        ])->assertRedirect(route('product.index'));

        $product = Product::withoutGlobalScope('team')->where('team_id', $team->id)->where('name', 'Camiseta test')->first();
        $this->assertNotNull($product);
        $this->assertSame((int) $mainStoreId, (int) $product->store_id);

        $this->actingAs(User::query()->findOrFail($user->id))->get(route('product.edit', $product->id))->assertOk();

        $this->actingAs(User::query()->findOrFail($user->id))->put(route('product.update', $product->id), [
            'name' => 'Camiseta test actualizada',
            'code' => 'CAM-TEST-001',
            'description' => '<p>Algodón orgánico</p>',
            'short_description' => '<p>Resumen nuevo</p>',
            'price' => '14.00',
            'sale_price' => '',
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'catalog_status' => 'draft',
            'stock_status' => 'outofstock',
            'manage_stock' => '1',
            'stock_quantity' => '5',
            'whatsapp_enabled' => '0',
        ])->assertRedirect(route('product.index'));

        $product->refresh();
        $this->assertSame('Camiseta test actualizada', $product->name);
        $this->assertFalse($product->whatsapp_enabled);
        $this->assertSame('draft', $product->catalog_status->value);
        $this->assertTrue($product->manage_stock);
        $this->assertSame(5, $product->stock_quantity);
        $this->assertNull($product->sale_price);
    }
}
