<?php

namespace App\Enums;

enum ServerStatus: int
{
    case Unknown = 0;
    case Active = 1;
    case Inactive = 2;
    case Maintenance = 3;
    case Error = 4;

    public function name(): string
    {
        return match ($this)
        {
            self::Unknown => 'Desconocido',
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Maintenance => 'Mantenimiento',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this)
        {
            self::Unknown => 'secondary',
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Maintenance => 'info',
            self::Error => 'danger',
        };
    }
}
