<?php

namespace App\Contracts;

interface WhatsAppGateway
{
    /**
     * Send a text message to a WhatsApp number.
     *
     * @param  string  $to  Recipient phone number (digits only or with +)
     * @param  string  $message  Message body
     * @param  array|null  $metadata  Optional metadata to store with the conversation
     * @param  int|null  $userId  Optional user ID for the sender (e.g. agent)
     * @return mixed Implementation-specific result (e.g. message SID or response)
     */
    public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed;

    /**
     * Send a media message (image, document, etc.) to a WhatsApp number.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $mediaPath  Public path or URL to the media file
     * @param  string|null  $caption  Optional caption
     * @return bool Success
     */
    public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool;

    /**
     * Whether this gateway is configured and ready to send.
     */
    public function isConfigured(): bool;

    /**
     * URL or path to display the QR code for linking (local driver only).
     */
    public function getQrUrl(): ?string;

    /**
     * Connection status for display (e.g. connected, disconnected, waiting_qr).
     *
     * @return array{status: string, number?: string}|null
     */
    public function getConnectionStatus(): ?array;

    /**
     * End WhatsApp session / unlink device (local driver only). Returns false for Twilio.
     */
    public function logout(): bool;
}
