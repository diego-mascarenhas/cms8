<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDailyPerformanceInsight>
 */
class UserDailyPerformanceInsightFactory extends Factory
{
    protected $model = UserDailyPerformanceInsight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'insight_date' => now()->toDateString(),
            'performance_ratio' => fake()->randomFloat(2, 10, 95),
            'headline' => 'Focus',
            'focus' => 'Dial prospects log notes followups',
            'message' => fake()->realTextBetween(120, 280),
            'context_snapshot' => [
                'interactions_count' => 3,
                'call_minutes' => 15.0,
                'tasks_done' => 1,
            ],
        ];
    }
}
