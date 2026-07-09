<?php

namespace Database\Factories;

use App\Models\PaidAdAudience;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaidAdAudience>
 */
class PaidAdAudienceFactory extends Factory
{
    protected $model = PaidAdAudience::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->words(2, true),
            'type' => 'saved',
            'targeting_rules' => ['locations' => 'Spain', 'interests' => 'marketing'],
            'estimated_size' => $this->faker->numberBetween(1000, 500000),
        ];
    }
}
