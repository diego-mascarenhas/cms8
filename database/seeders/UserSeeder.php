<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Administrator
        $user = User::factory()->create([
            'name' => 'Diego Mascarenhas',
            'phone' => 34722372858,
            'email' => 'diego.mascarenhas@icloud.com',
            'password' => '$2y$10$9His4IIPh5nFp0TSilz.h.0DLLE4DzhX1Os2y0QHwt.a19s6whxyC',
        ]);
        $user->assignRole(1);
        $user->categories()->attach([1, 2, 3, 4]);

        // Tester
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(1);
        $user->categories()->attach([1]);

        // Colaborators
        $user = User::factory()->create([
            'name' => 'Pablo Barrozo',
            'phone' => 5491138738376,
            'email' => 'pablo@revisionalpha.com',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole(2);
        $user->categories()->attach([1, 3, 4]);

        $user = User::factory()->create([
            'name' => 'Carla De Loureiro',
            'phone' => 5491153875691,
            'email' => 'carla@revisionestudio.com',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole(2);
        $user->categories()->attach([1, 4]);

        $user = User::factory()->create([
            'name' => 'Daniel Girol',
            'phone' => 34660136913,
            'email' => 'daniel@girol.es',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole(2);
        $user->categories()->attach([3]);
    }
}
