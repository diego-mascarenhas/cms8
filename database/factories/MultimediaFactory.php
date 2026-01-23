<?php

namespace Database\Factories;

use App\Enums\MultimediaStatus;
use App\Enums\MultimediaVisibility;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Multimedia>
 */
class MultimediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'category_id' => null,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'status' => MultimediaStatus::ACTIVE->value,
            'visibility' => MultimediaVisibility::PRIVATE->value,
            'type' => 'image',
            'created_by' => User::factory(),
            'updated_by' => function (array $attributes)
            {
                return $attributes['created_by'];
            },
        ];
    }
}
