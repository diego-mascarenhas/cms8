<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

/**
 * Remembers the last single product shown to a WhatsApp shopper so "agregame 2" can add it.
 */
class WhatsAppLastOfferedProduct
{
    private const TTL_SECONDS = 7200;

    public static function remember(string $phone, int $teamId, int $productId): void
    {
        $key = self::cacheKey($phone, $teamId);
        if ($key === null || $productId < 1)
        {
            return;
        }

        Cache::put($key, $productId, self::TTL_SECONDS);
    }

    /**
     * Remember only when the catalog search had a single match. Several hits would guess wrong.
     *
     * @param  iterable<int|object>  $productIds
     */
    public static function rememberIfSingle(string $phone, int $teamId, iterable $productIds): void
    {
        $ids = [];
        foreach ($productIds as $item)
        {
            $id = is_object($item) ? (int) ($item->id ?? 0) : (int) $item;
            if ($id > 0)
            {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if (count($ids) === 1)
        {
            self::remember($phone, $teamId, $ids[0]);

            return;
        }

        self::forget($phone, $teamId);
    }

    public static function id(string $phone, int $teamId): ?int
    {
        $key = self::cacheKey($phone, $teamId);
        if ($key === null)
        {
            return null;
        }

        $id = Cache::get($key);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    public static function forget(string $phone, int $teamId): void
    {
        $key = self::cacheKey($phone, $teamId);
        if ($key !== null)
        {
            Cache::forget($key);
        }
    }

    private static function cacheKey(string $phone, int $teamId): ?string
    {
        $session = WhatsAppCartSessionKey::fromPhone($phone);
        if ($session === '' || $teamId < 1)
        {
            return null;
        }

        return 'whatsapp_last_offered_product:'.$teamId.':'.$session;
    }
}
