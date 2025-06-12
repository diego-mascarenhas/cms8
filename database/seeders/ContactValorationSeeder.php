<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            ['id' => 1, 'name' => 'Top'],
            ['id' => 2, 'name' => 'Validada'], 
            ['id' => 3, 'name' => 'Interesante'],
            ['id' => 4, 'name' => 'Lista negra'],
            ['id' => 5, 'name' => 'En espera'],
        ];

        // Get all teams
        $teams = DB::table('teams')->get();

        foreach ($teams as $team) {
            foreach ($valorations as $valoration) {
                DB::table('contact_valorations')->insertOrIgnore([
                    'id' => ($team->id * 10) + $valoration['id'], // Generate unique ID
                    'team_id' => $team->id,
                    'name' => $valoration['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
