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
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignProductsToMainStoreCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_assigns_products_without_store_to_main_store(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        $productsModuleId = Module::query()->where('key', 'products')->value('id');
        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $productsModuleId,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');

        $mainStore = Store::ensureMainStoreForTeam((int) $team->id);

        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'No branch product',
            'code' => 'NB-1',
            'description' => 'Test.',
            'category_id' => $category->id,
            'currency_id' => $currencyId,
            'store_id' => null,
            'price' => 10.00,
            'status' => true,
        ]);

        $this->assertNull($product->store_id);

        Artisan::call('products:assign-main-store', ['--team' => (string) $team->id]);

        $product->refresh();
        $this->assertSame($mainStore->id, $product->store_id);
    }
}
