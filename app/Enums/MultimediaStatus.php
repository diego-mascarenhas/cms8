<?php

namespace App\Enums;

enum MultimediaStatus: int
{
    case UNCLASSIFIED = 0;
    case INACTIVE = 1;
    case ACTIVE = 2;

    public function label(): string
    {
        return match ($this)
        {
            self::UNCLASSIFIED => __('app.Unclassified'),
            self::INACTIVE => __('app.Inactive'),
            self::ACTIVE => __('app.Active'),
        };
    }
}
