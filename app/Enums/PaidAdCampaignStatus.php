<?php

namespace App\Enums;

enum PaidAdCampaignStatus: string
{
    case Draft = 'draft';
    case Publishing = 'publishing';
    case Active = 'active';
    case Paused = 'paused';
    case Failed = 'failed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this)
        {
            self::Draft => 'Borrador',
            self::Publishing => 'Publicando',
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::Failed => 'Fallida',
            self::Archived => 'Archivada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this)
        {
            self::Draft => 'bg-label-secondary text-secondary',
            self::Publishing => 'bg-label-info text-info',
            self::Active => 'bg-label-success text-success',
            self::Paused => 'bg-label-warning text-warning',
            self::Failed => 'bg-label-danger text-danger',
            self::Archived => 'bg-label-dark text-dark',
        };
    }
}
