<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * Get the Demo Team (ID: 1) or create if not exists.
     */
    private function getDemoTeam(): Team
    {
        return Team::find(1) ?? Team::factory()->create(['name' => "Demo's Team"]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accountTypes = [
            ['code' => 'CASH', 'name' => 'Cash', 'symbol' => '$'],
            ['code' => 'BANK', 'name' => 'Bank Account', 'symbol' => '$'],
            ['code' => 'SAVINGS', 'name' => 'Savings Account', 'symbol' => '$'],
            ['code' => 'CHECKING', 'name' => 'Checking Account', 'symbol' => '$'],
            ['code' => 'CREDIT', 'name' => 'Credit Account', 'symbol' => '$'],
            ['code' => 'INVESTMENT', 'name' => 'Investment Account', 'symbol' => '$'],
            ['code' => 'BUSINESS', 'name' => 'Business Account', 'symbol' => '$'],
            ['code' => 'PERSONAL', 'name' => 'Personal Account', 'symbol' => '$'],
        ];

        $currencies = [
            840, // USD
            978, // EUR
            826, // GBP
            124, // CAD
            36,  // AUD
            392, // JPY
        ];

        $account = $this->faker->randomElement($accountTypes);

        return [
            'team_id' => $this->getDemoTeam()->id,
            'code' => $account['code'].'_'.$this->faker->unique()->numberBetween(1, 999),
            'name' => $account['name'].' '.$this->faker->randomNumber(3),
            'symbol' => $account['symbol'],
            'currency_id' => $this->faker->randomElement($currencies),
            'status' => 1, // Active by default
        ];
    }

    /**
     * Indicate that the payment account is for cash transactions.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'CASH_'.$this->faker->unique()->numberBetween(1, 999),
            'name' => 'Cash '.$this->faker->randomNumber(3),
            'symbol' => '$',
        ]);
    }

    /**
     * Indicate that the payment account is a bank account.
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'BANK_'.$this->faker->unique()->numberBetween(1, 999),
            'name' => 'Bank Account '.$this->faker->randomNumber(3),
            'symbol' => '$',
        ]);
    }

    /**
     * Indicate that the payment account is a savings account.
     */
    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'SAVINGS_'.$this->faker->unique()->numberBetween(1, 999),
            'name' => 'Savings Account '.$this->faker->randomNumber(3),
            'symbol' => '$',
        ]);
    }

    /**
     * Indicate that the payment account is a business account.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'BUSINESS_'.$this->faker->unique()->numberBetween(1, 999),
            'name' => 'Business Account '.$this->faker->randomNumber(3),
            'symbol' => '$',
        ]);
    }

    /**
     * Indicate that the payment account is for a specific team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Indicate that the payment account uses EUR currency.
     */
    public function euro(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_id' => 978, // EUR
            'symbol' => '€',
        ]);
    }

    /**
     * Indicate that the payment account uses USD currency.
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_id' => 840, // USD
            'symbol' => '$',
        ]);
    }

    /**
     * Indicate that the payment account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
