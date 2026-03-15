<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Models\Conversation;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $webhookSecret = null,
        protected ?int $teamId = null,
    ) {}

    private function statusUrl(): string
    {
        $url = rtrim($this->baseUrl, '/').'/status';
        if ($this->teamId !== null)
        {
            $url .= (str_contains($url, '?') ? '&' : '?').'team_id='.$this->teamId;
        }

        return $url;
    }

    private function sendBody(array $payload): array
    {
        if ($this->teamId !== null)
        {
            $payload['team_id'] = $this->teamId;
        }

        return $payload;
    }

    public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
    {
        if (! $this->isConfigured())
        {
            throw new \RuntimeException('Local WhatsApp service is not configured (check WHATSAPP_LOCAL_BASE_URL).');
        }

        $cleanTo = preg_replace('/[^0-9]/', '', $to);
        $response = Http::timeout(15)
            ->post(rtrim($this->baseUrl, '/').'/send-message', $this->sendBody([
                'to' => $cleanTo,
                'body' => $message,
            ]));

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

        $cleanFrom = '';
        if ($this->teamId !== null)
        {
            $teamNumber = Team::find($this->teamId)?->getWhatsAppFrom();
            $cleanFrom = $teamNumber !== null && $teamNumber !== '' ? preg_replace('/[^0-9]/', '', (string) $teamNumber) : '';
        }

        Conversation::create([
            'message_sid' => $messageSid,
            'channel' => 'whatsapp',
            'from' => $cleanFrom,
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
            ->post(rtrim($this->baseUrl, '/').'/send-media', $this->sendBody([
                'to' => $cleanTo,
                'mediaUrl' => $url,
                'caption' => $caption,
            ]));

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

        $url = rtrim($this->baseUrl, '/').'/qr';
        if ($this->teamId !== null)
        {
            $url .= '?team_id='.$this->teamId;
        }

        return $url;
    }

    public function getConnectionStatus(): ?array
    {
        if (! $this->isConfigured())
        {
            return null;
        }

        $response = Http::timeout(5)->get($this->statusUrl());

        if (! $response->successful())
        {
            return ['status' => 'unreachable'];
        }

        return $response->json();
    }
}
