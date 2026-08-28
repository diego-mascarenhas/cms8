<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    public function test_assistant_filter_includes_collaborators_and_excludes_clients(): void
    {
        [$admin, $team, $token] = $this->adminWithToken();

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->assignRole('collaborator');

        $client = User::factory()->create();
        $team->users()->attach($client, ['role' => 'client']);
        $client->assignRole('client');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users?assistant=1');

        $response->assertOk();
        $ids = collect($response->json('users'))->pluck('id');
        $this->assertTrue($ids->contains($admin->id));
        $this->assertTrue($ids->contains($collaborator->id));
        $this->assertFalse($ids->contains($client->id));
    }

    public function test_basic_filter_includes_collaborators_and_excludes_clients(): void
    {
        [$admin, $team, $token] = $this->adminWithToken();

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'collaborator']);
        $collaborator->assignRole('collaborator');

        $client = User::factory()->create();
        $team->users()->attach($client, ['role' => 'client']);
        $client->assignRole('client');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users?basic=1');

        $response->assertOk();
        $ids = collect($response->json('users'))->pluck('id');
        $this->assertTrue($ids->contains($admin->id));
        $this->assertTrue($ids->contains($collaborator->id));
        $this->assertFalse($ids->contains($client->id));
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

    public function test_store_creates_collaborator_when_role_is_sent(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'name' => 'Carla Colab',
                'email' => 'carla.colab@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'collaborator',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('user.role', 'collaborator');

        $created = User::query()->where('email', 'carla.colab@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('collaborator'));
        $this->assertFalse($created->hasRole('admin'));
        $this->assertTrue($created->teams->contains('id', $team->id));
        $this->assertSame('collaborator', $created->teams->first()?->pivot?->role);
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

    public function test_update_changes_team_member_profile_and_role(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $member = User::factory()->create([
            'name' => 'Viejo Nombre',
            'email' => 'viejo@example.com',
        ]);
        $team->users()->attach($member, ['role' => 'collaborator']);
        $member->assignRole('collaborator');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/users/'.$member->id, [
                'name' => 'Nuevo Nombre',
                'email' => 'nuevo@example.com',
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.name', 'Nuevo Nombre')
            ->assertJsonPath('user.email', 'nuevo@example.com')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.is_owner', false);

        $member->refresh();
        $this->assertSame('Nuevo Nombre', $member->name);
        $this->assertSame('nuevo@example.com', $member->email);
        $this->assertTrue($member->hasRole('admin'));
        $this->assertFalse($member->hasRole('collaborator'));
        $this->assertSame('admin', $member->teams()->first()?->pivot?->role);
    }

    public function test_update_password_changes_team_member_password(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $member = User::factory()->create(['password' => Hash::make('old-secret')]);
        $team->users()->attach($member, ['role' => 'collaborator']);
        $member->assignRole('collaborator');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/users/'.$member->id.'/password', [
                'password' => 'new-secret12',
                'password_confirmation' => 'new-secret12',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('new-secret12', $member->fresh()->password));
    }

    public function test_update_password_rejects_user_outside_team(): void
    {
        [, , $token] = $this->adminWithToken();
        $outsider = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/users/'.$outsider->id.'/password', [
                'password' => 'new-secret12',
                'password_confirmation' => 'new-secret12',
            ])
            ->assertNotFound();
    }

    public function test_send_password_reset_notifies_team_member(): void
    {
        Notification::fake();
        config(['services.assistant.url' => 'https://idoneo-assistant.test']);

        [, $team, $token] = $this->adminWithToken();
        $member = User::factory()->create(['email' => 'colab.reset@example.com']);
        $team->users()->attach($member, ['role' => 'collaborator']);
        $member->assignRole('collaborator');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users/'.$member->id.'/password-reset', [
                'frontend_url' => 'https://idoneo-assistant.test',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($member, ResetPassword::class, function (ResetPassword $notification) use ($member)
        {
            $mail = $notification->toMail($member);

            return str_contains((string) $mail->actionUrl, 'https://idoneo-assistant.test/reset-password?')
                && str_contains((string) $mail->actionUrl, 'email='.urlencode($member->email));
        });
    }
}
