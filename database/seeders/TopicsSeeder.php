<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Team;

class TopicsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topics = [
            'Medicina',
            'Viajes',
            'Técnica',
            'Ciencia',
            'Cine',
            'Letras',
            'Tecnología',
            'Deportes',
            'Arte',
            'Música',
            'Gastronomía',
            'Historia',
            'Educación',
            'Psicología',
            'Economía',
            'Política',
            'Medio Ambiente',
            'Salud',
            'Cultura',
            'Literatura',
            'Filosofía',
            'Arquitectura',
            'Diseño',
            'Marketing',
            'Negocios',
            'Finanzas',
            'Legal',
            'Inmobiliario',
            'Agricultura',
            'Turismo',
            'Comunicación',
            'Periodismo',
            'Traducción',
            'Interpretación',
            'Subtitulado',
            'Localización',
            'Gaming',
            'E-commerce',
            'Redes Sociales',
            'Automóvil',
            'Energía',
            'Ingeniería',
            'Biotecnología',
            'Farmacéutica',
            'Cosmética',
            'Moda',
            'Textil',
            'Alimentación',
            'Bebidas',
            'Entretenimiento'
        ];

        // Get all teams to assign topics to each one
        $teams = Team::all();

        foreach ($teams as $team) {
            foreach ($topics as $topicName) {
                DB::table('topics')->insert([
                    'name' => $topicName,
                    'team_id' => $team->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
} 