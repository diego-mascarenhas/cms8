<?php

namespace App\Helpers;

final class LegacyOrderNumberHelper
{
    /**
     * Deterministic alphanumeric code derived from team + legacy pedido id.
     *
     * Format example: `LG5SN-2B9` where both segments are base36 (uppercase).
     */
    public static function fromLegacyPedidoId(int $teamId, int $legacyOrderId): string
    {
        $normalizedTeamId = max(0, $teamId);
        $normalizedLegacyOrderId = abs($legacyOrderId);

        $teamSegment = strtoupper(base_convert((string) $normalizedTeamId, 10, 36));
        $orderSegment = strtoupper(base_convert((string) $normalizedLegacyOrderId, 10, 36));

        return 'LG'.$teamSegment.'-'.$orderSegment;
    }
}
