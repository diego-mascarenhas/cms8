<?php

namespace App\Enums;

enum TeamBillingProduct: string
{
    case TokensMultiplier = 'tokens_multiplier';
    case WhatsappSend = 'whatsapp_send';
    case MailerSend = 'mailer_send';
    case ProspectCredit = 'prospect_credit';
    case StorageMegabyte = 'storage_megabyte';
    case TranscriptionMinute = 'transcription_minute';
    case ElevenlabsVoiceThousand = 'elevenlabs_voice_thousand';
    case PaidAdVideo = 'paid_ad_video';

    public function label(): string
    {
        return match ($this)
        {
            self::TokensMultiplier => 'Multiplicador de tokens',
            self::WhatsappSend => 'Envío WhatsApp',
            self::MailerSend => 'Envío mail',
            self::ProspectCredit => 'Crédito de prospección',
            self::StorageMegabyte => 'Almacenamiento',
            self::TranscriptionMinute => 'Transcripción',
            self::ElevenlabsVoiceThousand => 'Voz ElevenLabs',
            self::PaidAdVideo => 'Vídeo de anuncio',
        };
    }
}
