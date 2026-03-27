<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductDataTableStoreColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_datatable_json_includes_store_column_when_product_has_no_store(): void
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
        $this->assertNotNull($productsModuleId);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $productsModuleId,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Orphan Product',
            'code' => 'ORPH-1',
            'description' => 'Test product without store.',
            'category_id' => $category->id,
            'currency_id' => $currencyId,
            'store_id' => null,
            'price' => 10.00,
            'status' => true,
        ]);

        $columnKeys = ['id', 'name', 'code', 'store.name', 'category.name', 'price', 'status', 'action'];
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

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('product.index'), [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $columns,
        ]);

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertNotNull($row);
        $this->assertSame('—', data_get($row, 'store.name'));
    }
}
