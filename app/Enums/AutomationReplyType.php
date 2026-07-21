<?php

namespace App\Enums;

enum AutomationReplyType: string
{
    case YesNo = 'yes_no';
    case Choice = 'choice';
    case FreeText = 'free_text';
    case Email = 'email';
    case Phone = 'phone';
    case Date = 'date';
    case Fallback = 'fallback';

    public function label(): string
    {
        return match ($this)
        {
            self::YesNo => __('Sí / No'),
            self::Choice => __('Opción'),
            self::FreeText => __('Texto libre'),
            self::Email => __('Email'),
            self::Phone => __('Teléfono'),
            self::Date => __('Fecha'),
            self::Fallback => __('Cualquier otra respuesta'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
