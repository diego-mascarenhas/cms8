<?php

namespace App\Support;

class SlashHomeAsset
{
    public const BASE = 'homes/slash';

    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $path === ''
            ? asset(self::BASE)
            : asset(self::BASE.'/'.$path);
    }
}
