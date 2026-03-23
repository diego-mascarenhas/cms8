<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketResponse>
 */
class TicketResponseFactory extends Factory
{
    protected $model = TicketResponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'message' => $this->faker->paragraph(),
            'is_internal_note' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => ['is_internal_note' => true]);
    }
}
