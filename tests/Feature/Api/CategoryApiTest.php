<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_categories_include_the_parent_name(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $token = $user->createToken('categories-test')->plainTextToken;

        $module = Module::query()->create([
            'name' => 'Tasks',
            'key' => 'tasks',
            'status' => 1,
        ]);

        $parent = Category::query()->create([
            'name' => 'Administración',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'status' => 1,
            'order' => 1,
        ]);

        Category::query()->create([
            'name' => 'Cobranza',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'parent_id' => $parent->id,
            'status' => 1,
            'order' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/category?module_key=tasks');

        $response->assertOk()
            ->assertJsonPath('0.name', 'Administración')
            ->assertJsonPath('1.name', 'Cobranza')
            ->assertJsonPath('1.parent_name', 'Administración');
    }
}
