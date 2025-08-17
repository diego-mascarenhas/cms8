<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
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
        $demoTeam = $this->getDemoTeam();

        return [
            'team_id' => $demoTeam->id,
            'enterprise_id' => Enterprise::where('team_id', $demoTeam->id)->inRandomOrder()->first()?->id,
            'transaction_type' => $this->faker->randomElement(['I', 'E']), // Income or Expense
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'invoice_id' => null, // Can be set via state
            'account_id' => PaymentAccount::where('team_id', $demoTeam->id)->inRandomOrder()->first()?->id ?? PaymentAccount::factory()->create(['team_id' => $demoTeam->id]),
            'type_id' => $this->faker->numberBetween(1, 7), // Updated to match new payment types
            'amount' => $this->faker->randomFloat(2, 10.00, 10000.00),
            'remarks' => $this->faker->optional()->sentence(),
            'status' => $this->faker->randomElement([1, 2]), // 1 = active, 2 = processed
        ];
    }

    /**
     * Indicate that the payment is an income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'I',
            'amount' => $this->faker->randomFloat(2, 100.00, 10000.00),
        ]);
    }

    /**
     * Indicate that the payment is an expense.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'E',
            'amount' => $this->faker->randomFloat(2, 10.00, 5000.00),
        ]);
    }

    /**
     * Indicate that the payment is linked to an invoice.
     */
    public function withInvoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => Invoice::factory(),
        ]);
    }

    /**
     * Indicate that the payment is processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 2,
        ]);
    }

    /**
     * Indicate that the payment is for a specific team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Indicate large payment amount.
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->randomFloat(2, 5000.00, 50000.00),
        ]);
    }

    /**
     * Indicate small payment amount.
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->randomFloat(2, 1.00, 100.00),
        ]);
    }
}
