<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Project and task categories for a hosting company.
 * Safe to run multiple times (firstOrCreate). Use for deploy and testing.
 */
class HostingProjectAndTaskCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $projectsModule = Module::where('key', 'projects')->first();
        $tasksModule = Module::where('key', 'tasks')->first();

        // Global categories (team_id = null) so all teams see them in the dropdown
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

        $this->command->info('Seeding project categories (hosting) and task categories...');

        // Make any existing hosting categories global so all teams see them
        Category::where('module_id', $projectsModule->id)
            ->where('team_id', 1)
            ->whereIn('name', ['Servicios hosting', 'Migración de hosting', 'Alojamiento web', 'VPS / Servidor dedicado', 'Dominios y DNS', 'Email hosting', 'SSL y seguridad', 'Backup y recuperación', 'Monitoreo y soporte'])
            ->update(['team_id' => null]);
        Category::where('module_id', $tasksModule->id)
            ->where('team_id', 1)
            ->whereIn('name', ['Tipos de tarea', 'Setup', 'Migración', 'Configuración', 'Soporte', 'Monitoreo', 'Facturación', 'Documentación', 'Desarrollo', 'Revisión'])
            ->update(['team_id' => null]);

        // --- Project categories (hosting-related) ---
        $projectParent = Category::firstOrCreate(
            [
                'name' => 'Servicios hosting',
                'module_id' => $projectsModule->id,
                'team_id' => $teamId,
            ],
            [
                'description' => 'Categorías de proyectos para empresa de hosting',
                'parent_id' => null,
                'order' => 0,
                'status' => true,
            ],
        );

        $projectCategories = [
            ['name' => 'Migración de hosting', 'description' => 'Migración de sitios o servidores'],
            ['name' => 'Alojamiento web', 'description' => 'Configuración y gestión de hosting web'],
            ['name' => 'VPS / Servidor dedicado', 'description' => 'Provisión y administración de VPS o dedicados'],
            ['name' => 'Dominios y DNS', 'description' => 'Registro de dominios y gestión DNS'],
            ['name' => 'Email hosting', 'description' => 'Configuración de correo corporativo'],
            ['name' => 'SSL y seguridad', 'description' => 'Certificados SSL y hardening'],
            ['name' => 'Backup y recuperación', 'description' => 'Backups y planes de recuperación'],
            ['name' => 'Monitoreo y soporte', 'description' => 'Monitoreo 24/7 y soporte técnico'],
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

        $this->command->info('Project categories (hosting) created.');

        // --- Task categories (for task module testing) ---
        $taskParent = Category::firstOrCreate(
            [
                'name' => 'Tipos de tarea',
                'module_id' => $tasksModule->id,
                'team_id' => $teamId,
            ],
            [
                'description' => 'Categorías de tareas para organización',
                'parent_id' => null,
                'order' => 0,
                'status' => true,
            ],
        );

        $taskCategories = [
            ['name' => 'Setup', 'description' => 'Instalación y puesta en marcha'],
            ['name' => 'Migración', 'description' => 'Migración de datos o servicios'],
            ['name' => 'Configuración', 'description' => 'Ajustes y configuración'],
            ['name' => 'Soporte', 'description' => 'Atención a incidencias'],
            ['name' => 'Monitoreo', 'description' => 'Revisión y monitoreo'],
            ['name' => 'Facturación', 'description' => 'Tareas administrativas de facturación'],
            ['name' => 'Documentación', 'description' => 'Documentación técnica o de proceso'],
            ['name' => 'Desarrollo', 'description' => 'Desarrollo o scripting'],
            ['name' => 'Revisión', 'description' => 'Code review o revisión técnica'],
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
        $this->command->info('Done. You can run: php artisan db:seed --class=HostingProjectAndTaskCategoriesSeeder');
    }
}
