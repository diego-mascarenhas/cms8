<?php

namespace Tests\Feature;

use App\Livewire\Teams\TeamMemberManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateTeamMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_roles_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(),
            ['role' => 'admin'],
        );

        $component = Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('managingRoleFor', $otherUser)
            ->set('currentRole', 'editor')
            ->call('updateRole');

        $this->assertTrue($otherUser->fresh()->hasTeamRole(
            $user->currentTeam->fresh(),
            'editor',
        ));
        $component->assertSet('currentlyManagingRole', false);
        $component->assertSet('roleFilter', 'editor');
    }

    public function test_manage_role_modal_save_updates_the_member_through_the_ui_action(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(),
            ['role' => 'admin'],
        );

        $component = Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->call('manageRole', $otherUser->id)
            ->assertSet('currentlyManagingRole', true)
            ->assertSet('currentRole', 'admin')
            ->assertSeeHtml('wire:click.prevent="updateRole"')
            ->set('currentRole', 'editor')
            ->call('updateRole')
            ->assertHasNoErrors()
            ->assertSet('currentlyManagingRole', false);

        $this->assertTrue($otherUser->fresh()->hasTeamRole(
            $user->currentTeam->fresh(),
            'editor',
        ));
        $component->assertSee($otherUser->name);
    }

    public function test_only_team_owner_can_update_team_member_roles(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(),
            ['role' => 'admin'],
        );

        $this->actingAs($otherUser);

        $component = Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('managingRoleFor', $otherUser)
            ->set('currentRole', 'editor')
            ->call('updateRole')
            ->assertStatus(403);

        $this->assertTrue($otherUser->fresh()->hasTeamRole(
            $user->currentTeam->fresh(),
            'admin',
        ));
    }
}
