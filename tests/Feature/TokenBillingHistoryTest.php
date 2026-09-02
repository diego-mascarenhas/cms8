<?php

namespace Tests\Feature;

use App\Models\TokenBillingHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenBillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_on_or_before_returns_the_ledger_month(): void
    {
        TokenBillingHistory::factory()->create([
            'rate_month' => now()->subMonth()->startOfMonth()->toDateString(),
            'amount_per_million' => 10,
            'markup_percent' => 50,
            'sell_rate' => 15,
        ]);
        TokenBillingHistory::factory()->create([
            'rate_month' => now()->startOfMonth()->toDateString(),
            'amount_per_million' => 10,
            'markup_percent' => 80,
            'sell_rate' => 18,
        ]);

        $previous = TokenBillingHistory::latestOnOrBefore('USD', now()->subMonth()->startOfMonth()->addDays(10));
        $current = TokenBillingHistory::latestOnOrBefore('USD', now());

        $this->assertNotNull($previous);
        $this->assertNotNull($current);
        $this->assertSame('15.0000', $previous->sell_rate);
        $this->assertSame('18.0000', $current->sell_rate);
    }
}
