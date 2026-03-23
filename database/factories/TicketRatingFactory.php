<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketRating>
 */
class TicketRatingFactory extends Factory
{
    protected $model = TicketRating::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'tiempo_respuesta' => $this->faker->numberBetween(1, 5),
            'atencion' => $this->faker->numberBetween(1, 5),
            'solucion' => $this->faker->numberBetween(1, 5),
            'comentarios' => $this->faker->optional(0.5)->sentence(),
        ];
    }
}
