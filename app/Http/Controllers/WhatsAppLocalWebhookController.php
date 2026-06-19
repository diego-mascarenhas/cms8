<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppGateway;
use App\Models\Prospect;
use App\Models\Team;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppInboundMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Transcription;

class WhatsAppLocalWebhookController extends Controller
{
    /**
     * Handle incoming message webhook from the local Node.js WhatsApp service (Baileys).
     * Normalizes the payload to Twilio-like format and reuses the WhatsApp orchestrator pipeline.
     */
    public function handleIncomingMessage(Request $request)
    {
        $secret = config('whatsapp.local.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret)
        {
            Log::warning('WhatsApp local webhook: invalid or missing secret');

            return response('Unauthorized', 401);
        }

        $rawBody = $request->getContent();
        Log::info('WhatsApp webhook raw body (pre-parse)', [
            'bytes' => strlen($rawBody),
            'body' => $this->rawWebhookBodyForLog($rawBody),
        ]);

        $payload = $request->all();

        Log::info('WhatsApp inbound payload', [
            'from' => $payload['from'] ?? null,
            'to' => $payload['to'] ?? null,
            'body_preview' => isset($payload['body']) ? \Illuminate\Support\Str::limit((string) $payload['body'], 80) : null,
            'push_name' => $payload['push_name'] ?? $payload['pushName'] ?? null,
            'team_id' => $payload['team_id'] ?? null,
            'id' => $payload['id'] ?? $payload['messageId'] ?? null,
        ]);

        if (! empty($payload['audio_base64']) && config('ai.providers.openai.key'))
        {
            $transcript = $this->transcribeIncomingAudio(
                $payload['audio_base64'],
                $payload['audio_content_type'] ?? 'audio/ogg',
            );
            if ($transcript !== null && $transcript !== '')
            {
                $payload['body'] = $transcript;
            }
        }

        $normalized = $this->normalizePayload($payload);
        if ($normalized === null)
        {
            Log::debug('WhatsApp local webhook: payload ignored (missing from/body)', ['payload' => $payload]);

            return response()->json(['error' => 'Invalid payload: from and body required'], 422);
        }

        $team = $this->resolveTeam($request);
        if ($team === null)
        {
            Log::warning('WhatsApp local webhook: unresolved team', [
                'team_id' => $payload['team_id'] ?? null,
                'to' => $payload['to'] ?? null,
                'message_id' => $payload['id'] ?? $payload['messageId'] ?? null,
            ]);

            return response()->json(['status' => 'ignored', 'reason' => 'unresolved_team'], 202);
        }

        $cleanFrom = preg_replace('/[^0-9]/', '', (string) str_replace('whatsapp:', '', $normalized['From'] ?? ''));
        if (strlen($cleanFrom) >= 8 && $team)
        {
            Prospect::captureFromWhatsApp($cleanFrom, $team->id);
        }

        $inboundMessageService = new WhatsAppInboundMessageService($team);
        $gateway = $team && config('whatsapp.driver') === 'local' && $team->getWhatsAppServiceBaseUrl() !== ''
            ? new LocalWhatsAppGateway($team->getWhatsAppServiceBaseUrl(), config('whatsapp.local.webhook_secret'), $team->id)
            : app(WhatsAppGateway::class);

        $fakeRequest = Request::create('/', 'POST', $normalized);
        Log::info('WhatsApp local webhook routed', [
            'team_id' => $team->id,
            'message_sid' => $normalized['MessageSid'] ?? null,
            'from' => $normalized['From'] ?? null,
            'to' => $normalized['To'] ?? null,
        ]);

        return $inboundMessageService->processIncomingMessage($fakeRequest, $gateway);
    }

    /**
     * Normalize Node/Baileys webhook payload to Twilio-like keys for processIncomingMessage.
     *
     * @return array<string, mixed>|null
     */
    private function normalizePayload(array $payload): ?array
    {
        $from = $payload['from'] ?? $payload['remoteJid'] ?? null;
        $to = $payload['to'] ?? $payload['ourJid'] ?? '';
        $body = $payload['body'] ?? $payload['message'] ?? $payload['text'] ?? '';
        $messageId = $payload['id'] ?? $payload['messageId'] ?? $payload['key'] ?? uniqid('wa_', true);

        if ($from === null)
        {
            return null;
        }

        $from = preg_replace('/:\d+$/', '', (string) $from);
        $to = preg_replace('/:\d+$/', '', (string) $to);
        $cleanFrom = preg_replace('/[^0-9]/', '', $from);
        $cleanTo = preg_replace('/[^0-9]/', '', $to);

        if ($cleanFrom === '' || strlen($cleanFrom) < 8)
        {
            Log::debug('WhatsApp local webhook: payload ignored (invalid or missing sender number)', ['payload' => $payload]);

            return null;
        }

        $body = (string) $body;
        if ($body === '')
        {
            $body = ' '; // allow media-only messages so conversation appears in chat list
        }

        $incomingMediaBase64 = $payload['media_base64'] ?? null;
        $incomingMediaContentType = $payload['media_content_type'] ?? null;
        $incomingMediaFileName = $payload['media_file_name'] ?? null;
        if (is_string($incomingMediaBase64) && trim($incomingMediaBase64) !== '')
        {
            $storedMediaUrl = $this->persistInboundMediaAsPublicUrl(
                $incomingMediaBase64,
                is_string($incomingMediaContentType) ? $incomingMediaContentType : null,
                is_string($incomingMediaFileName) ? $incomingMediaFileName : null,
            );
            if ($storedMediaUrl !== null)
            {
                $payload['mediaUrl'] = $storedMediaUrl;
                $payload['mediaContentType'] = is_string($incomingMediaContentType) && trim($incomingMediaContentType) !== ''
                    ? trim($incomingMediaContentType)
                    : 'application/octet-stream';
            }
        }

        $normalized = [
            'MessageSid' => is_string($messageId) ? $messageId : json_encode($messageId),
            'From' => 'whatsapp:'.$cleanFrom,
            'To' => 'whatsapp:'.$cleanTo,
            'Body' => $body,
            'NumMedia' => $payload['numMedia'] ?? $payload['hasMedia'] ?? 0,
        ];

        $mediaEntries = $this->extractMediaEntries($payload);
        if ($mediaEntries !== [])
        {
            $normalized['NumMedia'] = count($mediaEntries);
            foreach ($mediaEntries as $index => $entry)
            {
                $normalized['MediaUrl'.$index] = $entry['url'];
                $normalized['MediaContentType'.$index] = $entry['content_type'] ?? 'application/octet-stream';
            }
        }

        $profileKeys = ['push_name', 'pushName', 'profile_name'];
        foreach ($profileKeys as $key)
        {
            $raw = $payload[$key] ?? null;
            if (is_string($raw) && trim($raw) !== '')
            {
                $normalized['WaProfileName'] = mb_substr(trim($raw), 0, 255);

                break;
            }
        }

        return $normalized;
    }

    private function persistInboundMediaAsPublicUrl(string $mediaBase64, ?string $contentType = null, ?string $originalFileName = null): ?string
    {
        $decoded = base64_decode($mediaBase64, true);
        if ($decoded === false || $decoded === '')
        {
            return null;
        }

        $contentType = is_string($contentType) && trim($contentType) !== '' ? trim($contentType) : 'application/octet-stream';
        $extension = match ($contentType)
        {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => pathinfo((string) ($originalFileName ?? ''), PATHINFO_EXTENSION) ?: 'bin',
        };

        $safeExtension = preg_replace('/[^a-z0-9]/i', '', strtolower((string) $extension));
        $safeExtension = $safeExtension !== '' ? $safeExtension : 'bin';

        $relativePath = 'whatsapp-inbound/'.now()->format('Y/m/d').'/'.Str::uuid().'.'.$safeExtension;
        Storage::disk('public')->put($relativePath, $decoded);

        return url('storage/'.$relativePath);
    }

    /**
     * @return array<int, array{url: string, content_type: string}>
     */
    private function extractMediaEntries(array $payload): array
    {
        $entries = [];

        $addEntry = function (?string $url, ?string $contentType = null) use (&$entries): void
        {
            $cleanUrl = is_string($url) ? trim($url) : '';
            if ($cleanUrl === '')
            {
                return;
            }
            $entries[] = [
                'url' => $cleanUrl,
                'content_type' => is_string($contentType) && trim($contentType) !== '' ? trim($contentType) : 'application/octet-stream',
            ];
        };

        $addEntry($payload['mediaUrl'] ?? null, $payload['mediaContentType'] ?? null);
        $addEntry($payload['imageUrl'] ?? null, $payload['imageContentType'] ?? 'image/jpeg');
        $addEntry($payload['documentUrl'] ?? null, $payload['documentContentType'] ?? 'application/pdf');
        $addEntry($payload['fileUrl'] ?? null, $payload['fileContentType'] ?? null);

        if (isset($payload['media']) && is_array($payload['media']))
        {
            foreach ($payload['media'] as $mediaItem)
            {
                if (! is_array($mediaItem))
                {
                    continue;
                }
                $addEntry(
                    $mediaItem['url'] ?? $mediaItem['mediaUrl'] ?? $mediaItem['link'] ?? null,
                    $mediaItem['content_type'] ?? $mediaItem['contentType'] ?? $mediaItem['mimetype'] ?? null,
                );
            }
        }

        foreach (['image', 'document', 'file', 'attachment'] as $nestedKey)
        {
            $nested = $payload[$nestedKey] ?? null;
            if (! is_array($nested))
            {
                continue;
            }
            $addEntry(
                $nested['url'] ?? $nested['link'] ?? null,
                $nested['content_type'] ?? $nested['contentType'] ?? $nested['mimetype'] ?? null,
            );
        }

        $unique = [];
        foreach ($entries as $entry)
        {
            $unique[$entry['url']] = $entry;
        }

        return array_values($unique);
    }

    /**
     * Decode base64 audio from webhook and transcribe via Laravel AI (OpenAI). Returns transcript or null on failure.
     */
    private function transcribeIncomingAudio(string $audioBase64, string $contentType): ?string
    {
        $tmpDir = storage_path('app/temp');
        if (! is_dir($tmpDir))
        {
            @mkdir($tmpDir, 0755, true);
        }
        $extension = $contentType === 'audio/ogg' ? 'ogg' : (str_contains($contentType, 'webm') ? 'webm' : 'ogg');
        $tmpPath = $tmpDir.'/incoming_audio_'.uniqid('', true).'.'.$extension;
        $decoded = base64_decode($audioBase64, true);
        if ($decoded === false || @file_put_contents($tmpPath, $decoded) === false)
        {
            return null;
        }
        try
        {
            $file = new UploadedFile($tmpPath, 'audio.'.$extension, $contentType, 0, true);
            $transcript = (string) Transcription::fromUpload($file)->generate(provider: Lab::OpenAI);
            @unlink($tmpPath);

            return trim($transcript) !== '' ? trim($transcript) : null;
        } catch (\Throwable $e)
        {
            Log::warning('WhatsApp local webhook: transcription failed', ['error' => $e->getMessage()]);
            @unlink($tmpPath);

            return null;
        }
    }

    /**
     * Raw POST body for logs: JSON as received, with audio_base64 replaced by length only (avoids huge log lines).
     */
    private function rawWebhookBodyForLog(string $rawBody): string
    {
        if ($rawBody === '')
        {
            return '';
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
        {
            if (! empty($decoded['audio_base64']) && is_string($decoded['audio_base64']))
            {
                $decoded['audio_base64'] = '[base64 omitted, '.strlen($decoded['audio_base64']).' bytes]';
            }

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : $rawBody;
        }

        if (strlen($rawBody) > 65536)
        {
            return substr($rawBody, 0, 65536).'...[truncated, '.strlen($rawBody).' bytes total]';
        }

        return $rawBody;
    }

    private function resolveTeam(Request $request): ?Team
    {
        $payload = $request->all();
        $to = $payload['to'] ?? $payload['ourJid'] ?? null;
        if (! empty($to))
        {
            $normalized = preg_replace('/[^0-9]/', '', (string) $to);
            if ($normalized !== '')
            {
                $team = Team::findByWhatsAppNumber($normalized);
                if ($team !== null)
                {
                    return $team;
                }
            }
        }

        $teamId = $request->input('team_id');
        if ($teamId)
        {
            return Team::find($teamId);
        }

        $hash = $request->route('hash') ?? $request->input('webhook_hash');
        if ($hash)
        {
            return Team::findByWebhookHash($hash);
        }

        return null;
    }
}
