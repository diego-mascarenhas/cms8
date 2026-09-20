<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this)
        {
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
        };
    }

    public function requiresEmail(): bool
    {
        return $this === self::Email;
    }

    public function requiresPhone(): bool
    {
        return $this === self::WhatsApp || $this === self::Sms;
    }

    public function allowsAttachments(): bool
    {
        return $this === self::Email || $this === self::WhatsApp;
    }
}
