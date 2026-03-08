<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Database\Seeder;

class BasicCategoriesForAllModulesSeeder extends Seeder
{
    /**
     * Create at least one basic (global) category per module so every module
     * has a category available for use. Skips modules that already have categories.
     */
    public function run(): void
    {
        $this->command->info('Creating basic categories for all modules...');

        $modules = Module::orderBy('name')->get();
        $created = 0;

        foreach ($modules as $module)
        {
            $hasCategory = Category::where('module_id', $module->id)
                ->whereNull('team_id')
                ->where('status', 1)
                ->exists();

            if ($hasCategory)
            {
                continue;
            }

            Category::firstOrCreate(
                [
                    'module_id' => $module->id,
                    'parent_id' => null,
                    'team_id' => null,
                ],
                [
                    'name' => $module->name,
                    'description' => 'Default category for this module',
                    'order' => 0,
                    'status' => true,
                ],
            );
            $created++;
            $this->command->line("   • {$module->name} ({$module->key})");
        }

        $this->command->info("Basic categories created for {$created} module(s).");
    }
}
