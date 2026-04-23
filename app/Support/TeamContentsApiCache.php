<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Per-team generation counter for GET /api/team/contents index responses.
 * Works with any Laravel cache store that supports {@see Cache::increment()} (file, database, redis, …).
 */
class TeamContentsApiCache
{
    public static function generationCacheKey(int $teamId): string
    {
        return 'team_contents_api_gen:'.$teamId;
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

        Cache::increment(self::generationCacheKey($teamId));
    }

    /**
     * Stable cache key for one team + request query fingerprint + generation.
     */
    public static function indexCacheKey(int $teamId, int $generation, Request $request): string
    {
        $parameters = $request->collect()->only([
            'section_slug',
            'section_category_id',
            'category_id',
            'status',
            'featured',
            'search',
            'locale',
            'page',
            'per_page',
        ])->sortKeys()->all();

        return 'team_contents_index:'.$teamId.':'.$generation.':'.hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR));
    }
}
