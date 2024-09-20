<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Models\EnterpriseStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnterpriseFactory extends Factory
{
    protected $model = Enterprise::class;

    public function definition()
    {
        return [
            'team_id' => 1,
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->companyEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'status_id' => EnterpriseStatus::inRandomOrder()->first()->id,
        ];
    }
}