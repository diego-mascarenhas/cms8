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
        // First, get all the unit IDs - using Spanish names as defined in UnitsSeeder
        $minuteUnit = Unit::where('type', 'Minutos')->first();
        $tenMinutesUnit = Unit::where('type', '10 Minutos')->first();
        $hourUnit = Unit::where('type', 'Horas')->first();
        $wordUnit = Unit::where('type', 'Palabras')->first();
        $pageUnit = Unit::where('type', 'Páginas')->first();
        $rollUnit = Unit::where('type', 'Rollos')->first();

        // Check if units exist before proceeding
        if (! $minuteUnit || ! $tenMinutesUnit || ! $hourUnit || ! $wordUnit || ! $pageUnit || ! $rollUnit) {
            echo "Warning: Some units not found. Skipping FareUnitSeeder.\n";

            return;
        }

        $minuteId = $minuteUnit->id;
        $tenMinutesId = $tenMinutesUnit->id;
        $hourId = $hourUnit->id;
        $wordId = $wordUnit->id;
        $pageId = $pageUnit->id;
        $rollId = $rollUnit->id;

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
            ['fare_name' => 'Lengua de signos', 'unit_ids' => [$minuteId]],
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
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
