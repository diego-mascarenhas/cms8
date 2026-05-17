<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamInvitationAcceptFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'user', 'collaborator'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    public function test_guest_without_account_is_sent_to_register_with_locked_email(): void
    {
        if (! Features::sendsTeamInvitations())
        {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $invitation = $team->teamInvitations()->create([
            'email' => 'invited@example.com',
            'role' => 'collaborator',
        ]);

        $url = URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]);

        $this->get($url)
            ->assertRedirect(route('register'))
            ->assertSessionHas('pending_team_invitation_id', $invitation->id);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('invited@example.com', false);
    }

    public function test_registering_via_admin_invitation_syncs_admin_team_and_spatie_roles(): void
    {
        if (! Features::sendsTeamInvitations())
        {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $invitation = $team->teamInvitations()->create([
            'email' => 'admin-member@example.com',
            'role' => 'admin',
        ]);

        $this->get(URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]));

        $this->post(route('register'), [
            'name' => 'Admin Member',
            'email' => 'admin-member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $user = User::query()->where('email', 'admin-member@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasTeamRole($team, 'admin'));
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_registering_via_invitation_joins_team_without_personal_team(): void
    {
        if (! Features::sendsTeamInvitations())
        {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $invitation = $team->teamInvitations()->create([
            'email' => 'newmember@example.com',
            'role' => 'collaborator',
        ]);

        $acceptUrl = URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]);
        $this->get($acceptUrl);

        $response = $this->post(route('register'), [
            'name' => 'New Member',
            'email' => 'newmember@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect();

        $user = User::query()->where('email', 'newmember@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->ownedTeams()->where('personal_team', true)->exists());
        $this->assertTrue($team->fresh()->hasUserWithEmail('newmember@example.com'));
        $this->assertSame($team->id, $user->current_team_id);
        $this->assertCount(0, $team->fresh()->teamInvitations);
        $this->assertTrue($user->hasRole('collaborator'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_existing_user_is_sent_to_login_then_accepts_on_sign_in(): void
    {
        if (! Features::sendsTeamInvitations())
        {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        $owner = User::factory()->withPersonalTeam()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $member = User::factory()->unverified()->create(['email' => 'existing@example.com']);
        $member->ownedTeams()->save(Team::factory()->make(['personal_team' => true]));
        $member->forceFill(['current_team_id' => $member->ownedTeams()->first()->id])->save();

        $invitation = $team->teamInvitations()->create([
            'email' => 'existing@example.com',
            'role' => 'admin',
        ]);

        $acceptUrl = URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]);
        $this->get($acceptUrl)->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => 'existing@example.com',
            'password' => 'password',
        ])->assertRedirect();

        $member->refresh();
        $this->assertTrue($team->fresh()->hasUserWithEmail('existing@example.com'));
        $this->assertSame($team->id, $member->current_team_id);
        $this->assertCount(0, $team->fresh()->teamInvitations);
        $this->assertNotNull($member->email_verified_at);
        $this->assertTrue($member->hasVerifiedEmail());
    }
}
