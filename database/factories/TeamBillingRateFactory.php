<?php

namespace Database\Factories;

use App\Enums\TeamBillingProduct;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TeamBillingRate>
 */
class TeamBillingRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'product' => TeamBillingProduct::TokensMultiplier,
            'amount' => 10,
            'currency' => null,
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ];
    }
}
