<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Team;
use Hash;

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
        $revision->assignRole([1, 2, 10]);
        // $revision->categories()->attach([5001, 5002, 5003, 5004]);

        $humano = User::factory()->create([
            'name' => 'Victor Gómez',
            'phone' => 34665086080,
            'email' => 'victor@machbel.com',
            'password' => '$2y$10$FcK76MqjsbRMzQeDyqSO3ujezrf7NLQWoZlQuxtvlWHogq9ULJKoi',
        ]);
        $humano->assignRole([1, 2]);
        // $revision->categories()->attach([5001]);

        // Admin
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Simplicity!'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole([2]);
        // $user->categories()->attach([5001, 5003, 5004]);

        $user->ownedTeams()->create([
            'name' => "Demo's Team",
            'personal_team' => false,
        ]);
        $user->teams()->attach(1, [
            'role' => 'admin',
            'created_at' => now()
        ]);
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
        // $user->categories()->attach([5001]);
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
        // $user->categories()->attach([5001]);
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
        // $user->categories()->attach([5001]);
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
        // $user->categories()->attach([5001]);
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
        // $user->categories()->attach([5001]);
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
        // $user->categories()->attach([5001]);
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
        // $user->categories()->attach([5001]);
        $user->teams()->attach(1);

        // Team revision alpha
        $revision->ownedTeams()->create([
            'name' => "revision alpha's Team",
            'personal_team' => false,
        ]);

        $revision->teams()->attach(2, [
            'role' => 'admin',
            'created_at' => now()
        ]);
        $revision->update(['current_team_id' => 2]);

        // Lucas Luna - revision alpha
        $lucas = User::factory()->create([
            'name' => 'Lucas Luna Claraso',
            'email' => 'lucaslunaclaraso@gmail.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => now(),
            'current_team_id' => 2,
        ]);
        $lucas->assignRole(2);
        // $lucas->categories()->attach([5001]);
        $lucas->teams()->attach(2);

        // Jesica Lorente - revision alpha
        $jesica = User::factory()->create([
            'name' => 'Jesica Lorente',
            'email' => 'jesicalorente@selltion.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => now(),
            'current_team_id' => 2,
        ]);
        $jesica->assignRole(2);
        // $jesica->categories()->attach([5001]);
        $jesica->teams()->attach(2);

        // Team humano
        $humano->ownedTeams()->create([
            'name' => "Humano's Team",
            'personal_team' => false,
        ]);

        $humano->teams()->attach(3, [
            'role' => 'admin',
            'created_at' => now()
        ]);
        $humano->update(['current_team_id' => 3]);

        // Asing User to Team
        $team = Team::find(3);
        $team->users()->attach($revision->id, [
            'role' => 'admin'
        ]);
    }
}
