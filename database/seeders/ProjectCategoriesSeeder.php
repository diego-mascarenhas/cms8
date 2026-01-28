<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ProjectCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating project categories: Sitios webs, Apps, Diseño...');

        // Find projects module
        $projectsModule = Module::where('key', 'projects')->first();

        if (! $projectsModule)
        {
            $this->command->warn('⚠️  Projects module not found. Skipping project categories creation.');

            return;
        }

        // Get or create parent category for project types
        $projectTypesParent = Category::firstOrCreate(
            [
                'name' => 'Project Types',
                'module_id' => $projectsModule->id,
                'team_id' => 1,
            ],
            [
                'description' => 'Types of projects',
                'status' => 1,
            ],
        );

        // Create the three main categories
        $categories = [
            [
                'name' => 'Sitios webs',
                'description' => 'Proyectos de desarrollo de sitios web',
            ],
            [
                'name' => 'Apps',
                'description' => 'Proyectos de desarrollo de aplicaciones móviles y web',
            ],
            [
                'name' => 'Diseño',
                'description' => 'Proyectos de diseño gráfico y UX/UI',
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($categories as $categoryData)
        {
            $category = Category::firstOrCreate(
                [
                    'name' => $categoryData['name'],
                    'module_id' => $projectsModule->id,
                    'team_id' => 1,
                ],
                [
                    'parent_id' => $projectTypesParent->id,
                    'description' => $categoryData['description'],
                    'status' => 1,
                ],
            );

            if ($category->wasRecentlyCreated)
            {
                $created++;
                $this->command->info("✅ Created category: {$categoryData['name']}");
            } else
            {
                $updated++;
                $this->command->info("ℹ️  Category already exists: {$categoryData['name']}");
            }
        }

        $this->command->info("✅ Project categories seeder completed. Created: {$created}, Already existed: {$updated}");
    }
}
