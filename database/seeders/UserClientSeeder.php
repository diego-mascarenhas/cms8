<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Enterprise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserClientSeeder extends Seeder
{
    public function run()
    {
        $clients = Enterprise::all();

        foreach ($clients as $client)
        {
            $user = User::create([
                'name' => $client->name,
                'email' => strtolower(str_replace(' ', '.', $client->name)) . '@example.com',
                'password' => Hash::make('password'),
            ]);

            $user->assignRole(3);

            $client->update(['user_id' => $user->id]);
        }
    }
}
