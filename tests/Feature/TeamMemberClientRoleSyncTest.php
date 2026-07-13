<?php

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamMemberRole;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Team;
use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamMemberClientRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'client', 'collaborator', 'editor'] as $roleName)
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

    public function test_cannot_add_linked_contact_user_as_collaborator(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $clientUser = User::factory()->create(['email' => 'portal-client@example.com']);
        $this->createLinkedClientContact($team, $owner, $clientUser);

        try
        {
            app(AddTeamMember::class)->add($owner, $team, $clientUser->email, 'collaborator');
            $this->fail('Expected validation exception when adding linked client as collaborator.');
        } catch (ValidationException $exception)
        {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertFalse($team->fresh()->hasUserWithEmail($clientUser->email));
    }

    public function test_cannot_change_linked_contact_user_role_to_collaborator(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $clientUser = User::factory()->create();
        $clientUser->assignRole('client');
        $team->users()->attach($clientUser, ['role' => 'client']);
        $this->createLinkedClientContact($team, $owner, $clientUser);

        try
        {
            app(UpdateTeamMemberRole::class)->update($owner, $team, $clientUser->id, 'collaborator');
            $this->fail('Expected validation exception when changing linked client role.');
        } catch (ValidationException $exception)
        {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $this->assertTrue($clientUser->fresh()->hasTeamRole($team, 'client'));
        $this->assertTrue($clientUser->fresh()->hasRole('client'));
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
