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

class ProductDataTableSearchNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->teams()->attach($team->id, ['role' => 'admin']);
        $this->user->current_team_id = $team->id;
        $this->user->save();
        $this->user->assignRole($role);
    }

    public function test_datatable_search_matches_product_name_when_query_has_spanish_accent(): void
    {
        [$matching] = $this->createSparkPlugAndOtherProduct();

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->productDataTableUrl('bujía'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matching->id, (string) $response->json('data.0.DT_RowId'));
    }

    public function test_datatable_search_matches_product_name_when_query_has_no_accent(): void
    {
        [$matching] = $this->createSparkPlugAndOtherProduct();

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->productDataTableUrl('bujia'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame((string) $matching->id, (string) $response->json('data.0.DT_RowId'));
    }

    /**
     * @return array{0: Product, 1: Product}
     */
    private function createSparkPlugAndOtherProduct(): array
    {
        $team = $this->user->currentTeam;
        $this->assertNotNull($team);

        $productsModuleId = Module::query()->where('key', 'products')->value('id');
        $this->assertNotNull($productsModuleId);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $productsModuleId,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $matching = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'BUJIA 3 ELECT VW GOL',
            'code' => '28356',
            'description' => 'Spark plug',
            'category_id' => $category->id,
            'currency_id' => $currencyId,
            'store_id' => null,
            'price' => 10.00,
            'status' => true,
        ]);

        $other = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'ABRAZADERA 12 X 22',
            'code' => '25259',
            'description' => 'Clamp',
            'category_id' => $category->id,
            'currency_id' => $currencyId,
            'store_id' => null,
            'price' => 10.00,
            'status' => true,
        ]);

        return [$matching, $other];
    }

    private function productDataTableUrl(string $searchValue): string
    {
        $columnKeys = ['id', 'name', 'code', 'store.name', 'category.name', 'price', 'status', 'action'];
        $columns = [];
        foreach ($columnKeys as $data)
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => in_array($data, ['name', 'code', 'store.name', 'category.name'], true) ? 'true' : 'false',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return route('product.index').'?'.http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => $searchValue, 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => $columns,
        ]);
    }
}
