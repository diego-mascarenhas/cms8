<?php

namespace App\Support;

class CmsHomeAsset
{
    public const BASE = 'homes/cms';

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $path === ''
            ? asset(self::BASE)
            : asset(self::BASE.'/'.$path);
    }
}
