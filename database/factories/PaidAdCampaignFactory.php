<?php

namespace Database\Factories;

use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Models\PaidAdCampaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaidAdCampaign>
 */
class PaidAdCampaignFactory extends Factory
{
    protected $model = PaidAdCampaign::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->catchPhrase(),
            'objective' => PaidAdObjective::Traffic,
            'status' => PaidAdCampaignStatus::Draft,
            'budget_type' => 'daily',
            'budget_amount' => $this->faker->randomFloat(2, 5, 500),
            'currency' => 'EUR',
            'start_at' => now(),
            'end_at' => now()->addMonth(),
            'targeting' => ['locations' => 'Spain'],
            'creative' => ['headline' => $this->faker->sentence(4)],
            'settings' => [],
        ];
    }
}
