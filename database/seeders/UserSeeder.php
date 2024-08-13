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
        $user = User::factory()->create([
            'name' => 'Diego Mascarenhas',
            'phone' => 34722372858,
            'email' => 'diego.mascarenhas@icloud.com',
            'password' => '$2y$10$9His4IIPh5nFp0TSilz.h.0DLLE4DzhX1Os2y0QHwt.a19s6whxyC',
        ]);
        $user->assignRole([1, 2, 7]);
        $user->categories()->attach([5001, 5002, 5003, 5004]);

        // Admin
        $appUrl = env('APP_URL', 'localhost');
        $parsedUrl = parse_url($appUrl, PHP_URL_HOST) ?? $appUrl;

        if (Str::startsWith($parsedUrl, 'www.'))
        {
            $parsedUrl = substr($parsedUrl, 4);
        }

        $adminEmail = 'admin@' . $parsedUrl;

        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => $adminEmail,
            'password' => Hash::make('Simplicity!'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole([2, 7]);

        $user = User::factory()->create([
            'name' => 'Pablo Barrozo',
            'phone' => 5491138738376,
            'email' => 'pablo@revisionalpha.com.ar',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole([2, 7]);
        $user->categories()->attach([5001, 5003, 5004]);

        // Colaborator
        $user = User::factory()->create([
            'name' => 'Carla De Loureiro',
            'phone' => 5491153875691,
            'email' => 'carla@revisionestudio.com',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole(3);
        $user->categories()->attach([5001, 5004]);

        $user = User::factory()->create([
            'name' => 'Daniel Girol',
            'phone' => 34660136913,
            'email' => 'daniel@girol.es',
            'password' => Hash::make('Passw0rd!'),
        ]);
        $user->assignRole(2);
        $user->categories()->attach([5003]);

        // Editor
        $user = User::factory()->create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(4);
        $user->categories()->attach([5001]);

        // Auditor
        $user = User::factory()->create([
            'name' => 'Auditor User',
            'email' => 'auditor@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(5);
        $user->categories()->attach([5001]);

        // Client
        $user = User::factory()->create([
            'name' => 'Client User',
            'email' => 'client@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(6);
        $user->categories()->attach([5001]);

        // Guest
        $user = User::factory()->create([
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'password' => Hash::make('Passw0rd!'),
            'email_verified_at' => null,
        ]);
        $user->assignRole(7);
        $user->categories()->attach([5001]);
    }
}
