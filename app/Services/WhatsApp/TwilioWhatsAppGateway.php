<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;

class TwilioWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        protected WhatsAppMessageService $whatsAppMessageService,
    ) {}

    public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
    {
        return $this->whatsAppMessageService->sendWhatsApp($to, $message, $metadata, $userId);
    }

    public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
    {
        return $this->whatsAppMessageService->sendWhatsAppWithMedia($to, $mediaPath, 'media', $caption);
    }

    public function isConfigured(): bool
    {
        return $this->whatsAppMessageService->isConfigured();
    }

    public function getQrUrl(): ?string
    {
        return null;
    }

    public function getConnectionStatus(): ?array
    {
        return null;
    }
}
