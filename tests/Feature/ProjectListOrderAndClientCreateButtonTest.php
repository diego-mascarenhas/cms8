<?php

namespace Tests\Feature;

use App\DataTables\ProjectDataTable;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectListOrderAndClientCreateButtonTest extends TestCase
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

    public function test_project_list_defaults_to_newest_first(): void
    {
        $dataTable = app(ProjectDataTable::class);
        $order = $dataTable->html()->getOptions()['order'] ?? null;

        $this->assertSame([[0, 'desc']], $order);
    }

    public function test_client_show_has_create_project_button_and_lists_newest_projects_first(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Proyectos',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $olderProject = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Proyecto Antiguo',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $newerProject = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Proyecto Nuevo',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $this->assertGreaterThan($olderProject->id, $newerProject->id);

        $response = $this->actingAs($user)->get(route('client.show', $client->id));

        $response->assertOk();
        $response->assertSee(route('project.create').'?enterprise_id='.$client->id, false);
        $response->assertSee('Ingresar proyecto', false);
        $response->assertDontSee(__('Create').' '.__('Project'), false);
        $response->assertSeeInOrder([
            'Proyecto Nuevo',
            'Proyecto Antiguo',
        ]);
    }

    public function test_project_create_preselects_enterprise_from_query_string(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Prefill',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('project.create', [
            'enterprise_id' => $client->id,
        ]));

        $response->assertOk();
        $response->assertSee('option value="'.$client->id.'" selected', false);
        $response->assertSee('Cliente Prefill', false);
    }
}
