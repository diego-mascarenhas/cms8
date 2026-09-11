<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Per-team generation counter for public shop catalog API responses.
 *
 * Uses get + forever (not {@see Cache::increment}) because the database cache
 * driver returns false and writes nothing when incrementing a missing key.
 */
class ShopCatalogApiCache
{
    public static function generationCacheKey(int $teamId): string
    {
        return 'shop_catalog_api_gen:'.$teamId;
    }

    public static function currentGeneration(int $teamId): int
    {
        return (int) Cache::get(self::generationCacheKey($teamId), 0);
    }

    public static function bumpTeam(int $teamId): void
    {
        if ($teamId <= 0)
        {
            return;
        }

        $key = self::generationCacheKey($teamId);
        $next = (int) Cache::get($key, 0) + 1;
        Cache::forever($key, $next);
    }

    public static function indexCacheKey(int $teamId, int $generation, string $slug): string
    {
        return 'shop_catalog_index:'.$teamId.':'.$generation.':'.hash('sha256', mb_strtolower(trim($slug)));
    }

    public static function productCacheKey(int $teamId, int $generation, string $slug, string $code): string
    {
        $fingerprint = mb_strtolower(trim($slug)).'|'.mb_strtolower(trim($code));

        return 'shop_catalog_product:'.$teamId.':'.$generation.':'.hash('sha256', $fingerprint);
    }
}
