<?php

namespace App\Enums;

enum MessageStatus: int
{
    case INACTIVE = 1;
    case ACTIVE = 2;

    public function label(): string
    {
        return match($this) {
            self::INACTIVE => 'Inactive',
            self::ACTIVE => 'Active',
        };
    }
} 