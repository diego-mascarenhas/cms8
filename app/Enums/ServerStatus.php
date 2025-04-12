<?php

namespace App\Enums;

enum ServerStatus: int
{
    case INACTIVE = 1;
    case ACTIVE = 2;
    case MAINTENANCE = 3;
    case ERROR = 4;

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::MAINTENANCE => 'Maintenance',
            self::ERROR => 'Error',
        };
    }
} 