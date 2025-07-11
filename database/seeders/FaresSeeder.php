<?php

namespace Database\Seeders;

use App\Models\Fare;
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
            ['name' => 'Traducción de plantilla', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Traducción + subtitulado sin guion (creación de subtítulos)', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Traducción + subtitulado con guion (creación de subtítulos)', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Traducción sin guion', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Traducción con guion', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Traducción para locución, doblaje, voice over', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Traducción de guion literario', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Transcreación', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Transcripción', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Transcripción + subtitulado (creación de subtítulos)', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Adaptación + subtitulado (creación de subtítulos)', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Revisión audiovisual', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Ajuste de traducción para doblaje', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Posedición de traducción audiovisual', 'team_id' => 1, 'type_id' => 1],
            ['name' => 'Posedición de transcripción', 'team_id' => 1, 'type_id' => 1],

            // Traducción general (texto) (type_id = 2)
            ['name' => 'Traducción general', 'team_id' => 1, 'type_id' => 2],
            ['name' => 'Revisión general', 'team_id' => 1, 'type_id' => 2],
            ['name' => 'Traducción jurídica', 'team_id' => 1, 'type_id' => 2],
            ['name' => 'Traducción médica', 'team_id' => 1, 'type_id' => 2],
            ['name' => 'Traducción técnica', 'team_id' => 1, 'type_id' => 2],
            ['name' => 'Traducción científica', 'team_id' => 1, 'type_id' => 2],

            // Accesibilidad audiovisual (type_id = 3)
            ['name' => 'Posedición de traducción', 'team_id' => 1, 'type_id' => 3],
            ['name' => 'Subtítulos para sordos con guion', 'team_id' => 1, 'type_id' => 3],
            ['name' => 'Subtítulos para sordos sin guion', 'team_id' => 1, 'type_id' => 3],
            ['name' => 'Adaptación a subtítulos para sordos', 'team_id' => 1, 'type_id' => 3],
            ['name' => 'Revisión de subtítulos para sordos', 'team_id' => 1, 'type_id' => 3],
            ['name' => 'Creación guion de audiodescripción', 'team_id' => 1, 'type_id' => 3],
            ['name' => 'Locución de audiodescripción', 'team_id' => 1, 'type_id' => 3],
            ['name' => 'Lengua de signos', 'team_id' => 1, 'type_id' => 3],
        ];

        foreach ($fares as $fare) {
            Fare::create($fare);
        }
    }
}
