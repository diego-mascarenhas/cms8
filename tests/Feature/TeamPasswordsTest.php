<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\TeamPassword;
use App\Models\TeamPasswordShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamPasswordsTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPasswordsModule(): User
    {
        Module::query()->firstOrCreate(
            ['key' => 'passwords'],
            [
                'name' => 'Passwords',
                'icon' => 'key',
                'description' => 'Password vault',
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->enableModule('passwords');
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function unlockVault(User $user, string $masterKey): array
    {
        $team = $user->currentTeam;

        return [
            "passwords_unlocked_team_{$team->id}" => true,
            "passwords_unlocked_until_team_{$team->id}" => now()->addMinutes(10)->timestamp,
            '_token' => csrf_token(),
        ];
    }

    public function test_can_set_and_rotate_master_key(): void
    {
        $user = $this->createUserWithPasswordsModule();
        $team = $user->currentTeam;
        $this->actingAs($user);

        $this->put(route('team-settings.passwords.update', $team), [
            'new_master_key' => 'first-secret-key',
            'new_master_key_confirmation' => 'first-secret-key',
            'master_key_hint' => 'first hint',
        ])->assertRedirect(route('team-settings.passwords', $team));

        $team->refresh();
        $this->assertTrue($team->hasPasswordsMasterKey());
        $this->assertTrue($team->verifyPasswordsMasterKey('first-secret-key'));

        $this->put(route('team-settings.passwords.update', $team), [
            'current_master_key' => 'first-secret-key',
            'new_master_key' => 'second-secret-key',
            'new_master_key_confirmation' => 'second-secret-key',
            'master_key_hint' => 'second hint',
        ])->assertRedirect(route('team-settings.passwords', $team));

        $team->refresh();
        $this->assertTrue($team->verifyPasswordsMasterKey('second-secret-key'));
        $this->assertSame('second hint', $team->getSetting('passwords_master_key_hint'));
    }

    public function test_list_is_accessible_when_session_is_expired(): void
    {
        $user = $this->createUserWithPasswordsModule();
        $team = $user->currentTeam;
        $team->setSetting('passwords_master_key_hash', Hash::make('secret-master-key'), [
            'group' => 'passwords',
            'is_encrypted' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                "passwords_unlocked_team_{$team->id}" => true,
                "passwords_unlocked_until_team_{$team->id}" => now()->subMinute()->timestamp,
            ])
            ->get(route('passwords.index'))
            ->assertOk();
    }

    public function test_can_create_one_time_share_and_consume_once(): void
    {
        $user = $this->createUserWithPasswordsModule();
        $team = $user->currentTeam;
        $team->setSetting('passwords_master_key_hash', Hash::make('secret-master-key'), [
            'group' => 'passwords',
            'is_encrypted' => true,
        ]);

        $password = TeamPassword::query()->create([
            'team_id' => $team->id,
            'name' => 'Server root',
            'username' => 'root',
            'password_encrypted' => Crypt::encryptString('top-secret'),
        ]);

        $response = $this->actingAs($user)
            ->withSession($this->unlockVault($user, 'secret-master-key'))
            ->postJson(route('passwords.share', $password));

        $response->assertOk();
        $url = $response->json('url');
        $this->assertNotNull($url);

        $firstView = $this->get($url);
        $firstView->assertOk();
        $firstView->assertSee('top-secret');

        $secondView = $this->get($url);
        $secondView->assertStatus(410);
        $secondView->assertSee('already used');
    }

    public function test_share_link_expires_after_deadline(): void
    {
        $user = $this->createUserWithPasswordsModule();
        $team = $user->currentTeam;
        $password = TeamPassword::query()->create([
            'team_id' => $team->id,
            'name' => 'Demo secret',
            'username' => 'demo',
            'password_encrypted' => Crypt::encryptString('hidden'),
        ]);

        $plainToken = str_repeat('a', 64);
        TeamPasswordShare::query()->create([
            'team_password_id' => $password->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
            'max_views' => 1,
            'views_count' => 0,
        ]);

        $this->get(route('passwords.share.consume', ['token' => $plainToken]))
            ->assertStatus(410)
            ->assertSee('expired');
    }

    public function test_user_cannot_edit_password_from_another_team(): void
    {
        $ownerA = $this->createUserWithPasswordsModule();
        $ownerB = $this->createUserWithPasswordsModule();

        $teamA = $ownerA->currentTeam;
        $teamB = $ownerB->currentTeam;

        $teamA->setSetting('passwords_master_key_hash', Hash::make('master-a'), [
            'group' => 'passwords',
            'is_encrypted' => true,
        ]);

        $passwordFromTeamA = TeamPassword::query()->create([
            'team_id' => $teamA->id,
            'name' => 'Secret A',
            'username' => 'a',
            'password_encrypted' => Crypt::encryptString('secret-a'),
        ]);

        $this->actingAs($ownerB)
            ->withSession([
                "passwords_unlocked_team_{$teamB->id}" => true,
                "passwords_unlocked_until_team_{$teamB->id}" => now()->addMinutes(10)->timestamp,
            ])
            ->get(route('passwords.edit', $passwordFromTeamA))
            ->assertStatus(404);
    }
}
