<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition()
    {
        $users = User::all();

        return [
            'team_id' => 1,
            'name' => $this->faker->company,
            'creator_id' => $this->faker->boolean(70) ? $users->random()->id : $this->faker->randomElement($users)->id,
            'responsible_id' => $this->faker->boolean(70) ? $users->random()->id : $this->faker->randomElement($users)->id,
            'status_id' => ContactStatus::inRandomOrder()->first()->id,
        ];
    }
}