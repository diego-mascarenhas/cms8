<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin
        $email = 'diego.mascarenhas@icloud.com';
        $user = User::whereEmail($email)->first();

        if (!$user)
        {
            $user = User::factory()->create([
                'name' => 'Diego Mascarenhas',
                'phone' => 34722372858,
                'email' => $email,
                'password' => '$2y$10$9His4IIPh5nFp0TSilz.h.0DLLE4DzhX1Os2y0QHwt.a19s6whxyC',
            ]);
        }

        // Testers
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => hash::make('demo'),
            'email_verified_at' => null,
        ]);

        User::factory()->create([
            'name' => 'Pablo Barrozo',
            'phone' => 5491138738376,
            'email' => 'pablo@revisionalpha.com',
            'password' => hash::make('Passw0rd!'),
        ]);

        User::factory()->create([
            'name' => 'Carla De Loureiro',
            'phone' => 5491153875691,
            'email' => 'carla@revisionestudio.com',
            'password' => hash::make('Passw0rd!'),
        ]);

        User::factory()->create([
            'name' => 'Daniel Girol',
            'phone' => 34660136913,
            'email' => 'daniel@girol.es',
            'password' => hash::make('Passw0rd!'),
        ]);

        $this->call([
			CategorySeeder::class,
            MessageTypeSeeder::class,
            TemplateSeeder::class,
            MessageSeeder::class,
		]);
    }
}
