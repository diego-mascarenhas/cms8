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
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Transcription;

class WhatsAppLocalWebhookController extends Controller
{
    /**
     * Handle incoming message webhook from the local Node.js WhatsApp service (Baileys).
     * Normalizes the payload to Twilio-like format and reuses TwilioService::processIncomingMessage.
     */
    public function handleIncomingMessage(Request $request)
    {
        $secret = config('whatsapp.local.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret)
        {
            Log::warning('WhatsApp local webhook: invalid or missing secret');

            return response('Unauthorized', 401);
        }

        $payload = $request->all();

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

        $normalized = [
            'MessageSid' => is_string($messageId) ? $messageId : json_encode($messageId),
            'From' => 'whatsapp:'.$cleanFrom,
            'To' => 'whatsapp:'.$cleanTo,
            'Body' => $body,
            'NumMedia' => $payload['numMedia'] ?? $payload['hasMedia'] ?? 0,
        ];

        if (! empty($payload['mediaUrl']))
        {
            $normalized['MediaUrl0'] = $payload['mediaUrl'];
            $normalized['MediaContentType0'] = $payload['mediaContentType'] ?? 'application/octet-stream';
            $normalized['NumMedia'] = 1;
        }

        return $normalized;
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

    private function resolveTeam(Request $request): ?Team
    {
        $to = $request->input('to');
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
