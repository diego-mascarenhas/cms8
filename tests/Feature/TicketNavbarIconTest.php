<?php

namespace Tests\Feature;

use App\Livewire\TicketNavbarIcon;
use App\Models\Module;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketNavbarIconTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTicketsModule(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Module::firstOrCreate(
            ['key' => 'tickets'],
            ['name' => 'Tickets', 'is_core' => false],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->enableModule('tickets');

        return $user;
    }

    public function test_navbar_shows_open_ticket_count_badge(): void
    {
        $user = $this->userWithTicketsModule();
        $team = $user->currentTeam;

        Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);
        Ticket::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);
        Ticket::factory()->closed()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(TicketNavbarIcon::class)
            ->assertSet('openCount', 2)
            ->assertSee('badge-notifications', false)
            ->assertSee('2', false);
    }

    public function test_navbar_hides_badge_when_there_are_no_open_tickets(): void
    {
        $user = $this->userWithTicketsModule();
        $team = $user->currentTeam;

        Ticket::factory()->closed()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(TicketNavbarIcon::class)
            ->assertSet('openCount', 0)
            ->assertDontSee('badge-notifications', false);
    }

    public function test_navbar_ticket_icon_appears_on_dashboard_when_tickets_module_enabled(): void
    {
        $user = $this->userWithTicketsModule();
        $team = $user->currentTeam;

        Ticket::factory()->open()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeLivewire(TicketNavbarIcon::class);
        $response->assertSee('badge-notifications', false);
    }
}
