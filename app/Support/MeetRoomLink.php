<?php

namespace App\Support;

class MeetRoomLink
{
    public static function forNewRoom(): string
    {
        return self::url(self::slug());
    }

    public static function url(string $room): string
    {
        return rtrim((string) config('services.revisionalpha.meet_url'), '/').'/'.$room;
    }

    public static function slug(): string
    {
        return 'ra-'.substr(bin2hex(random_bytes(8)), 0, 12);
    }
}
