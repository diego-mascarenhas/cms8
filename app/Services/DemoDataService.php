<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;

/**
 * Demo data service — used by the complementary Demo seed and humano:start --demo.
 * Creates demo clients (REVISION ALPHA, IDONEO) and their projects for testing.
 * Not part of the main deploy seed; run separately: php artisan db:seed --class=DemoSeeder
 */
class DemoDataService
{
    /**
     * Create demo clients (REVISION ALPHA, IDONEO) and their projects for a given team.
     *
     * @param  object|null  $output  Optional object with info() and warn() (e.g. Artisan command or seeder dummy)
     */
    public static function createClientsAndProjects(int $teamId, ?object $output = null): void
    {
        $info = fn ($m) => $output && method_exists($output, 'info') ? $output->info($m) : null;
        $warn = fn ($m) => $output && method_exists($output, 'warn') ? $output->warn($m) : null;

        $projectsModule = Module::where('key', 'projects')->first();
        if (! $projectsModule)
        {
            if ($warn)
            {
                $warn('⚠️  Projects module not found. Skipping demo clients.');
            }

            return;
        }

        $projectCategory = Category::firstOrCreate(
            ['name' => 'General', 'module_id' => $projectsModule->id, 'team_id' => $teamId],
            ['description' => 'Proyectos generales', 'status' => 1],
        );

        $tasksModule = Module::where('key', 'tasks')->first();
        $taskCategories = collect();
        if ($tasksModule)
        {
            static::ensureTaskCategoriesForTeam($tasksModule->id, $teamId, $info);
            $taskCategories = Category::where('module_id', $tasksModule->id)->where('team_id', $teamId)->get();
        }

        $firstUserId = User::whereHas('teams', fn ($q) => $q->where('team_id', $teamId))->value('id')
            ?? User::withoutGlobalScopes()->value('id');
        $projectStatusId = 9; // IN_PROGRESS
        $taskStatusIds = \App\Models\TaskStatus::pluck('id')->all();
        if (empty($taskStatusIds))
        {
            $taskStatusIds = [1];
        }

        $clients = [
            [
                'name' => 'REVISION ALPHA',
                'email' => 'info@revisionalpha.es',
                'projects' => [
                    ['name' => 'Web Corporativa REVISION ALPHA', 'real_name' => 'Corporate Website', 'description' => 'Sitio web institucional y presencia digital'],
                    ['name' => 'Consultoría y Procesos', 'real_name' => 'Consulting', 'description' => 'Consultoría de procesos y mejora continua'],
                    ['name' => 'Formación Interna', 'real_name' => 'Internal Training', 'description' => 'Plataforma de formación para el equipo'],
                ],
            ],
            [
                'name' => 'IDONEO',
                'email' => 'hola@idoneo.dev',
                'projects' => [
                    ['name' => 'Portal IDONEO', 'real_name' => 'Idoneo Portal', 'description' => 'Desarrollo del portal y servicios Idoneo'],
                    ['name' => 'Integraciones y API', 'real_name' => 'Integrations', 'description' => 'Integraciones y API para clientes'],
                    ['name' => 'Soporte y Mantenimiento', 'real_name' => 'Support', 'description' => 'Soporte técnico y mantenimiento evolutivo'],
                ],
            ],
        ];

        foreach ($clients as $clientData)
        {
            $enterprise = Enterprise::withoutGlobalScopes()->updateOrCreate(
                ['email' => $clientData['email'], 'team_id' => $teamId],
                [
                    'name' => $clientData['name'],
                    'code' => strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $clientData['name']), 0, 12)),
                    'type_id' => 1,
                    'status_id' => 2,
                ],
            );
            if ($info)
            {
                $info("✅ Client: {$enterprise->name} ({$enterprise->email})");
            }

            foreach ($clientData['projects'] as $projectData)
            {
                $project = Project::withoutGlobalScopes()->firstOrCreate(
                    ['name' => $projectData['name'], 'team_id' => $teamId],
                    [
                        'real_name' => $projectData['real_name'],
                        'description' => $projectData['description'],
                        'enterprise_id' => $enterprise->id,
                        'category_id' => $projectCategory->id,
                        'status_id' => $projectStatusId,
                        'responsible_id' => $firstUserId,
                    ],
                );

                $board = TaskBoard::withoutGlobalScopes()->firstOrCreate(
                    ['name' => 'Board: '.$project->name, 'team_id' => $teamId],
                    [
                        'description' => 'Tablero de tareas para '.$project->name,
                        'is_default' => false,
                        'order' => 10,
                    ],
                );
                $project->update(['board_id' => $board->id]);

                $templates = ['Análisis', 'Diseño', 'Desarrollo', 'Revisión', 'Despliegue', 'Documentación'];
                $numTasks = rand(3, 6);
                for ($i = 0; $i < $numTasks; $i++)
                {
                    $title = $templates[$i % count($templates)].' - '.$project->name;
                    $category = $taskCategories->isNotEmpty() ? $taskCategories->random() : null;
                    Task::withoutGlobalScopes()->firstOrCreate(
                        ['title' => $title, 'board_id' => $board->id],
                        [
                            'description' => 'Tarea asociada a '.$project->name,
                            'category_id' => $category?->id,
                            'status_id' => $taskStatusIds[array_rand($taskStatusIds)],
                            'responsible_id' => $firstUserId,
                            'team_id' => $teamId,
                            'start_date' => now(),
                            'due_date' => now()->addDays(rand(7, 21)),
                            'order' => $i + 1,
                        ],
                    );
                }

                if ($info)
                {
                    $info("   └─ Project: {$project->name} ({$numTasks} tasks)");
                }
            }
        }

        if ($info)
        {
            $info('✅ Demo clients and projects created');
        }
    }

    /**
     * Ensure default task categories exist for the team so tasks can be created.
     */
    protected static function ensureTaskCategoriesForTeam(int $tasksModuleId, int $teamId, ?callable $info = null): void
    {
        $defaults = [
            ['name' => 'General', 'description' => 'Tareas generales'],
            ['name' => 'Desarrollo', 'description' => 'Desarrollo y programación'],
            ['name' => 'Diseño', 'description' => 'Diseño y maquetación'],
            ['name' => 'Revisión', 'description' => 'Revisión y QA'],
            ['name' => 'Documentación', 'description' => 'Documentación'],
            ['name' => 'Soporte', 'description' => 'Soporte y mantenimiento'],
        ];
        foreach ($defaults as $item)
        {
            Category::firstOrCreate(
                [
                    'name' => $item['name'],
                    'module_id' => $tasksModuleId,
                    'team_id' => $teamId,
                ],
                [
                    'description' => $item['description'],
                    'status' => 1,
                ],
            );
        }
        if ($info)
        {
            $info('✅ Task categories created');
        }
    }
}
