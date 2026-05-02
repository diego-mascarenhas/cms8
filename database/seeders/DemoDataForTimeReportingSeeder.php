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
use Illuminate\Support\Facades\Hash;

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
        $team = Team::query()
            ->where('name', 'Demo')
            ->first()
            ?? Team::where('personal_team', false)->orderBy('id')->first();
        if (! $team)
        {
            $this->command->warn('No hay equipo. Ejecuta antes DatabaseSeeder o crea un team.');

            return;
        }

        $user = $team->users()->first() ?? User::first();
        if (! $user)
        {
            $this->command->warn('No hay usuario en el equipo.');

            return;
        }

        // --- ~10 usuarios demo para el equipo: admin, colaboradores, empleados ---
        $demoPassword = Hash::make('password');
        $demoUsers = [
            ['name' => 'Demo Admin One', 'email' => 'demo-admin1@humano.test', 'role' => 'admin'],
            ['name' => 'Demo Admin Two', 'email' => 'demo-admin2@humano.test', 'role' => 'admin'],
            ['name' => 'Carmen Colaboradora', 'email' => 'demo-collab1@humano.test', 'role' => 'collaborator'],
            ['name' => 'Pablo Colaborador', 'email' => 'demo-collab2@humano.test', 'role' => 'collaborator'],
            ['name' => 'Laura Colaboradora', 'email' => 'demo-collab3@humano.test', 'role' => 'collaborator'],
            ['name' => 'Miguel Colaborador', 'email' => 'demo-collab4@humano.test', 'role' => 'collaborator'],
            ['name' => 'Sofia Empleada', 'email' => 'demo-employee1@humano.test', 'role' => 'employee'],
            ['name' => 'Diego Empleado', 'email' => 'demo-employee2@humano.test', 'role' => 'employee'],
            ['name' => 'Elena Empleada', 'email' => 'demo-employee3@humano.test', 'role' => 'employee'],
            ['name' => 'Roberto Empleado', 'email' => 'demo-employee4@humano.test', 'role' => 'employee'],
        ];
        foreach ($demoUsers as $demo)
        {
            $newUser = User::firstOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => $demoPassword,
                    'email_verified_at' => now(),
                    'current_team_id' => $team->id,
                ],
            );
            if (! $newUser->hasRole($demo['role']))
            {
                $newUser->assignRole($demo['role']);
            }
            if (! $newUser->teams()->where('team_id', $team->id)->exists())
            {
                $newUser->teams()->attach($team->id, ['role' => $demo['role']]);
            }
            $this->command->info("Usuario demo: {$newUser->name} ({$demo['role']}) — {$newUser->email}");
        }
        $this->command->info('');

        $projectsModule = Module::where('key', 'projects')->first();
        $tasksModule = Module::where('key', 'tasks')->first();
        if (! $projectsModule || ! $tasksModule)
        {
            $this->command->warn('Módulos projects/tasks no encontrados. Ejecuta ModuleSeeder y HostingProjectAndTaskCategoriesSeeder.');

            return;
        }

        $projectCategory = Category::where('module_id', $projectsModule->id)->first();
        $taskCategory = Category::where('module_id', $tasksModule->id)->first();
        if (! $projectCategory || ! $taskCategory)
        {
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
        foreach ($clients as $c)
        {
            $ent = Enterprise::withoutGlobalScope('team')->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'code' => $c['code'],
                ],
                [
                    'name' => $c['name'],
                    'type_id' => 1,
                    'status_id' => 1,
                    'email' => strtolower($c['code']).'@example.com',
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

        foreach ($projectsData as $pData)
        {
            $board = TaskBoard::withoutGlobalScope('team')->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => $pData['name'].' (board)',
                ],
                [
                    'description' => 'Tablero del proyecto '.$pData['name'],
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

            // Varias tareas por proyecto (más de dos para pruebas y API)
            $taskTitles = [
                'Desarrollo backend',
                'Revisión y testing',
                'Diseño de interfaz',
                'Integración API',
                'Documentación técnica',
                'Despliegue y configuración',
            ];
            foreach ($taskTitles as $i => $title)
            {
                $task = Task::withoutGlobalScope('team')->firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'board_id' => $board->id,
                        'title' => $title.' — '.$project->name,
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

        // --- Proyecto demo desde DB (Sistema Formularios, project/10) ---
        $demoBoard = TaskBoard::withoutGlobalScope('team')->firstOrCreate(
            [
                'team_id' => $team->id,
                'name' => 'Project: Sistema Formularios',
            ],
            [
                'description' => 'Task board for project: Sistema Formularios',
                'is_default' => false,
                'order' => 0,
            ],
        );

        $demoProjectCategory = Category::where('module_id', $projectsModule->id)->where('name', 'Desarrollo web')->first() ?? $projectCategory;

        $demoProjectData = [
            'budget_given' => "Presupuesto recibido:\nSistema de formularios y encuestas en Laravel 11 con Vuexy. Módulo de encuestas, diseño de base de datos (formularios, campos, respuestas), vistas y componentes UI.",
            'ai_interpretation' => 'Desarrollo de un sistema de formularios y encuestas. Alcance: análisis funcional, modelo de datos, UI en Vuexy (formularios, histórico, emails), implementación backend y frontend.',
            'dimension' => 'Proyecto mediano: un módulo principal de encuestas, CRUD de formularios y respuestas, vistas de historial y notificaciones por email. Stack Laravel 11 + Vuexy.',
            'estimated_times' => 'Fase 1 análisis y diseño (1-2 semanas). Fase 2 desarrollo backend y base de datos (2-3 semanas). Fase 3 UI y integración (2 semanas). Pruebas y despliegue (1 semana).',
            'resources' => '1 desarrollador Senior full-stack (Laravel + Vuexy). Entorno local y staging. Base de datos MySQL.',
            'suggested_tasks' => [
                ['title' => 'Análisis funcional y definición de flujos del módulo de encuestas', 'category_name' => 'Análisis', 'estimated_hours' => 6, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Diseño de base de datos: formularios, campos, respuestas y relaciones', 'category_name' => 'Diseño', 'estimated_hours' => 8, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Diseño de vistas y componentes UI en Vuexy (formularios, histórico, emails)', 'category_name' => 'Diseño', 'estimated_hours' => 8, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Implementación modelos y migraciones Laravel (formularios, campos, respuestas)', 'category_name' => 'Desarrollo web', 'estimated_hours' => 12, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'API REST y controladores para formularios y respuestas', 'category_name' => 'Desarrollo web', 'estimated_hours' => 10, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Vistas Blade/Vuexy: listado y edición de formularios', 'category_name' => 'Desarrollo web', 'estimated_hours' => 10, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Componentes de formulario dinámico (campos configurables)', 'category_name' => 'Desarrollo web', 'estimated_hours' => 14, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Flujo de respuestas y almacenamiento en BD', 'category_name' => 'Desarrollo web', 'estimated_hours' => 8, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Vista de histórico de respuestas y exportación', 'category_name' => 'Desarrollo web', 'estimated_hours' => 6, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Integración envío de emails (notificaciones, resúmenes)', 'category_name' => 'Desarrollo web', 'estimated_hours' => 6, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Validaciones y permisos por rol', 'category_name' => 'Desarrollo web', 'estimated_hours' => 6, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Tests automatizados (feature y unit)', 'category_name' => 'Desarrollo web', 'estimated_hours' => 8, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Documentación técnica y manual de usuario', 'category_name' => 'Documentación', 'estimated_hours' => 4, 'resource_level' => 'Junior', 'unit_price' => 45],
                ['title' => 'Despliegue en staging y configuración de entorno', 'category_name' => 'Desarrollo web', 'estimated_hours' => 4, 'resource_level' => 'Senior', 'unit_price' => 65],
                ['title' => 'Revisión final y entrega', 'category_name' => 'Análisis', 'estimated_hours' => 2, 'resource_level' => 'Senior', 'unit_price' => 65],
            ],
            'budget_preview_token' => \Illuminate\Support\Str::random(48),
        ];

        $demoProject = Project::withoutGlobalScope('team')->withoutGlobalScope('ownership')->firstOrCreate(
            [
                'team_id' => $team->id,
                'board_id' => $demoBoard->id,
            ],
            [
                'enterprise_id' => $enterprises[2]->id, // Agencia Digital (AGD)
                'category_id' => $demoProjectCategory->id,
                'name' => 'Sistema Formularios',
                'real_name' => 'Formularios',
                'description' => "El cliente tiene un sistema desarrollado recientemente por nosotros por lo que las tareas de auditoría y consultoría no deben contemplarse.\nEl sistema está realizado en Laravel 11 con Vuexy.",
                'status_id' => 1,
                'responsible_id' => $user->id,
                'date_start' => null,
                'date_end' => null,
                'data' => $demoProjectData,
            ],
        );

        $demoProject->update(['data' => $demoProjectData]);

        $this->command->info("Proyecto demo (desde DB): {$demoProject->name} (ID: {$demoProject->id}, board: {$demoBoard->id})");

        $demoTaskCategoryAnalisis = Category::where('module_id', $tasksModule->id)->where('name', 'Análisis')->first() ?? $taskCategory;
        $demoTaskCategoryDiseno = Category::where('module_id', $tasksModule->id)->where('name', 'Diseño')->first() ?? $taskCategory;

        $demoTasksData = [
            [
                'title' => 'Análisis de requisitos y diseño del modelo de datos',
                'description' => null,
                'estimated_hours' => 6,
                'category' => $demoTaskCategoryAnalisis,
            ],
            [
                'title' => 'Diseño de vistas y componentes UI en Vuexy (formularios, histórico, emails)',
                'description' => null,
                'estimated_hours' => 8,
                'category' => $demoTaskCategoryDiseno,
            ],
        ];

        foreach ($demoTasksData as $i => $taskData)
        {
            $task = Task::withoutGlobalScope('team')->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'board_id' => $demoBoard->id,
                    'title' => $taskData['title'],
                ],
                [
                    'category_id' => $taskData['category']->id,
                    'responsible_id' => $user->id,
                    'description' => $taskData['description'],
                    'estimated_hours' => $taskData['estimated_hours'],
                    'status_id' => $statusToDo,
                    'order' => $i + 1,
                    'start_date' => now(),
                    'due_date' => now(),
                ],
            );
            $this->command->info("  Tarea: {$task->title} (ID: {$task->id})");
        }

        $this->command->info('');
        $this->command->info("Proyecto demo: https://humano.test/project/{$demoProject->id} — Usa esta URL para demostraciones.");
        $this->command->info('Listo. Puedes usar https://humano.test/project/list y la API /api/time (start/stop o store) para pruebas.');
    }
}
