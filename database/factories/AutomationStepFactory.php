<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\AutomationStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationStep>
 */
class AutomationStepFactory extends Factory
{
    protected $model = AutomationStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'automation_id' => Automation::factory(),
            'key' => Str::slug($label, '_'),
            'label' => ucfirst($label),
            'prompt_key' => null,
            'instruction' => 'Ask the user a question and wait for their reply.',
            'is_entry' => false,
            'position_x' => fake()->numberBetween(50, 400),
            'position_y' => fake()->numberBetween(50, 300),
            'settings' => [],
        ];
    }

    public function entry(): static
    {
        return $this->state(fn () => ['is_entry' => true]);
    }
}
