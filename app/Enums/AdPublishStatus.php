<?php

namespace App\Enums;

enum AdPublishStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this)
        {
            self::Pending => 'Pendiente',
            self::Publishing => 'Publicando',
            self::Published => 'Publicada',
            self::Failed => 'Error',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this)
        {
            self::Pending => 'bg-label-secondary text-secondary',
            self::Publishing => 'bg-label-info text-info',
            self::Published => 'bg-label-success text-success',
            self::Failed => 'bg-label-danger text-danger',
        };
    }
}
