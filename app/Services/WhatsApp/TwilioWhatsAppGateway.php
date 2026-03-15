<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Services\TwilioService;

class TwilioWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        protected TwilioService $twilioService,
    ) {}

    public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
    {
        return $this->twilioService->sendWhatsApp($to, $message, $metadata, $userId);
    }

    public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
    {
        $this->twilioService->sendWhatsAppWithMedia($to, $mediaPath, 'media');

        return true;
    }

    public function isConfigured(): bool
    {
        return $this->twilioService->isConfigured();
    }

    public function getQrUrl(): ?string
    {
        return null;
    }

    public function getConnectionStatus(): ?array
    {
        return null;
    }

    /**
     * No-op for Twilio driver. Present for interface compatibility when WhatsAppGateway
     * still declares logout() (e.g. older deployments).
     */
    public function logout(): bool
    {
        return $this->twilioService->logout();
    }
}
