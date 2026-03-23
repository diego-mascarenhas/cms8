<?php

namespace Tests\Unit;

use App\Helpers\LegacyTiendaPedidoEstadoHelper;
use PHPUnit\Framework\TestCase;

class LegacyTiendaPedidoEstadoHelperTest extends TestCase
{
    public function test_maps_legacy_estados_from_con_car_pedidos_estados(): void
    {
        $this->assertSame(
            ['payment_status' => 'pending', 'delivery_status' => 'processing'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(1),
        );
        $this->assertSame(
            ['payment_status' => 'paid', 'delivery_status' => 'processing'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(2),
        );
        $this->assertSame(
            ['payment_status' => 'paid', 'delivery_status' => 'out_for_delivery'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(3),
        );
        $this->assertSame(
            ['payment_status' => 'paid', 'delivery_status' => 'delivered'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(4),
        );
        $this->assertSame(
            ['payment_status' => 'pending', 'delivery_status' => 'processing'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(5),
        );
        $this->assertSame(
            ['payment_status' => 'paid', 'delivery_status' => 'delivered'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(6),
        );
        $this->assertSame(
            ['payment_status' => 'paid', 'delivery_status' => 'delivered'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(7),
        );
        $this->assertSame(
            ['payment_status' => 'cancelled', 'delivery_status' => 'cancelled'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(8),
        );
    }

    public function test_returns_spanish_label_for_known_estado(): void
    {
        $this->assertSame('Recibido', LegacyTiendaPedidoEstadoHelper::legacyLabel(4));
        $this->assertNull(LegacyTiendaPedidoEstadoHelper::legacyLabel(99));
    }

    public function test_unknown_or_empty_defaults_to_pending_processing(): void
    {
        $this->assertSame(
            ['payment_status' => 'pending', 'delivery_status' => 'processing'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(null),
        );
        $this->assertSame(
            ['payment_status' => 'pending', 'delivery_status' => 'processing'],
            LegacyTiendaPedidoEstadoHelper::toHumanoOrderStatuses(999),
        );
    }
}
