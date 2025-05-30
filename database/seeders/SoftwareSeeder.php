<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Software;
use App\Models\SoftwareType;

class SoftwareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create software types
        $subtitleType = SoftwareType::firstOrCreate(['name' => 'Subtitulación']);
        $dubbingType = SoftwareType::firstOrCreate(['name' => 'Doblaje']);
        $videoEditingType = SoftwareType::firstOrCreate(['name' => 'Edición de video']);
        
        // Create software for subtitles
        $subtitleSoftware = [
            ['name' => 'Aegisub', 'team_id' => 1, 'type_id' => $subtitleType->id],
            ['name' => 'Subtitle Edit', 'team_id' => 1, 'type_id' => $subtitleType->id],
            ['name' => 'Subtitle Workshop', 'team_id' => 1, 'type_id' => $subtitleType->id],
            ['name' => 'EZTitles', 'team_id' => 1, 'type_id' => $subtitleType->id],
            ['name' => 'SubtitleNEXT', 'team_id' => 1, 'type_id' => $subtitleType->id],
        ];
        
        // Create software for dubbing
        $dubbingSoftware = [
            ['name' => 'Pro Tools', 'team_id' => 1, 'type_id' => $dubbingType->id],
            ['name' => 'Adobe Audition', 'team_id' => 1, 'type_id' => $dubbingType->id],
            ['name' => 'Logic Pro X', 'team_id' => 1, 'type_id' => $dubbingType->id],
            ['name' => 'Cubase', 'team_id' => 1, 'type_id' => $dubbingType->id],
            ['name' => 'REAPER', 'team_id' => 1, 'type_id' => $dubbingType->id],
        ];
        
        // Create software for video editing
        $videoEditingSoftware = [
            ['name' => 'Adobe Premiere Pro', 'team_id' => 1, 'type_id' => $videoEditingType->id],
            ['name' => 'Final Cut Pro', 'team_id' => 1, 'type_id' => $videoEditingType->id],
            ['name' => 'DaVinci Resolve', 'team_id' => 1, 'type_id' => $videoEditingType->id],
            ['name' => 'Avid Media Composer', 'team_id' => 1, 'type_id' => $videoEditingType->id],
            ['name' => 'Vegas Pro', 'team_id' => 1, 'type_id' => $videoEditingType->id],
        ];
        
        // Insert all software records
        foreach (array_merge($subtitleSoftware, $dubbingSoftware, $videoEditingSoftware) as $software) {
            Software::updateOrCreate(
                ['name' => $software['name'], 'team_id' => $software['team_id']],
                $software
            );
        }
    }
} 