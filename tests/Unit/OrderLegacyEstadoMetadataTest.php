<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderLegacyEstadoMetadataTest extends TestCase
{
    public function test_reads_legacy_estado_code_from_metadata(): void
    {
        $order = new Order([
            'metadata' => [
                'legacy_estado' => 7,
                'legacy_estado_label' => 'Entregado al cliente',
            ],
        ]);

        $this->assertSame(7, $order->legacy_estado_code);
        $this->assertSame('Entregado al cliente', $order->legacy_estado_label);
        $this->assertSame('bg-label-success', $order->legacy_estado_badge);
    }

    public function test_falls_back_to_legacy_status_key(): void
    {
        $order = new Order([
            'metadata' => [
                'legacy_status' => 5,
            ],
        ]);

        $this->assertSame(5, $order->legacy_estado_code);
        $this->assertSame('Solicitado/Pagado MP', $order->legacy_estado_label);
    }

    public function test_resolves_label_from_code_when_label_missing(): void
    {
        $order = new Order([
            'metadata' => [
                'legacy_estado' => 4,
            ],
        ]);

        $this->assertSame('Cancelado', $order->legacy_estado_label);
        $this->assertSame('bg-label-danger', $order->legacy_estado_badge);
    }

    public function test_returns_null_when_no_legacy_metadata(): void
    {
        $order = new Order(['metadata' => []]);

        $this->assertNull($order->legacy_estado_code);
        $this->assertNull($order->legacy_estado_label);
    }
}
