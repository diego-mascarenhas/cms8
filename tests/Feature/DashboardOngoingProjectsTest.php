<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\TaskStatusSeeder;
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

    public function test_dashboard_ongoing_projects_show_responsible_due_hours_and_task_counts(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
            TaskStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create(['name' => 'Ana Responsable']);
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
            'name' => 'Cliente Métricas',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Board métricas',
        ]);

        $dueDate = now()->addDays(3)->startOfDay();

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $board->id,
            'name' => 'Proyecto Con Métricas',
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
            'date_end' => $dueDate,
        ]);

        $todoStatusId = TaskStatus::query()->where('name', 'TO_DO')->value('id')
            ?? TaskStatus::query()->where('name', '!=', 'DONE')->value('id');
        $doneStatusId = TaskStatus::query()->where('name', 'DONE')->value('id');

        $openTask = Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'Tarea abierta',
            'responsible_id' => $user->id,
            'status_id' => $todoStatusId,
            'estimated_hours' => 4,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'Tarea hecha',
            'responsible_id' => $user->id,
            'status_id' => $doneStatusId,
            'estimated_hours' => 2,
            'start_date' => now()->subDay()->toDateString(),
            'due_date' => now()->toDateString(),
        ]);

        Time::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'task_id' => $openTask->id,
            'description' => 'Trabajo dashboard',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'duration_seconds' => 3600,
            'is_billable' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Proyecto Con Métricas', false);
        $response->assertSee('Ana Responsable', false);
        $response->assertSee('Cliente Métricas', false);
        $response->assertSee($dueDate->format('d/m/Y'), false);
        $response->assertSee('/ 2', false);
        $response->assertSee(__('Responsible'), false);
        $response->assertSee(__('Completion'), false);
    }
}
