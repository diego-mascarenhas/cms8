<?php

namespace Database\Factories;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grossAmount = $this->faker->randomFloat(2, 100, 5000);
        $discount = $this->faker->randomFloat(2, 0, $grossAmount * 0.2);  // Max 20% discount
        $totalAmount = $grossAmount - $discount;

        // Sometimes the invoice is fully paid, sometimes partially, sometimes unpaid
        $paymentScenario = $this->faker->randomElement(['paid', 'partial', 'unpaid']);

        switch ($paymentScenario)
        {
            case 'paid':
                $balance = 0;
                break;
            case 'partial':
                $balance = $this->faker->randomFloat(2, $totalAmount * 0.1, $totalAmount * 0.8);
                break;
            case 'unpaid':
                $balance = $totalAmount;
                break;
        }

        return [
            'enterprise_id' => Enterprise::factory(),
            'billing_id' => $this->faker->optional()->randomNumber(5),
            'type_id' => function ()
            {
                return InvoiceType::inRandomOrder()->first()?->id ?? 1;
            },
            'operation' => $this->faker->randomElement(['buy', 'sell']),
            'number' => $this->faker->unique()->numerify('INV-####-####'),
            'date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'due_date' => function (array $attributes)
            {
                return $this->faker->dateTimeBetween($attributes['date'], '+60 days');
            },
            'gross_amount' => $grossAmount,
            'discount' => $discount,
            'total_amount' => $totalAmount,
            'balance' => $balance,
            'status' => $this->faker->randomElement([1, 2, 3, 4, 5, 6, 7, 8]),  // Based on model status labels
        ];
    }

    /**
     * Configure the factory for team_id 1
     */
    public function forTeam1(): static
    {
        return $this->state(function (array $attributes)
        {
            return [
                'enterprise_id' => Enterprise::where('team_id', 1)->inRandomOrder()->first()?->id
                    ?? Enterprise::factory()->create(['team_id' => 1])->id,
            ];
        });
    }

    /**
     * Paid invoice state
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes)
        {
            return [
                'balance' => 0,
                'status' => 1,  // Print status (assuming paid)
            ];
        });
    }

    /**
     * Unpaid invoice state
     */
    public function unpaid(): static
    {
        return $this->state(function (array $attributes)
        {
            return [
                'balance' => $attributes['total_amount'] ?? $this->faker->randomFloat(2, 100, 2000),
                'status' => 2,  // Printed status (assuming unpaid)
            ];
        });
    }

    /**
     * Recent invoice (last 6 months)
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes)
        {
            $date = $this->faker->dateTimeBetween('-6 months', 'now');

            return [
                'date' => $date,
                'due_date' => $this->faker->dateTimeBetween($date, '+30 days'),
            ];
        });
    }

    /**
     * Overdue invoice
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes)
        {
            $date = $this->faker->dateTimeBetween('-1 year', '-1 month');

            return [
                'date' => $date,
                'due_date' => $this->faker->dateTimeBetween($date, '-1 week'),
                'balance' => $attributes['total_amount'] ?? $this->faker->randomFloat(2, 100, 2000),
                'status' => 2,  // Printed but unpaid
            ];
        });
    }

    /**
     * High value invoice
     */
    public function highValue(): static
    {
        return $this->state(function (array $attributes)
        {
            $grossAmount = $this->faker->randomFloat(2, 2000, 10000);
            $discount = $this->faker->randomFloat(2, 0, $grossAmount * 0.1);
            $totalAmount = $grossAmount - $discount;

            return [
                'gross_amount' => $grossAmount,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'balance' => $this->faker->randomFloat(2, 0, $totalAmount),
            ];
        });
    }
}
