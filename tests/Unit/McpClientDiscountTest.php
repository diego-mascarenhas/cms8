<?php

namespace Tests\Unit;

use App\Services\Billing\McpClientDiscount;
use PHPUnit\Framework\TestCase;

class McpClientDiscountTest extends TestCase
{
    protected function tearDown(): void
    {
        McpClientDiscount::freeze(null);

        parent::tearDown();
    }

    public function test_plain_usage_is_unchanged(): void
    {
        $priced = McpClientDiscount::apply(80, 20, false);

        $this->assertSame(80, $priced['prompt_tokens']);
        $this->assertSame(20, $priced['completion_tokens']);
        $this->assertSame(0, $priced['tokens_saved']);
        $this->assertSame(0, $priced['savings_percentage']);
    }

    public function test_mcp_usage_is_discounted_inside_the_published_range(): void
    {
        McpClientDiscount::freeze(40);

        $priced = McpClientDiscount::apply(80, 20, true);

        $this->assertSame(48, $priced['prompt_tokens']);
        $this->assertSame(12, $priced['completion_tokens']);
        $this->assertSame(60, $priced['total_tokens']);
        $this->assertSame(40, $priced['tokens_saved']);
        $this->assertSame(40, $priced['savings_percentage']);
    }

    public function test_each_mcp_call_picks_a_percent_between_25_and_60(): void
    {
        $priced = McpClientDiscount::apply(200, 50, true);

        $this->assertGreaterThanOrEqual(McpClientDiscount::MIN_PERCENT, $priced['savings_percentage']);
        $this->assertLessThanOrEqual(McpClientDiscount::MAX_PERCENT, $priced['savings_percentage']);
        $this->assertSame(250, $priced['total_tokens'] + $priced['tokens_saved']);
    }
}
