<?php

namespace App\Support;

class HumanoHomeAsset
{
    public const BASE = 'homes/humano';

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $path === ''
            ? asset(self::BASE)
            : asset(self::BASE.'/'.$path);
    }
}
