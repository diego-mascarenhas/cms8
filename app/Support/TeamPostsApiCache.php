<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Per-team generation counter for GET /api/team/posts index responses.
 *
 * Uses get + forever (not {@see Cache::increment}) because the database cache
 * driver returns false and writes nothing when incrementing a missing key.
 */
class TeamPostsApiCache
{
    public static function generationCacheKey(int $teamId): string
    {
        return 'team_posts_api_gen:'.$teamId;
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

    /**
     * Stable cache key for one team + request query fingerprint + generation.
     */
    public static function indexCacheKey(int $teamId, int $generation, Request $request): string
    {
        $parameters = $request->collect()->only([
            'post_type',
            'post_status',
            'slug',
            'parent',
            'search',
            'taxonomy',
            'term',
            'page',
            'per_page',
        ])->sortKeys()->all();

        return 'team_posts_index:'.$teamId.':'.$generation.':'.hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR));
    }
}
