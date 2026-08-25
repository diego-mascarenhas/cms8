<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamModulesByPricingPlanSyncer;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\TextileProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextileProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_textile_products_for_demo_team(): void
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
            'name' => 'Demo',
        ]);

        foreach (['products', 'stores', 'orders'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst($key),
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'assistant');

        $this->seed(TextileProductsSeeder::class);

        $team = $team->fresh();
        $this->assertNotNull($team);
        $this->assertTrue($team->hasModule('products'));
        $this->assertTrue($team->hasModule('stores'));
        $this->assertTrue($team->hasModule('orders'));

        $this->assertSame(
            18,
            Product::withoutGlobalScope('team')->where('team_id', $team->id)->count(),
        );

        $shirt = Product::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('name', 'Camiseta básica algodón')
            ->first();
        $this->assertNotNull($shirt);
        $this->assertNotNull($shirt->short_description);
        $this->assertStringContainsString('Camiseta básica unisex', $shirt->short_description);
        $this->assertNotNull($shirt->image);
        $this->assertStringStartsWith('https://images.unsplash.com/', $shirt->image);
    }
}
