<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            'Web Hosting',
            'Email Service',
            'Domain Registration',
            'SSL Certificate',
            'Website Maintenance',
            'SEO Services',
            'Content Management',
            'Database Management',
            'Backup Service',
            'Security Monitoring',
        ];

        $operations = ['buy', 'sell'];
        $statuses = [1, 2, 3, 4, 5, 6, 7, 8]; // Based on the model's status labels
        $frequencies = [1, 3, 6, 12]; // Monthly, quarterly, biannual, annual

        return [
            'enterprise_id' => Enterprise::factory(),
            'category_id' => function () {
                // Get a service category for team_id 1
                return Category::where('team_id', 1)
                    ->where('module_id', function ($query) {
                        $query->select('id')
                            ->from('modules')
                            ->where('name', 'services')
                            ->limit(1);
                    })
                    ->inRandomOrder()
                    ->first()?->id ?? 1;
            },
            'operation' => $this->faker->randomElement($operations),
            'description' => $this->faker->randomElement($services) . ' - ' . $this->faker->text(100),
            'data' => [
                'domain' => $this->faker->domainName(),
                'server_url' => $this->faker->url(),
                'username' => $this->faker->userName(),
                'serviceName' => $this->faker->randomElement($services),
                'unique_key' => $this->faker->uuid(),
                'state' => $this->faker->randomElement(['active', 'pending', 'suspended']),
            ],
            'currency_id' => 1, // Assuming EUR is ID 1
            'price' => $this->faker->randomFloat(2, 10, 500),
            'discount' => $this->faker->randomFloat(2, 0, 20),
            'frequency' => $this->faker->randomElement($frequencies),
            'next_billing' => $this->faker->dateTimeBetween('now', '+1 year'),
            'last_billed' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'expires_at' => $this->faker->dateTimeBetween('+6 months', '+2 years'),
            'responsible_id' => function () {
                // Get a user from team_id 1
                return User::whereHas('teams', function ($query) {
                    $query->where('team_id', 1);
                })->inRandomOrder()->first()?->id ?? 1;
            },
            'status' => $this->faker->randomElement($statuses),
        ];
    }

    /**
     * Configure the factory for team_id 1
     */
    public function forTeam1(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'enterprise_id' => Enterprise::where('team_id', 1)->inRandomOrder()->first()?->id 
                    ?? Enterprise::factory()->create(['team_id' => 1])->id,
            ];
        });
    }

    /**
     * Active service state
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 4, // Active status
                'operation' => 'sell',
            ];
        });
    }

    /**
     * Hosting service type
     */
    public function hosting(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'description' => 'Web Hosting Service - ' . $this->faker->domainName(),
                'data' => [
                    'domain' => $this->faker->domainName(),
                    'server_url' => 'https://hosting.example.com',
                    'username' => $this->faker->userName(),
                    'serviceName' => 'Web Hosting',
                    'unique_key' => $this->faker->uuid(),
                    'state' => 'active',
                    'disk_space' => $this->faker->randomElement(['1GB', '5GB', '10GB', 'Unlimited']),
                    'bandwidth' => $this->faker->randomElement(['10GB', '50GB', '100GB', 'Unlimited']),
                ],
                'price' => $this->faker->randomFloat(2, 15, 100),
                'frequency' => 12, // Annual
            ];
        });
    }
}
