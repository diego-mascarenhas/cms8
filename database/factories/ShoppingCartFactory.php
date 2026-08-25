<?php

namespace Database\Factories;

use App\Enums\ShoppingCartChannel;
use App\Models\ShoppingCart;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingCart>
 */
class ShoppingCartFactory extends Factory
{
    protected $model = ShoppingCart::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'session_key' => '54911'.$this->faker->numerify('########'),
            'channel' => ShoppingCartChannel::WhatsApp,
        ];
    }
}
