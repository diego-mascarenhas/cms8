<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;

class UnimplementedWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(protected string $driver) {}

    public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
    {
        throw new \RuntimeException($this->unsupportedMessage());
    }

    public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
    {
        throw new \RuntimeException($this->unsupportedMessage());
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function getQrUrl(): ?string
    {
        return null;
    }

    public function getConnectionStatus(): ?array
    {
        return [
            'status' => 'disconnected',
        ];
    }

    private function unsupportedMessage(): string
    {
        return "WhatsApp driver [{$this->driver}] is not implemented yet.";
    }
}
