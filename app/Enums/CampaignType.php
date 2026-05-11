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

    /** Singular label for tables and summaries (campaign row type). */
    public function singularLabel(): string
    {
        return match ($this)
        {
            self::Broadcasts => 'Difusión',
            self::Sequences => 'Secuencia',
            self::Events => 'Evento',
            self::ABTests => 'Prueba A/B',
        };
    }
}
