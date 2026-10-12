<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class CurrentTeamSwitchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team, 2: string}
     */
    private function memberWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $owned = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $owned->id])->save();
        $token = $user->createToken('teams-test')->plainTextToken;

        return [$user, $owned, $token];
    }

    public function test_lists_every_team_the_user_belongs_to(): void
    {
        [$user, $owned, $token] = $this->memberWithToken();

        $other = Team::factory()->create(['name' => 'Humano Team']);
        $other->users()->attach($user, ['role' => 'admin']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/teams');

        $response->assertOk();

        $teams = collect($response->json('teams'));
        $this->assertTrue($teams->pluck('id')->contains($owned->id));
        $this->assertTrue($teams->pluck('id')->contains($other->id));
        $this->assertSame($owned->id, $teams->firstWhere('is_current', true)['id']);
        $this->assertSame('owner', $teams->firstWhere('id', $owned->id)['role']);
        $this->assertSame('admin', $teams->firstWhere('id', $other->id)['role']);
    }

    public function test_switch_current_team_updates_the_active_team(): void
    {
        [$user, $owned, $token] = $this->memberWithToken();

        $other = Team::factory()->create(['name' => 'Humano Team']);
        $other->users()->attach($user, ['role' => 'collaborator']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/auth/current-team', ['team_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('current_team.id', $other->id)
            ->assertJsonPath('current_team.name', 'Humano Team');

        $this->assertSame($other->id, (int) $user->fresh()->current_team_id);
        $this->assertNotSame($owned->id, (int) $user->fresh()->current_team_id);
    }

    public function test_switch_rejects_a_team_the_user_does_not_belong_to(): void
    {
        [$user, $owned, $token] = $this->memberWithToken();
        $stranger = Team::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/auth/current-team', ['team_id' => $stranger->id])
            ->assertForbidden()
            ->assertJsonPath('message', 'No pertenecés a ese equipo.');

        $this->assertSame($owned->id, (int) $user->fresh()->current_team_id);
    }
}
