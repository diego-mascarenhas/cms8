<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactValorationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $valorations = [
            ['id' => 1, 'name' => 'Top', 'icon' => '⭐'],
            ['id' => 2, 'name' => 'Validada', 'icon' => '✅'],
            ['id' => 3, 'name' => 'Interesante', 'icon' => '🕐'],
            ['id' => 4, 'name' => 'Lista negra', 'icon' => '❌'],
            ['id' => 5, 'name' => 'En espera', 'icon' => '👁️'],
        ];

        // Get all teams
        $teams = DB::table('teams')->get();

        foreach ($teams as $team) {
            foreach ($valorations as $valoration) {
                DB::table('contact_valorations')->insertOrIgnore([
                    'id' => ($team->id * 10) + $valoration['id'], // Generate unique ID
                    'team_id' => $team->id,
                    'name' => $valoration['name'],
                    'icon' => $valoration['icon'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
