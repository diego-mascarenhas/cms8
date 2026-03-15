<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $webhookSecret = null,
    ) {}

    public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
    {
        if (! $this->isConfigured())
        {
            throw new \RuntimeException('Local WhatsApp service is not configured (check WHATSAPP_LOCAL_BASE_URL).');
        }

        $cleanTo = preg_replace('/[^0-9]/', '', $to);
        $response = Http::timeout(15)
            ->post(rtrim($this->baseUrl, '/').'/send-message', [
                'to' => $cleanTo,
                'body' => $message,
            ]);

        if (! $response->successful())
        {
            Log::error('Local WhatsApp send failed', [
                'to' => $cleanTo,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Local WhatsApp send failed: '.$response->body());
        }

        $data = $response->json() ?? [];
        $messageId = $data['id'] ?? $data['messageId'] ?? null;
        // message_sid is unique: use a fallback so we never insert duplicate null
        $messageSid = $messageId !== null && $messageId !== '' ? (string) $messageId : 'wa_'.uniqid('', true);

        Conversation::create([
            'message_sid' => $messageSid,
            'channel' => 'whatsapp',
            'from' => '', // Local session number filled by Node or left empty
            'to' => $cleanTo,
            'body' => $message,
            'status' => 'sent',
            'direction' => 'outbound',
            'user_id' => $userId ?? auth()->id(),
            'metadata' => array_merge($metadata ?? [], ['source' => 'local_whatsapp']),
        ]);

        return $data;
    }

    public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
    {
        if (! $this->isConfigured())
        {
            return false;
        }

        $cleanTo = preg_replace('/[^0-9]/', '', $to);
        $url = str_starts_with($mediaPath, 'http') ? $mediaPath : url($mediaPath);

        $response = Http::timeout(30)
            ->post(rtrim($this->baseUrl, '/').'/send-media', [
                'to' => $cleanTo,
                'mediaUrl' => $url,
                'caption' => $caption,
            ]);

        return $response->successful();
    }

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl);
    }

    public function getQrUrl(): ?string
    {
        if (! $this->isConfigured())
        {
            return null;
        }

        return rtrim($this->baseUrl, '/').'/qr';
    }

    public function getConnectionStatus(): ?array
    {
        if (! $this->isConfigured())
        {
            return null;
        }

        $response = Http::timeout(5)->get(rtrim($this->baseUrl, '/').'/status');

        if (! $response->successful())
        {
            return ['status' => 'unreachable'];
        }

        return $response->json();
    }

    /**
     * End WhatsApp session / unlink device (optional; not in interface). Next connection will require QR scan.
     */
    public function logout(): bool
    {
        if (! $this->isConfigured())
        {
            return false;
        }

        $response = Http::timeout(10)->post(rtrim($this->baseUrl, '/').'/logout');

        return $response->successful();
    }
}
