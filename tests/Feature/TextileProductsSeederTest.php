<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
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
        Team::factory()->create([
            'user_id' => $user->id,
            'name' => "Demo's Team",
        ]);

        $this->seed(TextileProductsSeeder::class);

        $team = Team::query()->where('name', "Demo's Team")->first();
        $this->assertNotNull($team);

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
