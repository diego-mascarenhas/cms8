<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Enterprise::class;

    public function definition()
    {
        $users = User::all();

        return [
            'name' => $this->faker->name,
            'description' => $this->faker->sentence(),
            'assigned_to' => $this->faker->boolean(70) ? $users->random()->id : $this->faker->randomElement($users)->id,
        ];
    }
}
