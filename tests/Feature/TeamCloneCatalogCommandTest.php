<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCloneCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedProductsModule(): Module
    {
        return Module::query()->create([
            'name' => 'Products',
            'key' => 'products',
            'icon' => 'ti-package',
            'description' => null,
            'is_core' => true,
            'group' => 'commerce',
            'order' => 1,
            'status' => 1,
        ]);
    }

    private function seedCurrency(): void
    {
        Currency::query()->create([
            'code' => 'ARS',
            'name' => 'Peso',
            'symbol' => '$',
            'status' => true,
        ]);
    }

    public function test_clone_catalog_copies_stores_categories_and_products(): void
    {
        $module = $this->seedProductsModule();
        $this->seedCurrency();
        $currencyId = (int) Currency::query()->where('code', 'ARS')->value('id');

        $sourceUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $sourceTeam = Team::factory()->create(['user_id' => $sourceUser->id]);
        $targetTeam = Team::factory()->create(['user_id' => $targetUser->id]);

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $sourceTeam->id,
            'name' => 'Branch A',
            'code' => 'BR-1',
            'address' => null,
            'data' => null,
            'status' => true,
            'is_main' => true,
        ]);

        $parent = Category::query()->create([
            'name' => 'Parent',
            'module_id' => $module->id,
            'team_id' => $sourceTeam->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $child = Category::query()->create([
            'name' => 'Child',
            'module_id' => $module->id,
            'team_id' => $sourceTeam->id,
            'parent_id' => $parent->id,
            'status' => true,
            'order' => 1,
        ]);

        Product::withoutGlobalScope('team')->create([
            'team_id' => $sourceTeam->id,
            'name' => 'With store',
            'code' => 'SKU-A',
            'description' => 'D1',
            'short_description' => null,
            'price' => 100,
            'sale_price' => null,
            'currency_id' => $currencyId,
            'category_id' => $child->id,
            'store_id' => $store->id,
            'status' => true,
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'manage_stock' => false,
            'stock_quantity' => 0,
            'whatsapp_enabled' => false,
        ]);

        Product::withoutGlobalScope('team')->create([
            'team_id' => $sourceTeam->id,
            'name' => 'No store',
            'code' => 'SKU-B',
            'description' => 'D2',
            'short_description' => null,
            'price' => 50,
            'sale_price' => null,
            'currency_id' => $currencyId,
            'category_id' => $child->id,
            'store_id' => null,
            'status' => true,
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'manage_stock' => false,
            'stock_quantity' => 0,
            'whatsapp_enabled' => false,
        ]);

        $this->artisan('team:clone-catalog', [
            'source_team_id' => $sourceTeam->id,
            'target_team_id' => $targetTeam->id,
        ])->assertSuccessful();

        $targetTeam->refresh();
        $this->assertTrue($targetTeam->hasModule('products'));

        $this->assertSame(1, Store::withoutGlobalScope('team')->where('team_id', $targetTeam->id)->count());
        $this->assertSame(2, Category::query()->where('team_id', $targetTeam->id)->where('module_id', $module->id)->count());
        $this->assertSame(2, Product::withoutGlobalScope('team')->where('team_id', $targetTeam->id)->count());

        $targetParent = Category::query()->where('team_id', $targetTeam->id)->where('name', 'Parent')->first();
        $targetChild = Category::query()->where('team_id', $targetTeam->id)->where('name', 'Child')->first();
        $this->assertNotNull($targetParent);
        $this->assertNotNull($targetChild);
        $this->assertSame($targetParent->id, $targetChild->parent_id);

        $clonedStore = Store::withoutGlobalScope('team')->where('team_id', $targetTeam->id)->where('code', 'BR-1')->first();
        $this->assertNotNull($clonedStore);
        $withStore = Product::withoutGlobalScope('team')->where('team_id', $targetTeam->id)->where('name', 'With store')->first();
        $this->assertSame($clonedStore->id, $withStore->store_id);
    }

    public function test_dry_run_does_not_write_rows(): void
    {
        $module = $this->seedProductsModule();
        $this->seedCurrency();
        $currencyId = (int) Currency::query()->where('code', 'ARS')->value('id');

        $sourceUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $sourceTeam = Team::factory()->create(['user_id' => $sourceUser->id]);
        $targetTeam = Team::factory()->create(['user_id' => $targetUser->id]);

        Category::query()->create([
            'name' => 'Only',
            'module_id' => $module->id,
            'team_id' => $sourceTeam->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        Product::withoutGlobalScope('team')->create([
            'team_id' => $sourceTeam->id,
            'name' => 'P',
            'code' => 'SKU-1',
            'description' => 'x',
            'short_description' => null,
            'price' => 1,
            'sale_price' => null,
            'currency_id' => $currencyId,
            'category_id' => Category::query()->where('team_id', $sourceTeam->id)->value('id'),
            'store_id' => null,
            'status' => true,
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'manage_stock' => false,
            'stock_quantity' => 0,
            'whatsapp_enabled' => false,
        ]);

        $this->artisan('team:clone-catalog', [
            'source_team_id' => $sourceTeam->id,
            'target_team_id' => $targetTeam->id,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Category::query()->where('team_id', $targetTeam->id)->count());
        $this->assertSame(0, Product::withoutGlobalScope('team')->where('team_id', $targetTeam->id)->count());
    }

    public function test_same_source_and_target_fails(): void
    {
        $this->seedProductsModule();
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->artisan('team:clone-catalog', [
            'source_team_id' => $team->id,
            'target_team_id' => $team->id,
        ])->assertFailed();
    }
}
