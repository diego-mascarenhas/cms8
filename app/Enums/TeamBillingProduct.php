<?php

namespace App\Enums;

enum TeamBillingProduct: string
{
    case TokensMultiplier = 'tokens_multiplier';
    case WhatsappSend = 'whatsapp_send';
    case MailerSend = 'mailer_send';
    case ProspectCredit = 'prospect_credit';
    case StorageMegabyte = 'storage_megabyte';

    public function label(): string
    {
        return match ($this)
        {
            self::TokensMultiplier => 'Multiplicador de tokens',
            self::WhatsappSend => 'Envío WhatsApp',
            self::MailerSend => 'Envío mail',
            self::ProspectCredit => 'Crédito de prospección',
            self::StorageMegabyte => 'Almacenamiento',
        };
    }
}
