<?php

namespace App\Enums;

enum MultimediaVisibility: int
{
    case PRIVATE = 1;
    case PUBLIC = 2;

    public function label(): string
    {
        return match ($this)
        {
            self::PRIVATE => __('Private'),
            self::PUBLIC => __('Public'),
        };
    }
}
