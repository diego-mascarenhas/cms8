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
            // Traducción
            ['name' => 'Traducción para locución/voice over/doblaje', 'unit_id' => 1, 'block_id' => 1],
            ['name' => 'Traducción + subtitulado', 'unit_id' => 1, 'block_id' => 1],
            ['name' => 'Transcripción + subtitulado', 'unit_id' => 1, 'block_id' => 1],
            ['name' => 'Traducción de guion literario', 'unit_id' => 3, 'block_id' => 1],
            ['name' => 'Transcripción', 'unit_id' => 3, 'block_id' => 1],
            
            // Subtitulado
            ['name' => 'SPS con guion', 'unit_id' => 1, 'block_id' => 2],
            ['name' => 'SPS sin guion', 'unit_id' => 1, 'block_id' => 2],
            ['name' => 'Adaptación a SPS', 'unit_id' => 1, 'block_id' => 2],
            ['name' => 'Revisión SPS', 'unit_id' => 1, 'block_id' => 2],
            
            // Audiodescripción
            ['name' => 'Guion de audiodescripción', 'unit_id' => 1, 'block_id' => 3],
            ['name' => 'Locución de audiodescripción', 'unit_id' => 1, 'block_id' => 3],
            
            // Otras tarifas
            ['name' => 'Tarifa mínima', 'unit_id' => 5, 'block_id' => 4],
            ['name' => 'Tarifa por hora', 'unit_id' => 5, 'block_id' => 4]
        ];

        foreach ($fares as $fare) {
            Fare::create($fare);
        }
    }
} 