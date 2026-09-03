<?php

namespace Database\Factories;

use App\Models\MailerUsageLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailerUsageLog>
 */
class MailerUsageLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'source' => 'fanyion',
            'count' => 1,
            'sent_at' => now(),
        ];
    }
}
