<?php

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamMemberRole;
use App\Livewire\Teams\TeamMemberManager;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Team;
use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamMemberClientRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'client', 'collaborator', 'editor', 'marketing'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);
    }

    private function createLinkedClientContact(Team $team, User $owner, User $clientUser): Contact
    {
        $status = ContactStatus::query()->firstOrFail();

        return Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Portal Client',
            'email' => $clientUser->email,
            'user_id' => $clientUser->id,
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => $status->id,
            'language' => 'es',
            'country' => 724,
        ]);
    }

    public function test_removing_team_member_restores_client_role_when_linked_to_contact(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $clientUser = User::factory()->create();
        $clientUser->assignRole('client');
        $team->users()->attach($clientUser, ['role' => 'client']);
        $clientUser->forceFill(['current_team_id' => $team->id])->save();

        $this->createLinkedClientContact($team, $owner, $clientUser);

        $team->users()->updateExistingPivot($clientUser->id, ['role' => 'collaborator']);
        app(JetstreamTeamRoleSynchronizer::class)->sync($clientUser->fresh(), 'collaborator');

        $clientUser->refresh();
        $this->assertTrue($clientUser->hasRole('collaborator'));
        $this->assertFalse($clientUser->hasRole('client'));

        app(RemoveTeamMember::class)->remove($owner, $team, $clientUser->fresh());

        $clientUser->refresh();

        $this->assertFalse($team->fresh()->hasUserWithEmail($clientUser->email));
        $this->assertTrue($clientUser->hasRole('client'));
        $this->assertFalse($clientUser->hasRole('collaborator'));
    }

    public function test_can_add_linked_contact_user_as_collaborator_and_unlinks_portal(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $clientUser = User::factory()->create(['email' => 'portal-client@example.com']);
        $clientUser->assignRole('client');
        $contact = $this->createLinkedClientContact($team, $owner, $clientUser);

        app(AddTeamMember::class)->add($owner, $team, $clientUser->email, 'collaborator');

        $this->assertTrue($team->fresh()->hasUserWithEmail($clientUser->email));
        $pivotRole = $team->fresh()->users()->where('users.id', $clientUser->id)->first()?->membership?->role;
        $this->assertSame('collaborator', $pivotRole);
        $this->assertNull($contact->fresh()->user_id);
    }

    public function test_promoting_linked_client_to_collaborator_unlinks_portal_contact(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $clientUser = User::factory()->create();
        $clientUser->assignRole('client');
        $team->users()->attach($clientUser, ['role' => 'client']);
        $contact = $this->createLinkedClientContact($team, $owner, $clientUser);

        app(UpdateTeamMemberRole::class)->update($owner, $team, $clientUser->id, 'collaborator');

        $pivotRole = $team->fresh()->users()->where('users.id', $clientUser->id)->first()?->membership?->role;
        $this->assertSame('collaborator', $pivotRole);
        $this->assertTrue($clientUser->fresh()->hasRole('collaborator'));
        $this->assertNull($contact->fresh()->user_id);
    }

    public function test_admin_with_a_crm_contact_can_change_team_role(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $adminMember = User::factory()->create(['name' => 'Ada Adminson']);
        $adminMember->assignRole('admin');
        $team->users()->attach($adminMember, ['role' => 'admin']);
        $this->createLinkedClientContact($team, $owner, $adminMember);

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $team])
            ->call('manageRole', $adminMember->id)
            ->set('currentRole', 'editor')
            ->call('updateRole')
            ->assertHasNoErrors()
            ->assertSet('currentlyManagingRole', false);

        $this->assertTrue($adminMember->fresh()->hasTeamRole($team, 'editor'));
        $this->assertTrue($adminMember->fresh()->hasRole('editor'));
    }

    public function test_manage_role_save_promotes_linked_client_and_unlinks_contact(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $clientUser = User::factory()->create(['name' => 'Portal Client User']);
        $clientUser->assignRole('client');
        $team->users()->attach($clientUser, ['role' => 'client']);
        $contact = $this->createLinkedClientContact($team, $owner, $clientUser);

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $team])
            ->set('roleFilter', 'client')
            ->call('manageRole', $clientUser->id)
            ->set('currentRole', 'marketing')
            ->call('updateRole')
            ->assertHasNoErrors()
            ->assertSet('currentlyManagingRole', false);

        $this->assertSame(
            'marketing',
            $team->fresh()->users()->where('users.id', $clientUser->id)->first()?->membership?->role,
        );
        $this->assertTrue($clientUser->fresh()->hasRole('marketing'));
        $this->assertNull($contact->fresh()->user_id);
    }

    public function test_sync_from_remaining_memberships_switches_current_team_after_removal(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $primaryTeam = $owner->currentTeam;
        $secondaryTeam = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);

        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $primaryTeam->users()->attach($member, ['role' => 'collaborator']);
        $secondaryTeam->users()->attach($member, ['role' => 'editor']);
        $member->forceFill(['current_team_id' => $primaryTeam->id])->save();

        app(RemoveTeamMember::class)->remove($owner, $primaryTeam, $member->fresh());

        $member->refresh();

        $this->assertSame($secondaryTeam->id, $member->current_team_id);
        $this->assertTrue($member->hasRole('editor'));
        $this->assertFalse($member->hasRole('collaborator'));
    }
}
