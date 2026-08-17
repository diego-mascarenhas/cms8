<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\AssistantSubscriptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantSubscriptionService::class)]
class AssistantSubscriptionPeriodTest extends TestCase
{
    public function test_reads_period_from_subscription_root(): void
    {
        [$start, $end] = AssistantSubscriptionService::periodTimestampsFromStripeSubscription([
            'current_period_start' => 1755400000,
            'current_period_end' => 1757992000,
        ]);

        $this->assertSame(1755400000, $start);
        $this->assertSame(1757992000, $end);
    }

    public function test_reads_period_from_subscription_item_when_root_is_missing(): void
    {
        [$start, $end] = AssistantSubscriptionService::periodTimestampsFromStripeSubscription([
            'items' => [
                'data' => [
                    [
                        'current_period_start' => 1755400000,
                        'current_period_end' => 1757992000,
                    ],
                ],
            ],
        ]);

        $this->assertSame(1755400000, $start);
        $this->assertSame(1757992000, $end);
    }

    public function test_prefers_subscription_period_over_item_period(): void
    {
        [$start, $end] = AssistantSubscriptionService::periodTimestampsFromStripeSubscription([
            'current_period_start' => 100,
            'current_period_end' => 200,
            'items' => [
                'data' => [
                    [
                        'current_period_start' => 1,
                        'current_period_end' => 2,
                    ],
                ],
            ],
        ]);

        $this->assertSame(100, $start);
        $this->assertSame(200, $end);
    }

    public function test_returns_nulls_when_period_is_missing(): void
    {
        [$start, $end] = AssistantSubscriptionService::periodTimestampsFromStripeSubscription([]);

        $this->assertNull($start);
        $this->assertNull($end);
    }
}
