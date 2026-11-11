<?php

namespace Database\Factories;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'survey_id' => function (array $attributes)
            {
                return Survey::factory()->create([
                    'team_id' => $attributes['team_id'],
                ])->id;
            },
            'user_id' => User::factory(),
            'payload_json' => ['answers' => ['q1' => '1']],
        ];
    }
}
