<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'module_id' => null,
            'team_id' => Team::factory(),
            'description' => $this->faker->sentence(),
            'data' => null,
            'parent_id' => null,
            'order' => 0,
            'status' => 1,
        ];
    }
}
