<?php

namespace App\Enums;

enum ShoppingCartChannel: string
{
    case WhatsApp = 'whatsapp';
    case PublicShop = 'public_shop';

    public function label(): string
    {
        return match ($this)
        {
            self::WhatsApp => 'WhatsApp',
            self::PublicShop => __('Tienda web'),
        };
    }
}
