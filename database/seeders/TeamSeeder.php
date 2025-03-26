<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'diego.mascarenhas@icloud.com')->first();
        
        $team = Team::updateOrCreate(
            ['id' => 2],
            [
                'user_id' => $user->id,
                'name' => "revision alpha's Team",
                'personal_team' => false,
            ]
        );

        $team->users()->sync([
            $user->id => ['role' => 'admin']
        ]);
    }
} 