<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Administrator
        $revision = User::factory()->create([
            'name' => 'Diego Mascarenhas',
            'phone' => 34722372858,
            'email' => 'diego.mascarenhas@icloud.com',
            'password' => '$2y$10$9His4IIPh5nFp0TSilz.h.0DLLE4DzhX1Os2y0QHwt.a19s6whxyC',
        ]);
        $revision->assignRole([1, 2]);
        $revision->categories()->attach([5001, 5002, 5003, 5004]);

        // Admin
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Simplicity!'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole([2]);
        $user->categories()->attach([5001, 5003, 5004]);

        $user->ownedTeams()->create([
            'name' => "Demo's Team",
            'personal_team' => false,
        ]);
        $user->teams()->attach(1);
        $user->update(['current_team_id' => 1]); 

        // Colaborator
        $user = User::factory()->create([
            'name' => 'Colaborator',
            'email' => 'colaborator@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
            'current_team_id' => 1,
            
        ]);
        $user->assignRole(3);
        $user->categories()->attach([5001]);
        $user->teams()->attach(1);
        
        // Editor
        $user = User::factory()->create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
            'current_team_id' => 1,
        ]);
        $user->assignRole(4);
        $user->categories()->attach([5001]);
        $user->teams()->attach(1);

        // Auditor
        $user = User::factory()->create([
            'name' => 'Auditor',
            'email' => 'auditor@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
            'current_team_id' => 1,
        ]);
        $user->assignRole(5);
        $user->categories()->attach([5001]);
        $user->teams()->attach(1);

        // Technical
        $user = User::factory()->create([
            'name' => 'Technical',
            'email' => 'technical@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
            'current_team_id' => 1,
        ]);
        $user->assignRole(6);
        $user->categories()->attach([5001]);
        $user->teams()->attach(1);

        // Client
        $user = User::factory()->create([
            'name' => 'Client',
            'email' => 'client@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
            'current_team_id' => 1,
        ]);
        $user->assignRole(7);
        $user->categories()->attach([5001]);
        $user->teams()->attach(1);

        // User
        $user = User::factory()->create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
            'current_team_id' => 1,
        ]);
        $user->assignRole(8);
        $user->categories()->attach([5001]);
        $user->teams()->attach(1);
        
        // Guest
        $user = User::factory()->create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
            'current_team_id' => 1,
        ]);
        $user->assignRole(9);
        $user->categories()->attach([5001]);
        $user->teams()->attach(1);

        // Team revision alpha
        $revision->ownedTeams()->create([
            'name' => "revision alpha's Team",
            'personal_team' => false,
        ]);

        $revision->teams()->attach(2);
        $revision->update(['current_team_id' => 2]); 
    }
}
