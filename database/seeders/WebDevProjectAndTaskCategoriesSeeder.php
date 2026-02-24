<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Project and task categories for a web development and infrastructure consulting company.
 * Safe to run multiple times (firstOrCreate). Global (team_id = null) so all teams see them.
 */
class WebDevProjectAndTaskCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $projectsModule = Module::where('key', 'projects')->first();
        $tasksModule = Module::where('key', 'tasks')->first();

        $teamId = null;

        if (! $projectsModule)
        {
            $this->command->warn('Projects module not found. Run ModuleSeeder first.');

            return;
        }

        if (! $tasksModule)
        {
            $this->command->warn('Tasks module not found. Run ModuleSeeder first.');

            return;
        }

        $this->command->info('Seeding project and task categories (web dev & infrastructure)...');

        // --- Project categories ---
        $projectParent = Category::firstOrCreate(
            [
                'name' => 'Servicios',
                'module_id' => $projectsModule->id,
                'team_id' => $teamId,
            ],
            [
                'description' => 'Tipos de proyecto para desarrollo web e infraestructura',
                'parent_id' => null,
                'order' => 0,
                'status' => true,
            ],
        );

        $projectCategories = [
            ['name' => 'Desarrollo web', 'description' => 'Sitios, aplicaciones web y portales'],
            ['name' => 'Consultoría técnica', 'description' => 'Auditorías, análisis y recomendaciones'],
            ['name' => 'Infraestructura y DevOps', 'description' => 'Servidores, CI/CD, contenedores y cloud'],
            ['name' => 'Mantenimiento y soporte', 'description' => 'Soporte técnico y actualizaciones'],
            ['name' => 'Integración de sistemas', 'description' => 'APIs, integraciones y migración de datos'],
            ['name' => 'Diseño UX/UI', 'description' => 'Diseño de interfaces y experiencia de usuario'],
            ['name' => 'Seguridad y cumplimiento', 'description' => 'Auditorías de seguridad y cumplimiento normativo'],
        ];

        foreach ($projectCategories as $index => $item)
        {
            Category::firstOrCreate(
                [
                    'name' => $item['name'],
                    'module_id' => $projectsModule->id,
                    'team_id' => $teamId,
                ],
                [
                    'parent_id' => $projectParent->id,
                    'description' => $item['description'],
                    'order' => $index + 1,
                    'status' => true,
                ],
            );
        }

        $this->command->info('Project categories created.');

        // --- Task categories ---
        $taskParent = Category::firstOrCreate(
            [
                'name' => 'Tipos de tarea',
                'module_id' => $tasksModule->id,
                'team_id' => $teamId,
            ],
            [
                'description' => 'Categorías de tareas para desarrollo y consultoría',
                'parent_id' => null,
                'order' => 0,
                'status' => true,
            ],
        );

        $taskCategories = [
            ['name' => 'Análisis', 'description' => 'Análisis de requisitos y viabilidad'],
            ['name' => 'Diseño', 'description' => 'Diseño técnico o de interfaz'],
            ['name' => 'Desarrollo frontend', 'description' => 'Desarrollo de interfaz y frontend'],
            ['name' => 'Desarrollo backend', 'description' => 'Desarrollo de lógica y APIs'],
            ['name' => 'Testing', 'description' => 'Pruebas y QA'],
            ['name' => 'Despliegue', 'description' => 'Despliegue y puesta en producción'],
            ['name' => 'Documentación', 'description' => 'Documentación técnica o funcional'],
            ['name' => 'Revisión', 'description' => 'Code review o revisión técnica'],
            ['name' => 'Soporte', 'description' => 'Atención a incidencias y soporte'],
            ['name' => 'Configuración', 'description' => 'Configuración de entornos o servicios'],
            ['name' => 'Migración', 'description' => 'Migración de datos o sistemas'],
        ];

        foreach ($taskCategories as $index => $item)
        {
            Category::firstOrCreate(
                [
                    'name' => $item['name'],
                    'module_id' => $tasksModule->id,
                    'team_id' => $teamId,
                ],
                [
                    'parent_id' => $taskParent->id,
                    'description' => $item['description'],
                    'order' => $index + 1,
                    'status' => true,
                ],
            );
        }

        $this->command->info('Task categories created.');
        $this->command->info('Done. Run: php artisan db:seed --class=WebDevProjectAndTaskCategoriesSeeder');
    }
}
