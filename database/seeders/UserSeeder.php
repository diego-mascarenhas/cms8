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
        $user->assignRole([1, 2, 7]);
        $user->categories()->attach([1, 2, 3, 4]);

        // Admin
        $user = User::factory()->create([
            'name' => 'Pablo Barrozo',
            'phone' => 5491138738376,
            'email' => 'pablo@revisionalpha.com.ar',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole([2, 7]);
        $user->categories()->attach([1, 3, 4]);

        // Colaborator
        $user = User::factory()->create([
            'name' => 'Carla De Loureiro',
            'phone' => 5491153875691,
            'email' => 'carla@revisionestudio.com',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole(3);
        $user->categories()->attach([1, 4]);

        // Editor
        $user = User::factory()->create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(4);
        $user->categories()->attach([1]);

        // Auditor
        $user = User::factory()->create([
            'name' => 'Auditor User',
            'email' => 'auditor@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(5);
        $user->categories()->attach([1]);

        // Client
        $user = User::factory()->create([
            'name' => 'Client User',
            'email' => 'client@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(6);
        $user->categories()->attach([1]);

        // Guest
        $user = User::factory()->create([
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(7);
        $user->categories()->attach([1]);

        // $user = User::factory()->create([
        //     'name' => 'Daniel Girol',
        //     'phone' => 34660136913,
        //     'email' => 'daniel@girol.es',
        //     'password' => Hash::make('Passw0rd!'),
        // ]);
        // $user->assignRole(2);
        // $user->categories()->attach([3]);
    }
}
