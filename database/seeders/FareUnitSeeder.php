<?php

namespace Database\Seeders;

use App\Models\Fare;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FareUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, get all the unit IDs
        $minuteId = Unit::where('type', 'Minute')->first()->id;
        $tenMinutesId = Unit::where('type', '10 Minutes')->first()->id;
        $hourId = Unit::where('type', 'Hour')->first()->id;
        $wordId = Unit::where('type', 'Word')->first()->id;
        $pageId = Unit::where('type', 'Page')->first()->id;
        $rollId = Unit::where('type', 'Roll')->first()->id;
        
        // Define the relationships
        $relationships = [
            // Traducción audiovisual
            ['fare_name' => 'Traducción de plantilla', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Traducción + subtitulado sin guion (creación de subtítulos)', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Traducción + subtitulado con guion (creación de subtítulos)', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Traducción sin guion', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Traducción con guion', 'unit_ids' => [$pageId]],
            ['fare_name' => 'Traducción para locución, doblaje, voice over', 'unit_ids' => [$minuteId, $rollId]],
            ['fare_name' => 'Traducción de guion literario', 'unit_ids' => [$pageId]],
            ['fare_name' => 'Transcreación', 'unit_ids' => [$hourId]],
            ['fare_name' => 'Transcripción', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Transcripción + subtitulado (creación de subtítulos)', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Adaptación + subtitulado (creación de subtítulos)', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Revisión audiovisual', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Ajuste de traducción para doblaje', 'unit_ids' => [$minuteId, $rollId]],
            ['fare_name' => 'Posedición de traducción audiovisual', 'unit_ids' => [$hourId, $minuteId]],
            ['fare_name' => 'Posedición de transcripción', 'unit_ids' => [$hourId, $minuteId]],
            
            // Traducción general (texto)
            ['fare_name' => 'Traducción general', 'unit_ids' => [$wordId]],
            ['fare_name' => 'Revisión general', 'unit_ids' => [$wordId]],
            ['fare_name' => 'Traducción jurídica', 'unit_ids' => [$wordId]],
            ['fare_name' => 'Traducción médica', 'unit_ids' => [$wordId]],
            ['fare_name' => 'Traducción técnica', 'unit_ids' => [$wordId]],
            ['fare_name' => 'Traducción científica', 'unit_ids' => [$wordId]],
            
            // Accesibilidad audiovisual
            ['fare_name' => 'Posedición de traducción', 'unit_ids' => [$hourId, $wordId]],
            ['fare_name' => 'Subtítulos para sordos con guion', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Subtítulos para sordos sin guion', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Adaptación a subtítulos para sordos', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Revisión de subtítulos para sordos', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Creación guion de audiodescripción', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Locución de audiodescripción', 'unit_ids' => [$minuteId]],
            ['fare_name' => 'Lengua de signos', 'unit_ids' => [$minuteId]]
        ];
        
        // Create the relationships
        foreach ($relationships as $relationship) {
            $fare = Fare::where('name', $relationship['fare_name'])->first();
            
            if ($fare) {
                foreach ($relationship['unit_ids'] as $unitId) {
                    DB::table('fare_unit')->insert([
                        'fare_id' => $fare->id,
                        'unit_id' => $unitId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}
