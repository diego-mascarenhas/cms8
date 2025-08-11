<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏷️ Creating product categories...');

        $categories = [
            [
                'name' => 'Productos',
                'module_key' => 'products',
                'description' => 'Categoría para productos importados desde CMS7',
                'teams' => [1], // Add to these teams
            ],
            [
                'name' => 'E-commerce',
                'module_key' => 'ecommerce',
                'description' => 'Categoría para productos de e-commerce',
                'teams' => [1],
            ],
            [
                'name' => 'Hosting y Dominios',
                'module_key' => 'products',
                'description' => 'Servicios de hosting web y registro de dominios',
                'teams' => [1],
            ],
            [
                'name' => 'Desarrollo Web',
                'module_key' => 'products',
                'description' => 'Servicios de desarrollo de sitios web y aplicaciones',
                'teams' => [1],
            ],
            [
                'name' => 'Soporte Técnico',
                'module_key' => 'products',
                'description' => 'Servicios de soporte técnico y mantenimiento',
                'teams' => [1],
            ],
            [
                'name' => 'Consultoría IT',
                'module_key' => 'products',
                'description' => 'Servicios de consultoría en tecnologías de la información',
                'teams' => [1],
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($categories as $categoryData) {
            foreach ($categoryData['teams'] as $teamId) {
                $existing = Category::where('team_id', $teamId)
                    ->where('name', $categoryData['name'])
                    ->first();

                if ($existing) {
                    // Update existing category
                    $existing->update([
                        'description' => $categoryData['description'],
                        'status' => true,
                    ]);
                    $updated++;
                    $this->command->info("🔄 Updated category: {$categoryData['name']} for Team {$teamId}");
                } else {
                    // Create new category
                    Category::create([
                        'team_id' => $teamId,
                        'name' => $categoryData['name'],
                        'description' => $categoryData['description'],
                        'module_id' => null, // We'll handle modules separately if needed
                        'status' => true,
                        'order' => null,
                        'parent_id' => null,
                        'data' => json_encode(['module_key' => $categoryData['module_key']]),
                    ]);
                    $created++;
                    $this->command->info("✅ Created category: {$categoryData['name']} for Team {$teamId}");
                }
            }
        }

        $this->command->info('📊 Product categories summary:');
        $this->command->info("   - Categories created: {$created}");
        $this->command->info("   - Categories updated: {$updated}");
        $this->command->info('✅ Product categories seeding completed successfully!');
    }
}
