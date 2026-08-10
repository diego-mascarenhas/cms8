<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardOngoingProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_ongoing_projects_include_approved_and_waiting_for_response(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'projects'],
            [
                'name' => 'Projects',
                'icon' => 'folder',
                'description' => 'Projects module',
                'status' => 1,
            ],
        );
        $team->enableModule('projects');

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Dashboard',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Proyecto Aprobado',
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_APPROVED,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Proyecto Esperando',
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_WAITING_FOR_RESPONSE,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Proyecto Finalizado',
            'responsible_id' => $user->id,
            'status_id' => 10,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('Ongoing Projects'), false);
        $response->assertSee('Proyecto Aprobado', false);
        $response->assertSee('Proyecto Esperando', false);
        $response->assertDontSee('Proyecto Finalizado', false);
    }
}
