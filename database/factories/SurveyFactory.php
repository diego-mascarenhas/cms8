<?php

namespace Database\Factories;

use App\Models\Team;
use App\Support\SurveySchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Survey>
 */
class SurveyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => $this->faker->sentence(3),
            'code' => strtoupper($this->faker->unique()->bothify('??##')),
            'is_active' => true,
            'schema_json' => SurveySchema::document('Encuesta', [
                SurveySchema::field('q1', '¿Te gustó?', [
                    ['label' => 'Sí', 'value' => '1'],
                    ['label' => 'No', 'value' => '2'],
                ]),
            ]),
        ];
    }
}
