<?php

namespace Tests\Feature;

use App\Livewire\Teams\TeamMemberManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateTeamMemberPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_owner_can_set_a_member_password(): void
    {
        $this->actingAs($owner = User::factory()->withPersonalTeam()->create());

        $owner->currentTeam->users()->attach(
            $member = User::factory()->create([
                'password' => Hash::make('password'),
            ]),
            ['role' => 'collaborator'],
        );

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->call('updateMemberPassword', $member->id, 'NuevaClave1', 'NuevaClave1')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave1', $member->fresh()->password));
        $this->assertFalse(Hash::check('password', $member->fresh()->password));
    }

    public function test_password_change_requires_confirmation(): void
    {
        $this->actingAs($owner = User::factory()->withPersonalTeam()->create());

        $owner->currentTeam->users()->attach(
            $member = User::factory()->create(),
            ['role' => 'collaborator'],
        );

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->call('updateMemberPassword', $member->id, 'NuevaClave1', 'OtraClave1')
            ->assertHasErrors(['password']);
    }

    public function test_only_team_owner_can_set_a_member_password(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $owner->currentTeam->users()->attach(
            $member = User::factory()->create([
                'password' => Hash::make('password'),
            ]),
            ['role' => 'admin'],
        );

        $this->actingAs($member);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->call('updateMemberPassword', $member->id, 'NuevaClave1', 'NuevaClave1')
            ->assertStatus(403);

        $this->assertTrue(Hash::check('password', $member->fresh()->password));
    }

    public function test_cannot_set_password_for_a_user_outside_the_team(): void
    {
        $this->actingAs($owner = User::factory()->withPersonalTeam()->create());
        $outsider = User::factory()->withPersonalTeam()->create();

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->call('updateMemberPassword', $outsider->id, 'NuevaClave1', 'NuevaClave1')
            ->assertStatus(403);

        $this->assertFalse(Hash::check('NuevaClave1', $outsider->fresh()->password));
    }

    public function test_member_list_shows_the_change_password_action(): void
    {
        $this->actingAs($owner = User::factory()->withPersonalTeam()->create());

        $owner->currentTeam->users()->attach(
            $member = User::factory()->create([
                'name' => 'Veronica Gomez',
                'email' => 'veronica@example.com',
            ]),
            ['role' => 'admin'],
        );

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->assertSee('Veronica Gomez (veronica@example.com)')
            ->assertSeeHtml('ti-key')
            ->assertSeeHtml('changeTeamMemberPassword('.$member->id.')')
            ->assertSeeHtml('href="javascript:;"');
    }
}
