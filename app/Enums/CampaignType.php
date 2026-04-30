<?php

namespace App\Enums;

enum CampaignType: string
{
    case Broadcasts = 'broadcasts';
    case Sequences = 'sequences';
    case Events = 'events';
    case ABTests = 'ab_tests';

    public function label(): string
    {
        return match ($this)
        {
            self::Broadcasts => 'Difusiones',
            self::Sequences => 'Secuencias',
            self::Events => 'Eventos',
            self::ABTests => 'Pruebas A/B',
        };
    }
}
