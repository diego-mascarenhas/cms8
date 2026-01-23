<?php

namespace App\Enums;

enum MultimediaStatus: int
{
    case INACTIVE = 1;
    case ACTIVE = 2;

    public function label(): string
    {
        return match ($this)
        {
            self::INACTIVE => __('Inactive'),
            self::ACTIVE => __('Active'),
        };
    }
}
