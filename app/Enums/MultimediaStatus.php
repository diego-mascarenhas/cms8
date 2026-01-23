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
            self::INACTIVE => __('app.Inactive'),
            self::ACTIVE => __('app.Active'),
        };
    }
}
