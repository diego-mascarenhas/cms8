<?php

namespace App\Helpers;

/**
 * Maps `tienda_pedidos.estado` values from legacy CMS to Humano order statuses.
 *
 * Mapping source: `con_car_pedidos_estados` catalog provided by project data migration.
 */
final class LegacyTiendaPedidoEstadoHelper
{
    /**
     * Spanish labels as defined in legacy `con_car_pedidos_estados`.
     *
     * @var array<int, string>
     */
    private const LEGACY_LABELS = [
        0 => 'Eliminado',
        1 => 'Ingresado',
        2 => 'Pagado',
        3 => 'Enviado',
        4 => 'Recibido',
        5 => 'Pendiente',
        6 => 'Regalado',
        7 => 'Bonificado',
        8 => 'Cancelado',
    ];

    /**
     * @return array{payment_status: string, delivery_status: string}
     */
    public static function toHumanoOrderStatuses(int|string|null $legacyEstado): array
    {
        $code = self::normalizeLegacyEstadoCode($legacyEstado);

        return match ($code)
        {
            0, 8 => ['payment_status' => 'cancelled', 'delivery_status' => 'cancelled'],
            1, 5 => ['payment_status' => 'pending', 'delivery_status' => 'processing'],
            2 => ['payment_status' => 'paid', 'delivery_status' => 'processing'],
            3 => ['payment_status' => 'paid', 'delivery_status' => 'out_for_delivery'],
            4, 6, 7 => ['payment_status' => 'paid', 'delivery_status' => 'delivered'],
            default => ['payment_status' => 'pending', 'delivery_status' => 'processing'],
        };
    }

    public static function legacyLabel(int|string|null $legacyEstado): ?string
    {
        $code = self::normalizeLegacyEstadoCode($legacyEstado);

        return self::LEGACY_LABELS[$code] ?? null;
    }

    private static function normalizeLegacyEstadoCode(int|string|null $legacyEstado): int
    {
        if ($legacyEstado === null || $legacyEstado === '')
        {
            return -1;
        }

        if (is_string($legacyEstado))
        {
            $legacyEstado = (int) $legacyEstado;
        }

        return $legacyEstado;
    }
}
