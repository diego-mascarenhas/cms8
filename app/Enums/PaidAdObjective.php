<?php

namespace App\Enums;

enum PaidAdObjective: string
{
    case Awareness = 'awareness';
    case Traffic = 'traffic';
    case Engagement = 'engagement';
    case Leads = 'leads';
    case Sales = 'sales';
    case AppPromotion = 'app_promotion';

    public function label(): string
    {
        return match ($this)
        {
            self::Awareness => 'Reconocimiento de marca',
            self::Traffic => 'Tráfico',
            self::Engagement => 'Interacción',
            self::Leads => 'Generación de leads',
            self::Sales => 'Ventas / Conversiones',
            self::AppPromotion => 'Promoción de app',
        };
    }
}
