<?php

namespace Database\Seeders;

use App\Models\Fare;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class FaresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fares = [
            // Traducción audiovisual (type_id = 1)
            ['name' => 'Traducción de plantilla', 'type_id' => 1],
            ['name' => 'Traducción + subtitulado sin guion (creación de subtítulos)', 'type_id' => 1],
            ['name' => 'Traducción + subtitulado con guion (creación de subtítulos)', 'type_id' => 1],
            ['name' => 'Traducción sin guion', 'type_id' => 1],
            ['name' => 'Traducción con guion', 'type_id' => 1],
            ['name' => 'Traducción para locución, doblaje, voice over', 'type_id' => 1],
            ['name' => 'Traducción de guion literario', 'type_id' => 1],
            ['name' => 'Transcreación', 'type_id' => 1],
            ['name' => 'Transcripción', 'type_id' => 1],
            ['name' => 'Transcripción + subtitulado (creación de subtítulos)', 'type_id' => 1],
            ['name' => 'Adaptación + subtitulado (creación de subtítulos)', 'type_id' => 1],
            ['name' => 'Revisión audiovisual', 'type_id' => 1],
            ['name' => 'Ajuste de traducción para doblaje', 'type_id' => 1],
            ['name' => 'Posedición de traducción audiovisual', 'type_id' => 1],
            ['name' => 'Posedición de transcripción', 'type_id' => 1],
            
            // Traducción general (texto) (type_id = 2)
            ['name' => 'Traducción general', 'type_id' => 2],
            ['name' => 'Revisión general', 'type_id' => 2],
            ['name' => 'Traducción jurídica', 'type_id' => 2],
            ['name' => 'Traducción médica', 'type_id' => 2],
            ['name' => 'Traducción técnica', 'type_id' => 2],
            ['name' => 'Traducción científica', 'type_id' => 2],
            
            // Accesibilidad audiovisual (type_id = 3)
            ['name' => 'Posedición de traducción', 'type_id' => 3],
            ['name' => 'Subtítulos para sordos con guion', 'type_id' => 3],
            ['name' => 'Subtítulos para sordos sin guion', 'type_id' => 3],
            ['name' => 'Adaptación a subtítulos para sordos', 'type_id' => 3],
            ['name' => 'Revisión de subtítulos para sordos', 'type_id' => 3],
            ['name' => 'Creación guion de audiodescripción', 'type_id' => 3],
            ['name' => 'Locución de audiodescripción', 'type_id' => 3],
            ['name' => 'Lengua de signos', 'type_id' => 3]
        ];

        foreach ($fares as $fare) {
            Fare::create($fare);
        }
    }
} 