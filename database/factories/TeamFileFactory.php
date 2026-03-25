<?php

namespace Database\Factories;

use App\Enums\MultimediaVisibility;
use App\Models\Team;
use App\Models\TeamFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamFile>
 */
class TeamFileFactory extends Factory
{
    protected $model = TeamFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'visibility' => MultimediaVisibility::PRIVATE,
            'sort_order' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forTeamAndUser(Team $team, User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
