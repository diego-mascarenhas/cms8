<?php

namespace App\Helpers;

final class LegacyOrderNumberHelper
{
    /**
     * Compact numeric order number derived from the legacy pedido id (max 6 digits).
     * When the legacy id fits in 6 digits, it is used as-is. Larger ids use a deterministic
     * 6-digit code so the global {@see \App\Models\Order::$order_number} unique constraint stays safe.
     */
    public static function fromLegacyPedidoId(int $teamId, int $legacyOrderId): string
    {
        $legacyOrderId = abs($legacyOrderId);

        if ($legacyOrderId <= 999999)
        {
            return (string) $legacyOrderId;
        }

        return sprintf('%06d', crc32((string) $teamId.'-'.$legacyOrderId) % 1000000);
    }
}
