<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Helpers\WhatsAppCartSessionKey;
use App\Helpers\WhatsAppOutboundText;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppCustomerServiceWindow;
use Illuminate\Support\Facades\Log;

/**
 * Runs admin proactive WhatsApp outreach (forced team flow + opening message) for a keyword and phone.
 * Used from Artisan, web assistant slash commands, and WhatsApp slash commands ({@see AdminProactiveOutreachSlashDispatcher}).
 */
class AdminProactiveWhatsAppOutreachExecutor
{
    public function __construct(
        protected AdminProactiveWhatsAppOutreachService $outreach,
        protected ChatAssistantReplyService $replyService,
        protected AgentConversationContextService $contextService,
    ) {}

    /**
     * @return array<string, mixed> success payload or error with _http_status
     */
    public function execute(
        User $actor,
        Team $team,
        string $keyword,
        string $phoneDigits,
        string $persistedUserMessage,
    ): array {
        $teamId = (int) $team->id;

        if (! $actor->hasAnyRole(['admin', 'root']))
        {
            return [
                'success' => false,
                'message' => 'Only admin or root may send proactive WhatsApp outreach.',
                '_http_status' => 403,
            ];
        }

        if (! $actor->belongsToTeam($team))
        {
            return [
                'success' => false,
                'message' => 'The user is not a member of this team.',
                '_http_status' => 403,
            ];
        }

        $digits = preg_replace('/[^0-9]/', '', $phoneDigits) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 15)
        {
            return [
                'success' => false,
                'message' => 'Invalid phone number (expected 10–15 digits).',
                '_http_status' => 422,
            ];
        }

        $routingKey = $this->outreach->resolveRoutingKeyForKeyword($teamId, trim($keyword));
        if ($routingKey === null)
        {
            $hints = Prompt::forTeam($teamId)
                ->active()
                ->with('module')
                ->where('section_key', '!=', 'general')
                ->orderBy('order')
                ->limit(25)
                ->get()
                ->map(fn (Prompt $p) => $this->outreach->routingKeyForPrompt($p))
                ->implode(', ');

            $hintText = $hints !== ''
                ? ' Active flows (examples): '.$hints
                : '';

            return [
                'success' => false,
                'message' => 'No active prompt matches keyword «'.trim($keyword).'».'.$hintText,
                '_http_status' => 422,
            ];
        }

        if (! app(WhatsAppCustomerServiceWindow::class)->isOpen($digits))
        {
            return [
                'success' => false,
                'message' => __('whatsapp.send.error.session_window_closed'),
                '_http_status' => 422,
            ];
        }

        $gateway = $this->gatewayForTeam($team);

        if (! $gateway->isConfigured())
        {
            return [
                'success' => false,
                'message' => 'WhatsApp is not configured for this team.',
                '_http_status' => 422,
            ];
        }

        $sessionPhone = WhatsAppCartSessionKey::fromPhone($digits);
        $history = $this->contextService->getHistoryForPrompt($actor->id, AgentConversationContextService::DEFAULT_HISTORY_LIMIT);

        $operatorPrompt = __('[Operador Humano] Iniciá conversación proactiva por WhatsApp: en esta misma respuesta invocá send_whatsapp_message como máximo una vez, con el único mensaje de apertura que debe ver el cliente. No hagas un segundo envío ni uses la herramienta para confirmaciones al operador (esas van solo en tu texto de respuesta al chat, no a WhatsApp). El número de destino ya está autorizado en esta sesión: no pidas número. Seguí el flujo del equipo para el saludo; sé breve. Si no podés usar la herramienta, el sistema reenviará tu texto plano al cliente: escribí solo lo que debe leer el cliente, sin meta-aclaraciones ni pedidos de teléfono.');

        $userMessageForModel = trim($keyword)."\n\n".$operatorPrompt;

        $replyResponse = $this->replyService->getReply(
            $userMessageForModel,
            $history,
            $teamId,
            true,
            $actor->id,
            $sessionPhone,
            $routingKey,
            null,
            false,
            \App\Services\Assistant\AssistantActorContextService::CHANNEL_WHATSAPP,
            true,
        );

        if (! ($replyResponse['success'] ?? false))
        {
            return [
                'success' => false,
                'message' => $replyResponse['message'] ?? 'The assistant could not generate the opening message.',
                '_http_status' => 502,
            ];
        }

        $assistantText = trim((string) ($replyResponse['text'] ?? ''));
        $sentViaTool = $this->toolCallsIncludeSendWhatsApp($replyResponse['tool_calls'] ?? []);

        if (! $sentViaTool && $assistantText !== '')
        {
            try
            {
                $gateway->sendMessage($digits, WhatsAppOutboundText::stripInternalQaMarkers(WhatsAppOutboundText::sanitize($assistantText)), [
                    'source' => 'admin_proactive_whatsapp_cli',
                    'routing_key' => $routingKey,
                ], $actor->id);
            } catch (\Throwable $e)
            {
                Log::error('Admin proactive WhatsApp send failed', [
                    'team_id' => $teamId,
                    'phone' => $digits,
                    'routing_key' => $routingKey,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Could not send WhatsApp message: '.$e->getMessage(),
                    '_http_status' => 502,
                ];
            }
        }

        $confirmation = $sentViaTool
            ? __('Outreach enviado: el asistente usó la herramienta de WhatsApp hacia :phone con el flujo «:flow».', ['phone' => $digits, 'flow' => $routingKey])
            : __('Outreach enviado: mensaje de apertura enviado a :phone con el flujo «:flow».', ['phone' => $digits, 'flow' => $routingKey]);

        $logSource = str_starts_with($persistedUserMessage, 'humano:')
            ? 'cli'
            : (str_starts_with($persistedUserMessage, '/') ? 'slash' : 'other');

        Log::info('Admin proactive WhatsApp outreach', [
            'team_id' => $teamId,
            'actor_id' => $actor->id,
            'phone' => $digits,
            'routing_key' => $routingKey,
            'keyword' => trim($keyword),
            'sent_via_tool' => $sentViaTool,
            'source' => $logSource,
        ]);

        $this->contextService->persistMessages(
            $actor->id,
            $persistedUserMessage,
            $confirmation,
            $replyResponse['routed_to'] ?? null,
            $replyResponse['usage'] ?? [],
            $replyResponse['meta'] ?? [],
            $replyResponse['tool_calls'] ?? [],
            $replyResponse['tool_results'] ?? [],
            $teamId,
            (bool) ($replyResponse['assistant_flow_routing_key_specified'] ?? false),
            $replyResponse['assistant_flow_routing_key'] ?? null,
        );

        return [
            'success' => true,
            'response' => $confirmation,
            'action_performed' => 'proactive_whatsapp_outreach',
            'routing_key' => $routingKey,
            'phone' => $digits,
            'sent_via_tool' => $sentViaTool,
            '_http_status' => 200,
        ];
    }

    protected function gatewayForTeam(Team $team): WhatsAppGateway
    {
        if ($team->usesLocalWhatsApp())
        {
            $baseUrl = $team->getWhatsAppServiceBaseUrl();
            if ($baseUrl !== '')
            {
                return new LocalWhatsAppGateway($baseUrl, (string) config('whatsapp.local.webhook_secret'), $team->id);
            }
        }

        return app(WhatsAppGateway::class);
    }

    /**
     * @param  array<int, mixed>  $toolCalls
     */
    protected function toolCallsIncludeSendWhatsApp(array $toolCalls): bool
    {
        foreach ($toolCalls as $tc)
        {
            $name = '';
            if (is_array($tc))
            {
                $name = (string) ($tc['name'] ?? ($tc['function']['name'] ?? ''));
            } elseif (is_object($tc))
            {
                $name = (string) ($tc->name ?? (isset($tc->function->name) ? $tc->function->name : ''));
            }
            if ($name === 'send_whatsapp_message')
            {
                return true;
            }
        }

        return false;
    }
}
