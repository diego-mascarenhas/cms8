<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'collaborator', 'editor', 'client', 'developer', 'technical'] as $role)
        {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->ownedTeams()->first();
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');

        $token = $admin->createToken('users-test')->plainTextToken;

        return [$admin, $team, $token];
    }

    public function test_assignable_users_exclude_clients(): void
    {
        [$admin, $team, $token] = $this->adminWithToken();

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();
        $collaborator->assignRole('collaborator');

        $editor = User::factory()->create();
        $team->users()->attach($editor, ['role' => 'editor']);
        $editor->forceFill(['current_team_id' => $team->id])->save();
        $editor->assignRole('editor');

        $client = User::factory()->create(['name' => 'Cliente Excluido']);
        $team->users()->attach($client, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $team->id])->save();
        $client->assignRole('client');

        $all = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users');

        $all->assertOk();
        $allIds = collect($all->json('users'))->pluck('id');
        $this->assertTrue($allIds->contains($client->id));

        $assignable = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users?assignable=1');

        $assignable->assertOk()
            ->assertJsonPath('success', true);

        $assignableIds = collect($assignable->json('users'))->pluck('id');

        $this->assertTrue($assignableIds->contains($admin->id));
        $this->assertTrue($assignableIds->contains($collaborator->id));
        $this->assertTrue($assignableIds->contains($editor->id));
        $this->assertFalse($assignableIds->contains($client->id));
    }
}
