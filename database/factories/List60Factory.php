<?php

namespace Database\Factories;

use App\Models\List60;
use Illuminate\Database\Eloquent\Factories\Factory;

class List60Factory extends Factory
{
    protected $model = List60::class;

    public function definition()
    {
        return [
            'contact_id' => $this->faker->numberBetween(1, 100),
            'type_id' => $this->faker->numberBetween(1, 2),
            'date_next' => $this->faker->dateTimeBetween('now', '+1 month'),
            'notes' => $this->faker->sentence(),
            'status_id' => $this->faker->numberBetween(1, 5),
        ];
    }
}