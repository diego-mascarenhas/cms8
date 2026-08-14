<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SanctumOrTeamTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_api_token_can_list_enterprises(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        $team = $user->currentTeam;
        $created = $team->createApiToken('MCP Idoneo', '*');

        $this->getJson('/api/enterprises?search=Fanyion', [
            'Authorization' => 'Bearer '.$created['plain'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('team.id', $team->id);
    }

    public function test_sanctum_token_still_works_for_enterprises(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        Sanctum::actingAs($user);

        $this->getJson('/api/enterprises')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_invalid_bearer_is_unauthenticated(): void
    {
        $this->getJson('/api/enterprises', [
            'Authorization' => 'Bearer totally-invalid-token',
        ])->assertStatus(401);
    }
}
