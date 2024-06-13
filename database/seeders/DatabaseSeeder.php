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
                'email' => $email,
                'password' => '$2y$10$9His4IIPh5nFp0TSilz.h.0DLLE4DzhX1Os2y0QHwt.a19s6whxyC',
            ]);
        }

        // Tester
        $email = 'test@example.com';
        $user = User::whereEmail($email)->first();

        if (!$user)
        {
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => $email,
                'password' => hash::make('Passw0rd!'),
            ]);
        }

        User::factory(10)->create();

        $this->call([
			CategorySeeder::class,
		]);
    }
}
