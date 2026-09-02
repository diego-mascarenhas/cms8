<?php

namespace App\Enums;

enum TeamBillingProduct: string
{
    case TokensMultiplier = 'tokens_multiplier';
    case WhatsappSend = 'whatsapp_send';
    case MailerSend = 'mailer_send';

    public function label(): string
    {
        return match ($this)
        {
            self::TokensMultiplier => 'Multiplicador de tokens',
            self::WhatsappSend => 'Envío WhatsApp',
            self::MailerSend => 'Envío mail',
        };
    }
}
