<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Fare;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $projectNames = [
            'Website Translation - English to Spanish',
            'Marketing Video Subtitling',
            'Technical Manual Translation',
            'Mobile App Localization',
            'Documentary Voice-over',
            'Software Interface Translation',
            'Training Video Dubbing',
            'Legal Document Translation',
            'Product Catalog Translation',
            'Corporate Presentation Subtitling',
            'User Manual Translation',
            'Advertising Campaign Localization',
            'Educational Content Translation',
            'Press Release Translation',
            'Annual Report Translation',
        ];

        $descriptions = [
            'Professional translation services for corporate website content, ensuring cultural adaptation and SEO optimization.',
            'High-quality subtitling for marketing videos with precise timing and cultural adaptation.',
            'Technical translation of user manuals and documentation with industry-specific terminology.',
            'Complete localization of mobile application including UI, strings, and marketing materials.',
            'Professional voice-over recording for documentary content with native speakers.',
            'Software interface translation maintaining consistency across all user-facing elements.',
            'Complete dubbing services for training videos with professional voice actors.',
            'Legal document translation with certified translators and notarization services.',
            'Product catalog translation with product description optimization.',
            'Corporate presentation subtitling for international audiences.',
            'User manual translation with technical accuracy and user-friendly language.',
            'Advertising campaign localization for multiple markets and cultures.',
            'Educational content translation for online learning platforms.',
            'Press release translation for international media distribution.',
            'Annual report translation with financial terminology expertise.',
        ];

        return [
            'team_id' => 1, // Team 1 (Demo)
            'enterprise_id' => Enterprise::where('team_id', 1)->inRandomOrder()->first()->id ?? 1,
            'category_id' => optional(Category::where('module_id', 1)->inRandomOrder()->first())->id, // Assuming module_id 1 is for projects
            'name' => $this->faker->randomElement($projectNames),
            'real_name' => $this->faker->optional()->words(3, true),
            'description' => $this->faker->randomElement($descriptions),
            'price' => $this->faker->randomFloat(2, 500, 50000),
            'discount' => $this->faker->optional(0.3)->randomFloat(2, 0, 20),
            'cost' => $this->faker->optional()->randomFloat(2, 200, 30000),
            'date_material' => $this->faker->optional()->dateTimeBetween('-6 months', '-1 month'),
            'date_start' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
            'date_end' => $this->faker->dateTimeBetween('-1 month', '+3 months'),
            'responsible_id' => User::whereHas('teams', function ($query)
            {
                $query->where('team_id', 1);
            })->inRandomOrder()->first()->id ?? 1,
            'status_id' => ProjectStatus::inRandomOrder()->first()->id ?? 1,
        ];
    }

    /**
     * Indicate that the project is active (in progress).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => ProjectStatus::whereIn('name', ['AUTHORIZED', 'APPROVED', 'WAITING_FOR_RESPONSE', 'IN_PROGRESS'])->inRandomOrder()->first()->id ?? 3,
        ]);
    }

    /**
     * Indicate that the project is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => ProjectStatus::where('name', 'COMPLETED')->first()->id ?? 4,
            'date_end' => $this->faker->dateTimeBetween('-2 months', 'now'),
        ]);
    }

    /**
     * Indicate that the project is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => ProjectStatus::whereIn('name', ['PENDING', 'WAITING_FOR_APPROVAL'])->inRandomOrder()->first()->id ?? 1,
        ]);
    }

    /**
     * Indicate that the project is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => ProjectStatus::where('name', 'CANCELLED')->first()->id ?? 5,
        ]);
    }

    /**
     * Configure the project with fares after creation.
     */
    public function withFares(): static
    {
        return $this->afterCreating(function (Project $project)
        {
            // Get random fares (1-3 fares per project)
            $fares = Fare::where('team_id', 1)->inRandomOrder()->take($this->faker->numberBetween(1, 3))->get();

            if ($fares->isNotEmpty())
            {
                foreach ($fares as $fare)
                {
                    $project->fares()->attach($fare->id, [
                        'source_language_code' => $this->faker->randomElement(['es', 'en', 'fr', 'de', 'it']),
                        'target_language_code' => $this->faker->randomElement(['es', 'en', 'fr', 'de', 'it']),
                        'quantity' => $this->faker->numberBetween(100, 5000),
                        'unit' => $this->faker->randomElement(['pal', 'pág', 'hora', 'min']),
                    ]);
                }
            }
        });
    }
}
