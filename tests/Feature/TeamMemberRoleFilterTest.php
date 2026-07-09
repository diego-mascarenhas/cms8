<?php

namespace Tests\Feature;

use App\Livewire\Teams\TeamMemberManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamMemberRoleFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_filter_defaults_to_admin_and_only_shows_admin_members(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $owner->currentTeam->users()->attach(
            $adminMember = User::factory()->create(['name' => 'Ada Adminson']),
            ['role' => 'admin'],
        );
        $owner->currentTeam->users()->attach(
            $collaboratorMember = User::factory()->create(['name' => 'Colin Collaborator']),
            ['role' => 'collaborator'],
        );

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->assertSet('roleFilter', 'admin')
            ->assertSee($adminMember->name)
            ->assertDontSee($collaboratorMember->name);
    }

    public function test_role_filter_can_switch_to_another_role(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $owner->currentTeam->users()->attach(
            $adminMember = User::factory()->create(['name' => 'Ada Adminson']),
            ['role' => 'admin'],
        );
        $owner->currentTeam->users()->attach(
            $collaboratorMember = User::factory()->create(['name' => 'Colin Collaborator']),
            ['role' => 'collaborator'],
        );

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->set('roleFilter', 'collaborator')
            ->assertSee($collaboratorMember->name)
            ->assertDontSee($adminMember->name);
    }

    public function test_search_filters_members_by_name_or_email(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $owner->currentTeam->users()->attach(
            $adminMember = User::factory()->create(['name' => 'Ada Adminson', 'email' => 'ada@example.com']),
            ['role' => 'admin'],
        );
        $owner->currentTeam->users()->attach(
            $otherAdmin = User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']),
            ['role' => 'admin'],
        );

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->set('search', 'ada')
            ->assertSee($adminMember->name)
            ->assertDontSee($otherAdmin->name)
            ->set('search', 'bob@example.com')
            ->assertSee($otherAdmin->name)
            ->assertDontSee($adminMember->name);
    }

    public function test_role_filter_all_shows_every_member(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $owner->currentTeam->users()->attach(
            $adminMember = User::factory()->create(['name' => 'Ada Adminson']),
            ['role' => 'admin'],
        );
        $owner->currentTeam->users()->attach(
            $collaboratorMember = User::factory()->create(['name' => 'Colin Collaborator']),
            ['role' => 'collaborator'],
        );

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->set('roleFilter', 'all')
            ->assertSee($adminMember->name)
            ->assertSee($collaboratorMember->name);
    }
}
