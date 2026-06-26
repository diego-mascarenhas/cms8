<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainName(),
            'server_id' => Server::factory(),
            'username' => fake()->userName(),
            'plan' => 'default',
            'suspended' => false,
            'site_type' => null,
            'php_version' => null,
            'notes' => null,
            'needs_update' => false,
            'is_working' => true,
            'data' => [],
        ];
    }
}
