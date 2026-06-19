<?php

namespace App\Support;

class PerformanceInsightHomeAsset
{
    public const BASE = 'homes/performance-insight';

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $path === ''
            ? asset(self::BASE)
            : asset(self::BASE.'/'.$path);
    }
}
