<?php

namespace Tests\Feature;

use App\Livewire\Teams\TeamMemberManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InviteTeamMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    }

    public function test_invite_stores_administrator_role_on_pending_invitation(): void
    {
        if (! Features::sendsTeamInvitations())
        {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Mail::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('addTeamMemberForm', [
                'email' => 'admin-invite@example.com',
                'role' => 'admin',
            ])
            ->call('addTeamMember');

        $invitation = $user->currentTeam->fresh()->teamInvitations->first();

        $this->assertNotNull($invitation);
        $this->assertSame('admin', $invitation->role);
    }

    public function test_add_team_member_form_defaults_to_admin_role(): void
    {
        if (! Features::sendsTeamInvitations())
        {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->assertSet('addTeamMemberForm.role', 'admin');
    }
}
