<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectListStatsCardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_project_list_shows_summary_cards_with_counts(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Stats',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Budget Project',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Budgeted Project',
            'responsible_id' => $user->id,
            'status_id' => 2,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'In Progress Project',
            'responsible_id' => $user->id,
            'status_id' => 9,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'To Invoice Project',
            'responsible_id' => $user->id,
            'status_id' => 11,
        ]);

        $stats = Project::getProjectStats((int) $team->id);

        $this->assertSame(4, $stats['totalProjects']);
        $this->assertSame(1, $stats['totalBudget']);
        $this->assertSame(1, $stats['totalBudgeted']);
        $this->assertSame(1, $stats['totalInProgress']);
        $this->assertSame(1, $stats['totalToInvoice']);
        $this->assertArrayNotHasKey('totalInvoiced', $stats);

        $response = $this->actingAs($user)->get(route('project-list'));

        $response->assertOk();
        $response->assertSeeInOrder([
            __('project_status.BUDGET'),
            __('project_status.BUDGETED'),
            __('project_status.IN_PROGRESS'),
            __('project_status.TO_INVOICE'),
        ]);
        $response->assertDontSee(__('project_status.INVOICED'), false);
        $response->assertSee('data-status="1"', false);
        $response->assertSee('data-status="2"', false);
        $response->assertSee('data-status="3,7,8,9"', false);
        $response->assertSee('data-status="10,11"', false);
        $response->assertDontSee('data-status="12"', false);
        $response->assertSee('id="project-table"', false);
        $response->assertSee('project-list-card', false);
    }
}
