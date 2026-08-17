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

    public function test_admins_filter_excludes_non_admin_members(): void
    {
        [$admin, $team, $token] = $this->adminWithToken();

        $editor = User::factory()->create();
        $team->users()->attach($editor, ['role' => 'editor']);
        $editor->assignRole('editor');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users?admins=1');

        $response->assertOk();
        $ids = collect($response->json('users'))->pluck('id');
        $this->assertTrue($ids->contains($admin->id));
        $this->assertFalse($ids->contains($editor->id));
    }

    public function test_store_creates_admin_on_current_team(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'name' => 'Ana Admin',
                'email' => 'ana.admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('user.email', 'ana.admin@example.com');
        $response->assertJsonPath('user.role', 'admin');

        $created = User::query()->where('email', 'ana.admin@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('admin'));
        $this->assertTrue($created->teams->contains('id', $team->id));
        $this->assertSame($team->id, (int) $created->current_team_id);
    }

    public function test_store_requires_unique_email_and_password_confirmation(): void
    {
        [$admin, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'name' => 'Ana',
                'email' => $admin->email,
                'password' => 'password123',
                'password_confirmation' => 'other',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_destroy_removes_member_but_not_self(): void
    {
        [$admin, $team, $token] = $this->adminWithToken();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $member->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/users/'.$admin->id)
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/users/'.$member->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse($member->fresh()->teams->contains('id', $team->id));
    }
}
