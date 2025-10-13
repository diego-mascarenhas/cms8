<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EnterpriseTaxStatusType>
 */
class EnterpriseTaxStatusTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $taxStatuses = [
            'Exempt from tax',
            'Registered taxpayer',
            'Self-employed',
            'Tax-exempt entity',
            'VAT registered',
            'Non-resident taxpayer',
            'Corporate taxpayer',
            'Individual taxpayer',
            'Partnership',
            'Limited liability company',
            'Sole proprietorship',
            'Government entity',
            'Non-profit organization',
            'Foreign corporation',
            'Domestic corporation',
        ];

        return [
            'name' => $this->faker->unique()->randomElement($taxStatuses),
        ];
    }

    /**
     * Indicate that the tax status is exempt.
     */
    public function exempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Exempt from tax',
        ]);
    }

    /**
     * Indicate that the tax status is registered taxpayer.
     */
    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Registered taxpayer',
        ]);
    }

    /**
     * Indicate that the tax status is self-employed.
     */
    public function selfEmployed(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Self-employed',
        ]);
    }
}
