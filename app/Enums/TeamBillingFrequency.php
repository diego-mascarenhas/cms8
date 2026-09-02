<?php

namespace App\Enums;

enum TeamBillingFrequency: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this)
        {
            self::Monthly => 'Mensual',
            self::Weekly => 'Semanal',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Monthly->value => self::Monthly->label(),
            self::Weekly->value => self::Weekly->label(),
        ];
    }
}
