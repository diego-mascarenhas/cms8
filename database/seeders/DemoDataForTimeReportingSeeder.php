<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Categorías, clientes (enterprises) y proyectos/tareas de ejemplo para probar
 * el reporte de tiempo y la API de Humano (time/store, fichajes).
 *
 * Ejecutar: php artisan db:seed --class=DemoDataForTimeReportingSeeder
 */
class DemoDataForTimeReportingSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::where('personal_team', false)->first();
        if (! $team) {
            $this->command->warn('No hay equipo. Ejecuta antes DatabaseSeeder o crea un team.');

            return;
        }

        $user = $team->users()->first() ?? User::first();
        if (! $user) {
            $this->command->warn('No hay usuario en el equipo.');

            return;
        }

        $projectsModule = Module::where('key', 'projects')->first();
        $tasksModule = Module::where('key', 'tasks')->first();
        if (! $projectsModule || ! $tasksModule) {
            $this->command->warn('Módulos projects/tasks no encontrados. Ejecuta ModuleSeeder y HostingProjectAndTaskCategoriesSeeder.');

            return;
        }

        $projectCategory = Category::where('module_id', $projectsModule->id)->first();
        $taskCategory = Category::where('module_id', $tasksModule->id)->first();
        if (! $projectCategory || ! $taskCategory) {
            $this->command->warn('Categorías de proyecto/tarea no encontradas. Ejecuta HostingProjectAndTaskCategoriesSeeder.');

            return;
        }

        $statusInProgress = 9;  // IN_PROGRESS
        $statusToDo = 1;       // TO_DO (TaskStatus order 1)

        // --- Clientes (Enterprises type_id = 1) ---
        $clients = [
            ['name' => 'Acme Corp', 'code' => 'ACME'],
            ['name' => 'Startup Beta', 'code' => 'BETA'],
            ['name' => 'Agencia Digital', 'code' => 'AGD'],
        ];

        $enterprises = [];
        foreach ($clients as $c) {
            $ent = Enterprise::withoutGlobalScope('team')->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'code' => $c['code'],
                ],
                [
                    'name' => $c['name'],
                    'type_id' => 1,
                    'status_id' => 1,
                    'email' => strtolower($c['code']) . '@example.com',
                ],
            );
            $enterprises[] = $ent;
            $this->command->info("Cliente: {$ent->name} (ID: {$ent->id})");
        }

        // --- Proyectos con board y tareas ---
        $projectsData = [
            ['name' => 'Sitio web Acme', 'client' => $enterprises[0]],
            ['name' => 'App móvil Beta', 'client' => $enterprises[1]],
            ['name' => 'Consultoría técnica', 'client' => $enterprises[2]],
        ];

        foreach ($projectsData as $pData) {
            $board = TaskBoard::withoutGlobalScope('team')->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => $pData['name'] . ' (board)',
                ],
                [
                    'description' => 'Tablero del proyecto ' . $pData['name'],
                    'is_default' => false,
                    'order' => 0,
                ],
            );

            $project = Project::withoutGlobalScope('team')->withoutGlobalScope('ownership')->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'board_id' => $board->id,
                ],
                [
                    'enterprise_id' => $pData['client']->id,
                    'category_id' => $projectCategory->id,
                    'name' => $pData['name'],
                    'real_name' => $pData['name'],
                    'description' => 'Proyecto de ejemplo para pruebas de tiempo.',
                    'status_id' => $statusInProgress,
                    'responsible_id' => $user->id,
                    'date_start' => now(),
                    'date_end' => now()->addMonths(2),
                ],
            );

            $this->command->info("Proyecto: {$project->name} (ID: {$project->id}, board: {$board->id})");

            // 2 tareas por proyecto
            $taskTitles = [
                'Desarrollo backend',
                'Revisión y testing',
            ];
            foreach ($taskTitles as $i => $title) {
                $task = Task::withoutGlobalScope('team')->firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'board_id' => $board->id,
                        'title' => $title . ' — ' . $project->name,
                    ],
                    [
                        'category_id' => $taskCategory->id,
                        'responsible_id' => $user->id,
                        'description' => 'Tarea de ejemplo para fichar tiempo.',
                        'estimated_hours' => 5,
                        'status_id' => $statusToDo,
                        'order' => $i + 1,
                        'start_date' => now(),
                        'due_date' => now()->addWeeks(2),
                    ],
                );
                $this->command->info("  Tarea: {$task->title} (ID: {$task->id})");
            }
        }

        $this->command->info('');
        $this->command->info('Listo. Puedes usar https://humano.test/project/list y la API /api/time (start/stop o store) para pruebas.');
    }
}
