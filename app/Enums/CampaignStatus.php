<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this)
        {
            self::Active => 'Activo',
            self::Scheduled => 'Programado',
            self::Sent => 'Enviado',
            self::Paused => 'Pausado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this)
        {
            self::Active => 'bg-label-success text-success',
            self::Scheduled => 'bg-label-warning text-warning',
            self::Sent => 'bg-label-info text-info',
            self::Paused => 'bg-label-secondary text-secondary',
        };
    }
}
