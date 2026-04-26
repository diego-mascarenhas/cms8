<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

/**
 * Admin-only slash commands for proactive WhatsApp outreach (same engine as {@see HumanoSendDemoWhatsAppCommand}).
 *
 * Supported (trimmed, case-insensitive command prefix):
 * - `/enviar-demo +34…` or `/send-demo +34…` → keyword demo + destination phone
 * - `/enviar-flujo cobrar +34…` or `/send-flow cobrar +34…` → keyword + phone (same rules as keyword+phone parsing)
 */
class AdminProactiveOutreachSlashDispatcher
{
    public function __construct(
        protected AdminProactiveWhatsAppOutreachService $outreach,
        protected AdminProactiveWhatsAppOutreachExecutor $executor,
    )
    {
    }

    /**
     * Web assistant chat (Humano): only in own thread, no audio.
     *
     * @return array<string, mixed>|null Null if the message is not a recognized slash outreach command.
     */
    public function tryWebAssistantMessage(
        string $message,
        User $actor,
        int $teamId,
        User $contextUser,
        bool $hasAudio,
    ): ?array {
        if ($hasAudio || $actor->id !== $contextUser->id)
        {
            return null;
        }

        $t = trim($message);
        if (! $this->isProactiveOutreachSlash($t))
        {
            return null;
        }

        $parsed = $this->parseSlashBody($t);
        if ($parsed === null)
        {
            return [
                'success' => false,
                'message' => __('Formato: /enviar-demo +34… o /enviar-flujo clave +34… (teléfono al final).'),
                '_http_status' => 422,
            ];
        }

        $team = $actor->currentTeam;
        if (! $team || (int) $team->id !== $teamId)
        {
            return [
                'success' => false,
                'message' => __('No team context.'),
                '_http_status' => 403,
            ];
        }

        return $this->executorResultToChatPayload(
            $this->executor->execute($actor, $team, $parsed['keyword'], $parsed['phone_digits'], trim($message)),
            $hasAudio,
            trim($message),
        );
    }

    /**
     * Inbound WhatsApp to the team number: admin/root on that team can trigger outreach without going through contact assistant toggles.
     *
     * @return array{whatsapp_reply: string}|null Null if not a slash outreach command.
     */
    public function tryWhatsAppInbound(string $body, User $contextUser, int $assistantTeamId): ?array
    {
        $t = trim($body);
        if (! $this->isProactiveOutreachSlash($t))
        {
            return null;
        }

        $parsed = $this->parseSlashBody($t);
        if ($parsed === null)
        {
            return [
                'whatsapp_reply' => __('Formato: /enviar-demo +34… o /enviar-flujo clave +34… (teléfono al final).'),
            ];
        }

        if (! $contextUser->hasAnyRole(['admin', 'root']))
        {
            return [
                'whatsapp_reply' => __('Solo administradores pueden usar /enviar-demo o /enviar-flujo.'),
            ];
        }

        $team = Team::withoutGlobalScopes()->find($assistantTeamId);
        if (! $team || ! $contextUser->belongsToTeam($team))
        {
            return [
                'whatsapp_reply' => __('No team context.'),
            ];
        }

        $result = $this->executor->execute($contextUser, $team, $parsed['keyword'], $parsed['phone_digits'], trim($body));

        if (! ($result['success'] ?? false))
        {
            return [
                'whatsapp_reply' => (string) ($result['message'] ?? __('No se pudo completar el envío.')),
            ];
        }

        return [
            'whatsapp_reply' => (string) ($result['response'] ?? __('Listo.')),
        ];
    }

    public function isProactiveOutreachSlash(string $t): bool
    {
        return $t !== '' && preg_match('#^/(?:enviar-demo|send-demo|enviar-flujo|send-flow)\b#iu', $t) === 1;
    }

    /**
     * @return array{keyword: string, phone_digits: string}|null Null when not a recognized slash outreach command or phone cannot be parsed.
     */
    public function parseSlashBody(string $t): ?array
    {
        if (! $this->isProactiveOutreachSlash($t))
        {
            return null;
        }

        if (preg_match('#^/(?:enviar-demo|send-demo)\s*(.+)$#iu', $t, $m))
        {
            $synthetic = 'demo '.trim($m[1]);
            $p = $this->outreach->parseKeywordAndPhone($synthetic);

            return $p !== null ? ['keyword' => $p['keyword'], 'phone_digits' => $p['phone_digits']] : null;
        }

        if (preg_match('#^/(?:enviar-flujo|send-flow)\s*(.+)$#iu', $t, $m))
        {
            $p = $this->outreach->parseKeywordAndPhone(trim($m[1]));

            return $p !== null ? ['keyword' => $p['keyword'], 'phone_digits' => $p['phone_digits']] : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function executorResultToChatPayload(array $result, bool $hasAudio, string $originalMessage): array
    {
        $http = (int) ($result['_http_status'] ?? 200);
        unset($result['_http_status']);

        if (($result['success'] ?? false) === true)
        {
            $payload = [
                'success' => true,
                'response' => $result['response'] ?? '',
                'action_performed' => $result['action_performed'] ?? 'proactive_whatsapp_outreach',
                'routing_key' => $result['routing_key'] ?? null,
                'phone' => $result['phone'] ?? null,
                'sent_via_tool' => $result['sent_via_tool'] ?? false,
                '_http_status' => 200,
            ];
            if ($hasAudio)
            {
                $payload['transcript'] = $originalMessage;
            }

            return $payload;
        }

        return array_merge($result, ['_http_status' => $http >= 400 ? $http : 422]);
    }
}
