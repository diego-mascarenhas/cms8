<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssignableTeamUsers;
use App\View\Components\TeamUsersSelect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignableTeamUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'collaborator', 'editor', 'client'] as $role)
        {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_excludes_team_clients_even_when_they_have_spatie_admin(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->ownedTeams()->firstOrFail();
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $owner->assignRole('admin');

        $staff = User::factory()->create(['name' => 'Staff Member']);
        $team->users()->attach($staff, ['role' => 'collaborator']);
        $staff->assignRole('collaborator');

        $foreignClient = User::factory()->create(['name' => 'AF Construcciones S.R.L.']);
        $team->users()->attach($foreignClient, ['role' => 'client']);
        $foreignClient->assignRole('admin');

        $assignable = AssignableTeamUsers::forTeam($team->fresh());

        $this->assertTrue($assignable->contains('id', $owner->id));
        $this->assertTrue($assignable->contains('id', $staff->id));
        $this->assertFalse($assignable->contains('id', $foreignClient->id));
        $this->assertFalse(
            AssignableTeamUsers::optionsForTeam($team->fresh())->contains('AF Construcciones S.R.L.'),
        );
    }

    public function test_team_users_select_options_exclude_foreign_clients(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $owner = User::factory()->withPersonalTeam()->create(['name' => 'Diego Mascarenhas']);
        $team = $owner->ownedTeams()->firstOrFail();
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $owner->assignRole('admin');

        $foreignClient = User::factory()->create(['name' => 'Agrosem S.R.L.']);
        $team->users()->attach($foreignClient, ['role' => 'client']);
        $foreignClient->assignRole('admin');

        $this->actingAs($owner);

        foreach (['Asesor', 'Responsible', 'Colaborador'] as $label)
        {
            $component = new TeamUsersSelect(
                selected: $owner->id,
                label: $label,
                id: 'responsible_id',
            );

            $this->assertArrayHasKey($owner->id, $component->options->all());
            $this->assertArrayNotHasKey($foreignClient->id, $component->options->all());
            $this->assertFalse($component->options->contains('Agrosem S.R.L.'));
        }
    }

    public function test_role_filter_uses_membership_pivot_not_spatie(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->ownedTeams()->firstOrFail();
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $clientWithAdminSpatie = User::factory()->create(['name' => 'Aguila Andina S.A.']);
        $team->users()->attach($clientWithAdminSpatie, ['role' => 'client']);
        $clientWithAdminSpatie->assignRole('admin');

        $realAdmin = User::factory()->create(['name' => 'Real Admin']);
        $team->users()->attach($realAdmin, ['role' => 'admin']);
        $realAdmin->assignRole('admin');

        $this->actingAs($owner);

        $component = new TeamUsersSelect(
            selected: null,
            label: 'Admin',
            id: 'admin_id',
            role: 'admin',
        );

        $this->assertArrayHasKey($realAdmin->id, $component->options->all());
        $this->assertArrayNotHasKey($clientWithAdminSpatie->id, $component->options->all());
    }
}
