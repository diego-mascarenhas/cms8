<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\TeamDemoProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamDemoProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_thirty_products_and_categories_for_demo_team(): void
    {
        $this->seed(CurrencySeeder::class);

        Module::query()->create([
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

        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name' => "Demo's Team",
        ]);

        $this->seed(TeamDemoProductsSeeder::class);

        $this->assertSame(
            30,
            Product::withoutGlobalScope('team')->where('team_id', $team->id)->count(),
        );

        $productsModule = Module::query()->where('key', 'products')->first();
        $this->assertNotNull($productsModule);

        $this->assertSame(
            5,
            Category::query()
                ->where('team_id', $team->id)
                ->where('module_id', $productsModule->id)
                ->count(),
        );
    }
}
