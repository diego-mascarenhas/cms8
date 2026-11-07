<?php

namespace Database\Factories;

use App\Enums\PaidAdUsageKind;
use App\Models\PaidAdUsageLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaidAdUsageLog>
 */
class PaidAdUsageLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'kind' => PaidAdUsageKind::Video,
            'quantity' => 1,
        ];
    }
}
