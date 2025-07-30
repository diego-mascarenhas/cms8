<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopicsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topics = [
            'Medicina',
            'Técnica',
            'Ciencia',
            'Cine',
            'Tecnología',
            'Arte',
            'Educación',
            'Marketing',
            'Legal',
            'Entretenimiento',
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
