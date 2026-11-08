<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AxisMenuPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_hide_an_app_section(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $token = $user->createToken('menu')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/app/menu')
            ->assertOk()
            ->assertJsonPath('data.hidden', []);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/app/menu', ['hidden' => ['/kanban', '/dashboard']])
            ->assertOk()
            ->assertJsonPath('data.hidden', ['/kanban']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/app/menu')
            ->assertOk()
            ->assertJsonPath('data.hidden.0', '/kanban');
    }
}
