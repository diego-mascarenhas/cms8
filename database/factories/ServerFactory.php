<?php

namespace Database\Factories;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company().' WHM',
            'ip' => fake()->ipv4(),
            'server_url' => fake()->unique()->domainName(),
            'username' => 'root',
            'operating_system' => 'Linux',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'test-token',
            'success' => false,
            'status_id' => ServerStatus::Active->value,
            'data' => [],
        ];
    }

    public function plesk(): static
    {
        return $this->state(fn () => [
            'control_panel' => 'plesk',
        ]);
    }
}
