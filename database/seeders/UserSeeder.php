<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Core system users - these will be assigned to teams by their respective seeders
        
        // Administrator revision alpha - will be handled by RevisionAlphaSeeder
        $revision = User::factory()->create([
            'name' => 'Diego Mascarenhas',
            'phone' => 34722372858,
            'email' => 'diego.mascarenhas@icloud.com',
            'password' => '$2y$10$9His4IIPh5nFp0TSilz.h.0DLLE4DzhX1Os2y0QHwt.a19s6whxyC',
        ]);
        $revision->assignRole([1, 2, 10]);

        // Administrator humano - will be handled by HumanoSeeder
        $humano = User::factory()->create([
            'name' => 'Victor Gómez',
            'phone' => 34665086080,
            'email' => 'victor@machbel.com',
            'password' => '$2y$10$FcK76MqjsbRMzQeDyqSO3ujezrf7NLQWoZlQuxtvlWHogq9ULJKoi',
        ]);
        $humano->assignRole([1, 2]);

        // Demo Admin - creates Demo team (Team 1)
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Simplicity!'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole([2]);
        
        // Create Demo Team (Team 1)
        $demoTeam = $user->ownedTeams()->create([
            'name' => "Demo's Team",
            'personal_team' => false,
        ]);
        $user->teams()->attach($demoTeam->id, [
            'role' => 'admin',
            'created_at' => now(),
        ]);
        $user->update(['current_team_id' => $demoTeam->id]);

        // Demo team role-based users - these will be assigned to Team 1
        $demoUsers = [
            ['name' => 'Collaborator', 'email' => 'collaborator@example.com', 'role' => 3],
            ['name' => 'Editor', 'email' => 'editor@example.com', 'role' => 4],
            ['name' => 'Auditor', 'email' => 'auditor@example.com', 'role' => 5],
            ['name' => 'Technical', 'email' => 'technical@example.com', 'role' => 6],
            ['name' => 'Client', 'email' => 'client@example.com', 'role' => 7],
            ['name' => 'User', 'email' => 'user@example.com', 'role' => 8],
            ['name' => 'Guest', 'email' => 'guest@example.com', 'role' => 9],
        ];

        foreach ($demoUsers as $userData) {
            $user = User::factory()->create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('Passw0rd!'),
                'email_verified_at' => null,
                'current_team_id' => $demoTeam->id,
            ]);
            $user->assignRole($userData['role']);
            $user->teams()->attach($demoTeam->id);
        }

        // Note: Teams are created as follows:
        // - Team 1 (Demo) -> Created above in UserSeeder
        // - Team 2 (Revision Alpha) -> RevisionAlphaSeeder
        // - Team 3 (Humano) -> HumanoSeeder
        // - Team 4 (BBO) -> BboSeeder
    }
}
