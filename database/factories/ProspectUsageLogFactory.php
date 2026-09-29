<?php

namespace Database\Factories;

use App\Models\ProspectUsageLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectUsageLog>
 */
class ProspectUsageLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'source' => 'import',
            'count' => 1,
            'consumed_at' => now(),
        ];
    }
}
