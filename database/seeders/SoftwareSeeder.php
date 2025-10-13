<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use App\Models\Software;
use Illuminate\Database\Seeder;

class SoftwareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the software module
        $softwareModule = Module::where('key', 'softwares')->first();

        if (! $softwareModule)
        {
            $this->command->warn('Software module not found, skipping software seeding');

            return;
        }

        // Create software categories
        $subtitleCategory = Category::firstOrCreate([
            'name' => 'Subtitulación',
            'module_id' => $softwareModule->id,
            'team_id' => 1,
        ], [
            'description' => 'Software para subtitulación y captions',
            'status' => 1,
        ]);

        $dubbingCategory = Category::firstOrCreate([
            'name' => 'Doblaje',
            'module_id' => $softwareModule->id,
            'team_id' => 1,
        ], [
            'description' => 'Software para doblaje y audio',
            'status' => 1,
        ]);

        $videoEditingCategory = Category::firstOrCreate([
            'name' => 'Edición de video',
            'module_id' => $softwareModule->id,
            'team_id' => 1,
        ], [
            'description' => 'Software para edición de video',
            'status' => 1,
        ]);

        $catToolsCategory = Category::firstOrCreate([
            'name' => 'CAT Tools',
            'module_id' => $softwareModule->id,
            'team_id' => 1,
        ], [
            'description' => 'Computer Assisted Translation tools',
            'status' => 1,
        ]);

        $developmentCategory = Category::firstOrCreate([
            'name' => 'Desarrollo',
            'module_id' => $softwareModule->id,
            'team_id' => 1,
        ], [
            'description' => 'Software de desarrollo y programación',
            'status' => 1,
        ]);

        // Create software for subtitles
        $subtitleSoftware = [
            ['name' => 'Aegisub', 'team_id' => 1, 'category_id' => $subtitleCategory->id],
            ['name' => 'Subtitle Edit', 'team_id' => 1, 'category_id' => $subtitleCategory->id],
            ['name' => 'Subtitle Workshop', 'team_id' => 1, 'category_id' => $subtitleCategory->id],
            ['name' => 'EZTitles', 'team_id' => 1, 'category_id' => $subtitleCategory->id],
            ['name' => 'SubtitleNEXT', 'team_id' => 1, 'category_id' => $subtitleCategory->id],
        ];

        // Create software for dubbing
        $dubbingSoftware = [
            ['name' => 'Pro Tools', 'team_id' => 1, 'category_id' => $dubbingCategory->id],
            ['name' => 'Adobe Audition', 'team_id' => 1, 'category_id' => $dubbingCategory->id],
            ['name' => 'Logic Pro X', 'team_id' => 1, 'category_id' => $dubbingCategory->id],
            ['name' => 'Cubase', 'team_id' => 1, 'category_id' => $dubbingCategory->id],
            ['name' => 'REAPER', 'team_id' => 1, 'category_id' => $dubbingCategory->id],
        ];

        // Create software for video editing
        $videoEditingSoftware = [
            ['name' => 'Adobe Premiere Pro', 'team_id' => 1, 'category_id' => $videoEditingCategory->id],
            ['name' => 'Final Cut Pro', 'team_id' => 1, 'category_id' => $videoEditingCategory->id],
            ['name' => 'DaVinci Resolve', 'team_id' => 1, 'category_id' => $videoEditingCategory->id],
            ['name' => 'Avid Media Composer', 'team_id' => 1, 'category_id' => $videoEditingCategory->id],
            ['name' => 'Vegas Pro', 'team_id' => 1, 'category_id' => $videoEditingCategory->id],
        ];

        // Create software for CAT Tools
        $catToolsSoftware = [
            ['name' => 'SDL Trados', 'team_id' => 1, 'category_id' => $catToolsCategory->id],
            ['name' => 'MemoQ', 'team_id' => 1, 'category_id' => $catToolsCategory->id],
            ['name' => 'Wordfast', 'team_id' => 1, 'category_id' => $catToolsCategory->id],
            ['name' => 'Memsource', 'team_id' => 1, 'category_id' => $catToolsCategory->id],
            ['name' => 'Xbench', 'team_id' => 1, 'category_id' => $catToolsCategory->id],
        ];

        // Create software for development
        $developmentSoftware = [
            ['name' => 'Visual Studio Code', 'team_id' => 1, 'category_id' => $developmentCategory->id],
            ['name' => 'Sublime Text', 'team_id' => 1, 'category_id' => $developmentCategory->id],
            ['name' => 'PHPStorm', 'team_id' => 1, 'category_id' => $developmentCategory->id],
            ['name' => 'Git', 'team_id' => 1, 'category_id' => $developmentCategory->id],
            ['name' => 'Docker', 'team_id' => 1, 'category_id' => $developmentCategory->id],
        ];

        // Insert all software records
        foreach (array_merge($subtitleSoftware, $dubbingSoftware, $videoEditingSoftware, $catToolsSoftware, $developmentSoftware) as $software)
        {
            Software::updateOrCreate(
                ['name' => $software['name'], 'team_id' => $software['team_id']],
                $software,
            );
        }
    }
}
