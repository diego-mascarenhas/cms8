<?php

namespace App\Enums;

enum CommunicationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this)
        {
            self::Pending => __('Pendiente'),
            self::Sent => __('Enviado'),
            self::Failed => __('Fallido'),
        };
    }
}
