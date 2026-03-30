<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        $contact = Contact::factory()->create();

        return [
            'team_id' => $contact->team_id,
            'contact_id' => $contact->id,
            'responsible_id' => User::query()->first()?->id ?? 1,
            'opportunity_stage_id' => OpportunityStage::query()->orderBy('sort_order')->firstOrFail()->id,
            'name' => $this->faker->sentence(3),
            'opened_at' => now()->toDateString(),
            'estimated_amount' => $this->faker->randomFloat(2, 100, 50000),
            'currency_id' => null,
            'description' => $this->faker->optional()->paragraph(),
            'notes' => null,
            'offering_summary' => null,
            'offering_type' => null,
            'offering_id' => null,
            'expected_close_at' => null,
            'probability' => $this->faker->numberBetween(0, 100),
            'won_project_id' => null,
            'closed_at' => null,
            'closed_reason' => null,
        ];
    }
}
