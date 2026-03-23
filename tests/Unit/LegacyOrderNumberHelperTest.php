<?php

namespace Tests\Unit;

use App\Helpers\LegacyOrderNumberHelper;
use PHPUnit\Framework\TestCase;

class LegacyOrderNumberHelperTest extends TestCase
{
    public function test_returns_string_id_when_legacy_id_fits_in_six_digits(): void
    {
        $this->assertSame('1', LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 1));
        $this->assertSame('99999', LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 99999));
        $this->assertSame('999999', LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 999999));
    }

    public function test_returns_six_digit_code_when_legacy_id_exceeds_999999(): void
    {
        $code = LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 1000000);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame(
            LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 1000000),
            LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 1000000),
        );
    }

    public function test_team_id_affects_code_when_legacy_id_exceeds_999999(): void
    {
        $a = LegacyOrderNumberHelper::fromLegacyPedidoId(7507, 1000000);
        $b = LegacyOrderNumberHelper::fromLegacyPedidoId(7508, 1000000);

        $this->assertNotSame($a, $b);
    }

    public function test_uses_absolute_value_for_negative_ids(): void
    {
        $this->assertSame('42', LegacyOrderNumberHelper::fromLegacyPedidoId(1, -42));
    }
}
