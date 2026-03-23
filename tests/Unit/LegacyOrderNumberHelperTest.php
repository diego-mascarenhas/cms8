<?php

namespace Tests\Unit;

use App\Helpers\LegacyOrderNumberHelper;
use PHPUnit\Framework\TestCase;

class LegacyOrderNumberHelperTest extends TestCase
{
    public function test_generates_alphanumeric_code_with_expected_prefix(): void
    {
        $code = LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 99999);

        $this->assertStringStartsWith('LG', $code);
        $this->assertMatchesRegularExpression('/^LG[A-Z0-9]+-[A-Z0-9]+$/', $code);
    }

    public function test_is_deterministic_for_same_team_and_order_ids(): void
    {
        $this->assertSame(
            LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 281487),
            LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 281487),
        );
    }

    public function test_team_id_affects_generated_code(): void
    {
        $a = LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 281487);
        $b = LegacyOrderNumberHelper::fromLegacyPedidoId(7508, 281487);

        $this->assertNotSame($a, $b);
    }

    public function test_uses_absolute_value_for_negative_ids(): void
    {
        $this->assertSame(
            LegacyOrderNumberHelper::fromLegacyPedidoId(1, 42),
            LegacyOrderNumberHelper::fromLegacyPedidoId(1, -42),
        );
    }
}
