<?php

namespace Database\Factories;

use App\Models\GoLink;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoLink>
 */
class GoLinkFactory extends Factory
{
    protected $model = GoLink::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'code' => fake()->unique()->regexify('[23456789abcdefghjkmnpqrstuvwxyz]{8}'),
            'type' => GoLink::TYPE_SHOP_CATALOG_QR,
            'target_url' => 'https://pedimosfacil.com/demo',
            'active' => true,
            'hits_count' => 0,
            'last_hit_at' => null,
        ];
    }
}
