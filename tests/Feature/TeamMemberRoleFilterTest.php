<?php

namespace Tests\Feature;

use App\Livewire\Teams\TeamMemberManager;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamStripeCustomerService;
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

    public function test_member_list_shows_the_team_they_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->withPersonalTeam()->create(['name' => 'Diego Propio']);
        $member->ownedTeams()->first()->forceFill(['name' => 'Equipo Diego'])->save();
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->assertSee('Equipo Diego')
            ->assertDontSee('confirmCreateMemberTeam');
    }

    public function test_owner_can_create_a_team_for_a_member_without_switching_their_current_team(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create([
            'name' => 'Leticia Silvano',
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->assertSee(__('Create Team'))
            ->assertSee('ti ti-plus', false)
            ->call('confirmCreateMemberTeam', $member->id)
            ->assertSet('confirmingMemberTeam', true)
            ->set('newTeamName', 'Equipo Leticia')
            ->call('createMemberTeam')
            ->assertSet('confirmingMemberTeam', false)
            ->assertSee('Equipo Leticia');

        $owned = $member->ownedTeams()->first();

        $this->assertInstanceOf(Team::class, $owned);
        $this->assertSame('Equipo Leticia', $owned->name);
        $this->assertTrue($owned->personal_team);
        $this->assertSame($owner->currentTeam->id, $member->fresh()->current_team_id);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $owned->id,
            'user_id' => $member->id,
            'role' => 'admin',
        ]);
        $this->assertNull($owned->stripe_id);
    }

    public function test_creating_a_member_team_links_an_existing_stripe_customer_by_email(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create([
            'name' => 'Leticia Silvano',
            'email' => 'leticia@example.com',
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);

        $this->mock(TeamStripeCustomerService::class, function ($mock): void
        {
            $mock->shouldReceive('findCustomerIdByEmail')
                ->once()
                ->with('leticia@example.com')
                ->andReturn('cus_existing_leticia');
            $mock->shouldNotReceive('createStripeCustomer');
        });

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->call('confirmCreateMemberTeam', $member->id)
            ->set('newTeamName', 'Equipo Leticia')
            ->set('createInStripe', true)
            ->call('createMemberTeam')
            ->assertHasNoErrors()
            ->assertSet('confirmingMemberTeam', false);

        $this->assertSame('cus_existing_leticia', $member->ownedTeams()->first()?->stripe_id);
    }

    public function test_creating_a_member_team_creates_a_stripe_customer_when_the_email_is_new(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create([
            'name' => 'Leticia Silvano',
            'email' => 'leticia@example.com',
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);

        $this->mock(TeamStripeCustomerService::class, function ($mock): void
        {
            $mock->shouldReceive('findCustomerIdByEmail')
                ->once()
                ->with('leticia@example.com')
                ->andReturn(null);
            $mock->shouldReceive('createStripeCustomer')
                ->once()
                ->andReturnUsing(function (Team $team): string
                {
                    $team->forceFill(['stripe_id' => 'cus_new_leticia'])->save();

                    return 'cus_new_leticia';
                });
        });

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->call('confirmCreateMemberTeam', $member->id)
            ->set('createInStripe', true)
            ->call('createMemberTeam')
            ->assertHasNoErrors();

        $this->assertSame('cus_new_leticia', $member->ownedTeams()->first()?->stripe_id);
    }

    public function test_stripe_customer_already_used_by_another_team_does_not_create_a_local_team(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create([
            'email' => 'leticia@example.com',
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);
        Team::factory()->create([
            'user_id' => User::factory()->create()->id,
            'stripe_id' => 'cus_taken',
        ]);

        $this->mock(TeamStripeCustomerService::class, function ($mock): void
        {
            $mock->shouldReceive('findCustomerIdByEmail')->once()->andReturn('cus_taken');
            $mock->shouldNotReceive('createStripeCustomer');
        });

        $this->actingAs($owner);

        Livewire::test(TeamMemberManager::class, ['team' => $owner->currentTeam])
            ->call('confirmCreateMemberTeam', $member->id)
            ->set('createInStripe', true)
            ->call('createMemberTeam')
            ->assertHasErrors('createInStripe')
            ->assertSet('confirmingMemberTeam', true);

        $this->assertNull($member->ownedTeams()->first());
    }
}
