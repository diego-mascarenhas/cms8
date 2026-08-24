<?php

namespace Database\Factories;

use App\Models\AppFeedback;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppFeedback>
 */
class AppFeedbackFactory extends Factory
{
    protected $model = AppFeedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'product' => 'ads',
            'answers' => [
                ['key' => 'satisfaction', 'choice' => 'satisfied'],
                ['key' => 'ease', 'choice' => 'easy'],
                ['key' => 'value', 'choice' => 'quite_a_bit'],
            ],
            'comment' => fake()->optional()->sentence(),
            'message' => 'satisfaction: satisfied',
        ];
    }
}
