<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TokenBillingHistory>
 */
class TokenBillingHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = 10.0;
        $markup = 50.0;

        return [
            'base_currency' => 'USD',
            'rate_month' => now()->startOfMonth()->toDateString(),
            'amount_per_million' => $amount,
            'markup_percent' => $markup,
            'sell_rate' => round($amount * (1 + ($markup / 100)), 4),
            'recorded_at' => now(),
        ];
    }
}
