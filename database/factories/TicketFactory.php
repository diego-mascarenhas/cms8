<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'subject' => $this->faker->sentence(4),
            'description' => $this->faker->paragraphs(2, true),
            'status' => $this->faker->randomElement(['open', 'in_progress', 'waiting_client', 'closed']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'assigned_to' => null,
            'closed_at' => null,
            'last_response_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'open', 'closed_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }
}
