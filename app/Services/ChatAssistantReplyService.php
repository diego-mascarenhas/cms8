<?php

namespace App\Services;

use App\Helpers\WhatsAppCartPresenter;
use App\Helpers\WhatsAppNaturalCartPhrase;
use App\Models\Prompt;
use App\Models\User;
use App\Services\Assistant\AssistantActorContextService;
use App\Tools\AssistantTool;
use Laravel\Ai\AiManager;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

use function Laravel\Ai\agent;

/**
 * Returns the assistant reply for chat/WhatsApp. Uses laravel/ai; can use a stub for testing.
 * When withTools is true (e.g. assistant view), uses agent() with tools for actions (create contact, task, send WhatsApp, etc.).
 */
class ChatAssistantReplyService
{
    public function __construct(
        protected AssistantToolsService $assistantTools,
        protected AssistantToolIntentPromptService $toolIntentPrompts,
        protected AgentConversationContextService $agentConversationContext,
        protected CollectionAssistantContextService $collectionAssistantContext,
        protected ContactAssistantContextService $contactAssistantContext,
        protected AssistantToolAuthorizationService $assistantToolAuthorization,
        protected AssistantActorContextService $actorContext,
        protected BusinessAssistantContextService $businessAssistantContext,
    ) {}

    /**
     * Get assistant reply for the given message and history.
     * When stub mode is enabled (config or team), returns a canned response for testing.
     * When writing via WhatsApp, pass contextUserId so tools (e.g. get_my_profile) run as that user.
     * When $forcedFlowRoutingKey is set (module_prompts routing key), that team flow prompt is merged instead of intent detection.
     * When $contactId is set, a single CRM summary block is appended. When the active flow is invoices:collections, a Stripe invoices appendix is added (no duplicate CRM).
     * When $previewOnly is true (Humano Assistant modal preview), the model must not claim WhatsApp was sent/failed; send_whatsapp_message is disabled.
     * When $teamId is set, a markdown block from team business_config (wizard) is appended to instructions when non-empty.
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @param  string|null  $channel  {@see AssistantActorContextService::CHANNEL_WEB} or {@see AssistantActorContextService::CHANNEL_WHATSAPP}; profile (prompts, hints) is derived from the user + policies, not from the channel alone.
     * @param  bool  $singleCustomerWhatsAppSendPerTurn  When true, only the first {@see AssistantToolsService::sendWhatsAppMessage()} send in this request succeeds (admin proactive opening).
     * @param  string|null  $humanoGuideAppendix  When set, appended to system instructions (e.g. terminal interactive tour); does not enable tools by itself.
     * @return array{
     *     success: bool,
     *     text?: string,
     *     message?: string,
     *     routed_to?: string|null,
     *     assistant_flow_routing_key_specified: bool,
     *     assistant_flow_routing_key: ?string,
     * }
     */
    public function getReply(string $message, array $history = [], ?int $teamId = null, bool $withTools = false, ?int $contextUserId = null, ?string $contextCustomerPhone = null, ?string $forcedFlowRoutingKey = null, ?int $contactId = null, bool $previewOnly = false, ?string $channel = null, bool $singleCustomerWhatsAppSendPerTurn = false, ?string $humanoGuideAppendix = null): array
    {
        if ($this->useStub($teamId))
        {
            return $this->mergeFlowPersistMeta($this->getStubReply($message), false, null);
        }

        $this->assistantTools->clearRequestContext();
        if ($withTools && $teamId !== null)
        {
            $this->assistantTools->setRequestContext($contextUserId, $teamId, $contextCustomerPhone);
            if ($singleCustomerWhatsAppSendPerTurn)
            {
                $this->assistantTools->setWhatsAppToolSingleCustomerSendPerTurn(true);
            }
        }

        $directCart = $this->directWhatsAppCartReply($message, $withTools, $teamId, $contextCustomerPhone);
        if ($directCart !== null)
        {
            return $this->mergeFlowPersistMeta($directCart, false, null);
        }

        $flowRoutedTo = null;
        $flowRoutingKey = null;
        $flowPersistSpecified = false;
        $flowPersistKey = null;
        $flowPrompt = null;
        $whatsappCustomerThread = $channel === AssistantActorContextService::CHANNEL_WHATSAPP
            && $contextCustomerPhone !== null
            && trim($contextCustomerPhone) !== '';
        $instructions = $withTools
            ? $this->getAssistantToolsSystemPrompt($contextUserId)
            : AssistantSystemPrompt::get();

        $actorContext = ($withTools && $teamId !== null && $contextUserId !== null)
            ? $this->actorContext->resolve($contextUserId, $teamId, $channel)
            : null;

        if ($actorContext !== null && $actorContext->limitedToolset)
        {
            $instructions .= $this->customerTeamRoleInstructionsAppendix();
        }

        $businessAppendix = $this->businessAssistantContext->buildMarkdownAppendix($teamId, $whatsappCustomerThread);
        if ($businessAppendix !== '')
        {
            $instructions .= "\n\n---\n\n".$businessAppendix;
        }

        $hasGuideAppendix = $humanoGuideAppendix !== null && trim($humanoGuideAppendix) !== '';

        if ($withTools && $teamId !== null)
        {
            $forced = $forcedFlowRoutingKey !== null ? trim($forcedFlowRoutingKey) : '';
            $stickyKey = ($contextUserId !== null && $channel !== AssistantActorContextService::CHANNEL_WHATSAPP)
                ? $this->agentConversationContext->getAssistantToolFlowRoutingKey($contextUserId, $teamId)
                : null;
            if ($forced !== '')
            {
                $forcedPrompt = Prompt::findByRoutingKey($forced, $teamId);
                if ($forcedPrompt && $forcedPrompt->is_active && ! $forcedPrompt->isGeneralRouter())
                {
                    $resolution = [
                        'prompt' => $forcedPrompt,
                        'routing_key' => $forced,
                        'persist_assistant_flow_key' => 'set',
                    ];
                } elseif ($hasGuideAppendix)
                {
                    // Automation funnel / interactive guide owns the turn: do not fall back to sticky discovery.
                    $resolution = [
                        'prompt' => null,
                        'routing_key' => null,
                        'persist_assistant_flow_key' => 'omit',
                    ];
                } else
                {
                    $resolution = $this->toolIntentPrompts->resolveFlowForToolAssistant($teamId, $message, $stickyKey);
                }
            } elseif ($hasGuideAppendix)
            {
                // Active automation funnel step (or Humano guide appendix): skip intent menus.
                $resolution = [
                    'prompt' => null,
                    'routing_key' => null,
                    'persist_assistant_flow_key' => 'omit',
                ];
            } else
            {
                $resolution = $this->toolIntentPrompts->resolveFlowForToolAssistant($teamId, $message, $stickyKey);
            }
            $flowPrompt = $resolution['prompt'];
            if ($flowPrompt)
            {
                $flowRoutedTo = $flowPrompt->section_label;
                $flowBody = trim($flowPrompt->resolvedInstruction($teamId));
                if ($flowBody !== '')
                {
                    $instructions .= "\n\n---\n\n## Team flow prompt: «{$flowPrompt->section_label}» (section_key: {$flowPrompt->section_key})\n\n";
                    $instructions .= "Stay in this flow for follow-up messages in the same conversation until the user clearly changes topic (e.g. reset phrases).\n\n";
                    $instructions .= $flowBody;
                    if ($flowPrompt->section_key === 'wapify_me')
                    {
                        $instructions .= $this->wapifyFlowContextAppendix($history);
                    }
                }
                $flowPrompt->loadMissing('module');
                $flowRoutingKey = $flowPrompt->module
                    ? $flowPrompt->module->key.':'.$flowPrompt->section_key
                    : $flowPrompt->section_key;
            }

            if ($contactId !== null && $contactId > 0)
            {
                $contactSummary = $this->contactAssistantContext->buildMarkdownSummary($contactId, $teamId);
                if ($contactSummary !== '')
                {
                    $instructions .= "\n\n---\n\n".$contactSummary;
                }
                if ($flowRoutingKey === 'invoices:collections')
                {
                    $stripeAppendix = $this->collectionAssistantContext->buildStripeAppendixForContact($contactId, $teamId);
                    if ($stripeAppendix !== '')
                    {
                        $instructions .= "\n\n---\n\n".$stripeAppendix;
                    }
                }
            }

            if ($resolution['persist_assistant_flow_key'] === 'set')
            {
                $flowPersistSpecified = true;
                $flowPersistKey = $resolution['routing_key'];
            } elseif ($resolution['persist_assistant_flow_key'] === 'clear')
            {
                $flowPersistSpecified = true;
                $flowPersistKey = null;
            }

            if ($flowPrompt === null
                && ! $previewOnly
                && ! $hasGuideAppendix
                && ! $this->toolIntentPrompts->keywordIntentRoutingEnabled($teamId))
            {
                $discovery = $this->flowDiscoveryModeAppendix((int) $teamId);
                if ($discovery !== '')
                {
                    $instructions .= $discovery;
                }
            }
        }

        if ($previewOnly)
        {
            $collectionsTotalLabel = null;
            if ($flowRoutingKey === 'invoices:collections' && $contactId !== null && $contactId > 0 && $teamId !== null)
            {
                $collectionsTotalLabel = $this->collectionAssistantContext->unpaidTotalLabelForContact($contactId, $teamId);
            }
            $instructions .= $this->previewModeInstructionsAppendix($flowRoutingKey, $collectionsTotalLabel);
        }

        if ($actorContext !== null && $actorContext->whatsappInboundCustomerPrompts && ! $previewOnly)
        {
            $instructions .= $this->inboundWhatsappCustomerIntentAppendix();
            if (! $this->toolIntentPrompts->keywordIntentRoutingEnabled($teamId))
            {
                $discovery = $this->flowDiscoveryModeAppendix((int) $teamId);
                if ($discovery !== '')
                {
                    $instructions .= $discovery;
                }
            }
        }

        if ($hasGuideAppendix)
        {
            $instructions .= "\n\n---\n\n".trim((string) $humanoGuideAppendix);
        } elseif ($withTools && $teamId !== null && $actorContext !== null && ! $this->shouldCompactWhatsAppCatalogContext($whatsappCustomerThread, $flowRoutingKey))
        {
            $hintKey = $actorContext->usesWebInteractiveGuideHint()
                ? 'web_help_hint'
                : 'whatsapp_help_hint';
            $hint = trim((string) config('humano_interactive_guide.'.$hintKey, ''));
            if ($hint !== '')
            {
                $instructions .= "\n\n---\n\n".$hint;
            }
        }

        if ($withTools && $this->shouldCompactWhatsAppCatalogContext($whatsappCustomerThread, $flowRoutingKey))
        {
            $instructions = $this->compactWhatsAppCatalogInstructions(
                $contextUserId,
                $teamId,
                $contactId,
                $flowPrompt,
                $history,
            );
        }

        $tools = $withTools
            ? $this->buildLaravelAiTools(
                $previewOnly,
                $actorContext?->user,
                $teamId,
                $whatsappCustomerThread,
                $flowRoutingKey,
            )
            : [];

        $reply = $this->getReplyWithLaravelAi(
            $message,
            $this->compactHistoryForPrompt($history, $channel),
            $instructions,
            $tools,
            $flowRoutedTo,
        );
        if ($withTools && ($reply['success'] ?? false))
        {
            $committedKey = $this->routingKeyCommittedViaTools($reply['tool_results'] ?? []);
            if ($committedKey !== null)
            {
                $flowPersistSpecified = true;
                $flowPersistKey = $committedKey;
            }
        }

        if ($teamId !== null && ($reply['success'] ?? false))
        {
            TokenUsageLogService::logFromAiResponse(
                (int) $teamId,
                'ChatAssistantReplyService',
                $reply['usage'] ?? [],
                moduleKey: 'chat',
                inputSize: strlen($message),
                outputSize: strlen((string) ($reply['text'] ?? '')),
            );
        }

        return $this->mergeFlowPersistMeta($reply, $flowPersistSpecified, $flowPersistKey);
    }

    /**
     * @param  array<string, mixed>  $reply
     * @return array<string, mixed>
     */
    protected function mergeFlowPersistMeta(array $reply, bool $assistantFlowRoutingKeySpecified, ?string $assistantFlowRoutingKey): array
    {
        $reply['assistant_flow_routing_key_specified'] = $assistantFlowRoutingKeySpecified;
        $reply['assistant_flow_routing_key'] = $assistantFlowRoutingKey;

        return $reply;
    }

    /**
     * Show the real shopping cart. The model must not invent another WhatsApp number.
     *
     * @return array{success: bool, text: string, routed_to: null, usage: array, tool_calls: array, tool_results: array, meta: array}|null
     */
    private function directWhatsAppCartReply(string $message, bool $withTools, ?int $teamId, ?string $contextCustomerPhone): ?array
    {
        if (! $withTools || $teamId === null || $contextCustomerPhone === null || trim($contextCustomerPhone) === '')
        {
            return null;
        }

        if (! WhatsAppNaturalCartPhrase::isViewCart($message))
        {
            return null;
        }

        $text = WhatsAppCartPresenter::customerMessage($teamId, $contextCustomerPhone);

        return [
            'success' => true,
            'text' => $text,
            'routed_to' => null,
            'usage' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'meta' => ['whatsapp_cart' => 'view'],
        ];
    }

    /**
     * Use laravel/ai agent (Prism gateway) for assistant reply.
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @param  array<int, \Laravel\Ai\Contracts\Tool>  $tools
     * @return array{success: bool, text?: string, message?: string, routed_to?: string|null}
     */
    protected function getReplyWithLaravelAi(string $message, array $history, string $instructions, array $tools = [], ?string $routedTo = null): array
    {
        $message = trim($message);
        if ($message === '')
        {
            return [
                'success' => false,
                'message' => 'Empty user message',
                'usage' => [],
                'tool_calls' => [],
                'tool_results' => [],
                'meta' => [],
            ];
        }

        try
        {
            $historyMessages = $this->historyToMessages($history);
            $provider = (string) config('ai.assistant_provider', 'anthropic');
            $failover = config('ai.assistant_failover');
            $providerParam = is_array($failover) && $failover !== [] ? array_merge([$provider], $failover) : $provider;
            $configuredModel = config('ai.assistant_model', 'cheapest');
            $modelParam = $this->resolveAssistantModel($provider, $configuredModel);
            $timeout = (int) config('ai.assistant_timeout', 60);

            $agent = agent(
                instructions: $instructions,
                messages: $historyMessages,
                tools: $tools,
            );

            $response = $agent->prompt($message, [], $providerParam, $modelParam, $timeout);
            $text = $response->text ?? '';

            $usage = $this->usageArrayFromAiResponse($response->usage ?? null);
            if ($modelParam !== null && $modelParam !== '')
            {
                $usage['model'] = $modelParam;
            }

            $toolCalls = [];
            $toolResults = [];
            if (isset($response->toolCalls) && is_array($response->toolCalls))
            {
                $toolCalls = $response->toolCalls;
            }
            if (isset($response->toolResults) && is_array($response->toolResults))
            {
                $toolResults = $response->toolResults;
            }

            if ($text === '')
            {
                $text = $this->fallbackTextFromToolOutputs();
            }

            return [
                'success' => true,
                'text' => $text !== '' ? $text : 'No response text',
                'routed_to' => $routedTo,
                'usage' => $usage,
                'tool_calls' => $toolCalls,
                'tool_results' => $toolResults,
                'meta' => array_filter([
                    'model' => $modelParam,
                    'provider' => $provider,
                ]),
            ];
        } catch (\Throwable $e)
        {
            \Illuminate\Support\Facades\Log::error('ChatAssistantReplyService (laravel/ai)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error communicating with assistant: '.$e->getMessage(),
                'usage' => [],
                'tool_calls' => [],
                'tool_results' => [],
                'meta' => [],
            ];
        }
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    private function usageArrayFromAiResponse(mixed $usage): array
    {
        if ($usage === null)
        {
            return [];
        }

        $prompt = is_array($usage)
            ? (int) ($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0)
            : (int) ($usage->promptTokens ?? $usage->prompt_tokens ?? 0);
        $completion = is_array($usage)
            ? (int) ($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0)
            : (int) ($usage->completionTokens ?? $usage->completion_tokens ?? 0);
        $total = TokenUsageLogService::totalTokensFromUsage($usage);

        if ($total <= 0 && $prompt <= 0 && $completion <= 0)
        {
            return [];
        }

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total > 0 ? $total : ($prompt + $completion),
        ];
    }

    /**
     * Resolve assistant model from config. "cheapest" maps to provider cheapest text model.
     */
    private function resolveAssistantModel(string $provider, mixed $configuredModel): ?string
    {
        $model = is_string($configuredModel) ? trim($configuredModel) : null;
        if ($model === null || $model === '')
        {
            return null;
        }

        if (strtolower($model) !== 'cheapest')
        {
            return $model;
        }

        try
        {
            $ai = app(AiManager::class);
            $textProvider = $ai->textProvider($provider);
            $cheapest = $textProvider->cheapestTextModel();

            return is_string($cheapest) && trim($cheapest) !== '' ? trim($cheapest) : null;
        } catch (\Throwable)
        {
            return null;
        }
    }

    /**
     * Convert chat history (direction/body) to laravel/ai Message instances.
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @return array<int, \Laravel\Ai\Messages\UserMessage|\Laravel\Ai\Messages\AssistantMessage>
     */
    protected function historyToMessages(array $history): array
    {
        $out = [];
        foreach ($history as $item)
        {
            $direction = $item['direction'] ?? '';
            $body = trim((string) ($item['body'] ?? ''));
            if ($body === '')
            {
                continue;
            }
            if ($direction === 'inbound')
            {
                $out[] = new UserMessage($body);
            } elseif ($direction === 'outbound')
            {
                $out[] = new AssistantMessage($body);
            }
        }

        return $out;
    }

    /**
     * Inbound user messages in history plus the current turn (not yet in history).
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     */
    private function countClientTurnsIncludingCurrent(array $history): int
    {
        $inbound = 0;
        foreach ($history as $item)
        {
            if (($item['direction'] ?? '') === 'inbound')
            {
                $inbound++;
            }
        }

        return $inbound + 1;
    }

    /**
     * Objective turn count for staged Wapify disclosure (prompt defines how to use it).
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     */
    private function wapifyFlowContextAppendix(array $history): string
    {
        $turns = $this->countClientTurnsIncludingCurrent($history);

        return <<<EOT


### Parámetro interno Wapify (no lo cites literalmente al usuario)

- **Turnos de mensaje del cliente en este hilo** (incluye el mensaje actual): **{$turns}**.
- Úsalo solo según las reglas de progresión y códigos del flujo Wapify.Me.

EOT;
    }

    /**
     * Modal "Vista previa": single first message draft only; no multi-step playbook in the textarea.
     */
    private function previewModeInstructionsAppendix(?string $flowRoutingKey = null, ?string $collectionsTotalLabel = null): string
    {
        $collectionsNote = $flowRoutingKey === 'invoices:collections'
            ? "\nEl prompt del equipo puede describir una cobranza en **varios pasos**: para esta vista previa **ignorá esa estructura**. No escribas «Paso 1», «Paso 2», listas numeradas de toda la guía ni el guion completo: solo el **primer mensaje** que iría al cliente.\n"
            : '';
        $collectionsTotalRule = ($flowRoutingKey === 'invoices:collections' && $collectionsTotalLabel !== null)
            ? "\n- En ese primer mensaje, incluí explícitamente el total pendiente: **{$collectionsTotalLabel}**.\n"
            : '';

        return <<<EOT


### Vista previa — un solo mensaje

Salida requerida: **únicamente el texto del primer mensaje** que el operador enviaría al cliente (una burbuja). Sin introducción para el operador, sin explicar la estrategia, sin definir «todos los pasos» ni el recorrido completo de la conversación.
{$collectionsNote}
{$collectionsTotalRule}
- Debe sonar humano y breve: máximo 3 frases cortas (ideal 220-320 caracteres).
- No uses tablas ni listas largas.
- No uses markdown ni asteriscos para formato (** o *).
- No inventes fallos de envío ni problemas técnicos; aquí no se envía nada todavía.
- No uses la herramienta send_whatsapp_message (no aplica en vista previa).

EOT;
    }

    /**
     * When the model ends a turn without text but tools ran (e.g. it only called
     * update_cms_content), surface the last tool output so the user sees a confirmation
     * instead of "No response text".
     */
    protected function fallbackTextFromToolOutputs(): string
    {
        $outputs = $this->assistantTools->getToolOutputsInRequest();
        if ($outputs === [])
        {
            return '';
        }

        return trim((string) end($outputs));
    }

    /**
     * Build laravel/ai Tool instances from AssistantToolsService definitions.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    protected function buildLaravelAiTools(
        bool $excludeWhatsAppSend = false,
        ?User $actor = null,
        ?int $teamId = null,
        bool $whatsappCustomerThread = false,
        ?string $flowRoutingKey = null,
    ): array {
        $definitions = $this->assistantTools->getDefinitions();
        $allowedNames = $teamId !== null
            ? $this->assistantToolAuthorization->exposedToolNames(
                array_values(array_filter(array_map(fn (array $def) => (string) ($def['name'] ?? ''), $definitions))),
                $actor,
                $teamId,
                $whatsappCustomerThread,
                $flowRoutingKey,
                $excludeWhatsAppSend,
            )
            : null;
        $allowedLookup = $allowedNames !== null ? array_flip($allowedNames) : null;

        $tools = [];
        foreach ($definitions as $def)
        {
            $name = (string) ($def['name'] ?? '');
            if ($name === '')
            {
                continue;
            }
            if ($excludeWhatsAppSend && $name === 'send_whatsapp_message')
            {
                continue;
            }
            if ($allowedLookup !== null && ! isset($allowedLookup[$name]))
            {
                continue;
            }
            $tools[] = new AssistantTool(
                $this->assistantTools,
                $name,
                $def['description'],
                $def['input_schema'] ?? ['type' => 'object', 'properties' => [], 'required' => []],
            );
        }

        return $tools;
    }

    private function shouldCompactWhatsAppCatalogContext(bool $whatsappCustomerThread, ?string $flowRoutingKey): bool
    {
        return $whatsappCustomerThread
            && $this->assistantToolAuthorization->isCatalogSalesRoutingKey($flowRoutingKey);
    }

    /**
     * WhatsApp catalog thread: skip CRM staff prompt, discovery, and help hint.
     */
    private function compactWhatsAppCatalogInstructions(
        ?int $contextUserId,
        ?int $teamId,
        ?int $contactId,
        ?Prompt $flowPrompt,
        array $history,
    ): string {
        $instructions = $this->getCompactWhatsAppCatalogSystemPrompt($contextUserId);

        if ($flowPrompt)
        {
            $flowBody = trim((string) $flowPrompt->resolvedInstruction($teamId));
            if ($flowBody !== '')
            {
                $instructions .= "\n\n---\n\n## Team flow prompt: «{$flowPrompt->section_label}»\n\n";
                $instructions .= $flowBody;
                if ($flowPrompt->section_key === 'wapify_me')
                {
                    $instructions .= $this->wapifyFlowContextAppendix($history);
                }
            }
        }

        $businessAppendix = $this->businessAssistantContext->buildMarkdownAppendix($teamId, true);
        if ($businessAppendix !== '')
        {
            $instructions .= "\n\n---\n\n".$businessAppendix;
        }

        if ($contactId !== null && $contactId > 0 && $teamId !== null)
        {
            $contactSummary = $this->contactAssistantContext->buildMarkdownSummary($contactId, $teamId);
            if ($contactSummary !== '')
            {
                $instructions .= "\n\n---\n\n".$contactSummary;
            }
        }

        return $instructions;
    }

    /**
     * @param  array<int, array{direction?: string, body?: string}>  $history
     * @return array<int, array{direction?: string, body?: string}>
     */
    private function compactHistoryForPrompt(array $history, ?string $channel): array
    {
        $isWhatsApp = $channel === AssistantActorContextService::CHANNEL_WHATSAPP;
        $maxMessages = $isWhatsApp ? 6 : 12;
        $maxBody = $isWhatsApp ? 400 : 800;
        $sliced = array_slice($history, -$maxMessages);

        return array_map(function (array $item) use ($maxBody): array
        {
            $body = trim((string) ($item['body'] ?? ''));
            if (mb_strlen($body) > $maxBody)
            {
                $item['body'] = mb_substr($body, 0, $maxBody).'…';
            }

            return $item;
        }, $sliced);
    }

    /**
     * System prompt for the assistant when tools are enabled (manage contacts, tasks, reports, WhatsApp).
     */
    protected function getAssistantToolsSystemPrompt(?int $contextUserId = null): string
    {
        $today = now()->format('Y-m-d');
        $todayLabel = now()->translatedFormat('l d \d\e F \d\e Y');

        $user = auth()->user();
        if ($user === null && $contextUserId !== null)
        {
            $user = \App\Models\User::withoutGlobalScopes()->find($contextUserId);
        }
        $isAdmin = $user && ($user->hasRole('admin') || $user->hasRole('root'));
        $adminInstruction = $isAdmin
            ? "\n- El usuario es del equipo: no cierres con «¿Necesitás algo más?», «¿Algo más?» ni similares. Terminá con la respuesta útil."
            : '';

        return <<<EOT
Sos el asistente de Humano CRM. Tenés **acceso real** a los datos de este equipo (contactos, tareas, agenda, catálogo, facturación, CMS) a través de las herramientas. No es una simulación ni una demo.

FECHA DE HOY: {$today} ({$todayLabel}). «hoy» y «ahora» son {$today}; «mañana» es el día siguiente; un día de la semana es la próxima vez que caiga. Fechas y horas siempre en Y-m-d H:i:s como hora local del usuario («de 14 a 15» → 14:00:00 y 15:00:00).

## 1. Cómo escribís

- Respondé en el idioma del usuario; por defecto, español.
- **Breve y humano: 2 a 4 frases cortas.** Sin relleno, sin repetir lo que el usuario acaba de decir, sin narrar lo que vas a hacer ni resumir tu propio trabajo.
- Una sola pregunta por mensaje.
- Nada de tablas ni listas largas, salvo que pidan explícitamente un listado.
- En WhatsApp (y siempre que uses send_whatsapp_message) escribí las URLs en texto plano (https://...) **sin** asteriscos alrededor: `**https://...**` rompe el enlace. Usá negrita solo en palabras normales.{$adminInstruction}

## 2. Nunca inventes (regla dura)

La única fuente de verdad son los resultados de las herramientas y el contexto del negocio.

- **Nunca afirmes que algo se creó, se actualizó, se agregó o cambió de estado si la herramienta no devolvió éxito en ESTE turno.** Vale para contactos, interacciones, tareas, eventos, campañas, plantillas, tickets, oportunidades, CMS y carrito. Si proponés una acción y el usuario confirma (sí/ok/dale/confirmo), ejecutá la herramienta antes de contestar, no solo el texto.
- Nunca inventes precios, stock, disponibilidad, plazos de entrega, descuentos, importes, IDs, URLs, plantillas ni categorías. Si falta un dato, buscalo con la herramienta; si la herramienta no lo tiene, decilo en una frase y ofrecé la alternativa que sí exista.
- Nunca digas que «no tenés acceso», que «esto es una simulación», que «no hay datos reales» o que «no estás conectado a ningún sistema». Estás conectado: usá las herramientas y devolvé el resultado real.
- Nunca inventes fallos: «problema técnico», «problema con la base de datos», «la búsqueda no funciona». Sin resultados significa crear el registro o hacer una pregunta de aclaración. Sin permiso significa decir que su rol no puede hacer esa acción, no culpar al sistema.
- Copiá los teléfonos internacionales tal cual los escribió el usuario (+61 sigue siendo Australia; no asumas +34).

## 3. Venta: del catálogo al pedido cerrado

Cuando el cliente quiere comprar, esto tiene prioridad. Recorré el circuito completo; no lo dejes a la mitad.

1. **Mostrar**: list_product_catalog o search_products. Ofrecé como mucho 3 o 4 opciones, con nombre y precio reales. **No vuelvas a listar el catálogo** si el cliente solo confirma (ok, dale, gracias, sí) y ya mostraste productos en este hilo: seguí al carrito o hacé una pregunta. list_product_catalog sin categoría solo al abrir el catálogo; search_products solo cuando nombra un producto o rubro.
2. **Agregar**: en cuanto confirma («sí», «dale», «ok», «quiero», «agregalo», «agregame», «agregame 2», «poneme 2», «añadilo», «mandale») después de mostrar un producto, llamá **add_to_whatsapp_cart en ese mismo turno** con `quantity` si dijo cuántas. No contestes solo con texto. Si no nombra el producto, usá el último `product_id` de search_products. No preguntes «¿te lo agrego al carrito?» y después pidas un comando: si mostraste uno solo, preguntá cuántas quiere o sumalo cuando confirme.
3. **Cerrar**: después de agregar, decí qué agregaste (nombre, cantidad, precio) y proponé **finalizar** para cerrar el pedido. Si piden ver el carrito, llamá **view_whatsapp_cart** y mostrá ese resultado. Un *SÍ* suelto confirma el pedido recién **después** de *finalizar*.
4. **Siempre proponé el próximo paso concreto.** No cierres con «avisame cualquier cosa».

Si add_to_whatsapp_cart dice que no hay teléfono, es el asistente web sin destinatario: pedile que escriba por WhatsApp. En un hilo de WhatsApp el teléfono ya está: **nunca** le pidas que escriba *comprar* más el nombre, **nunca** le pases otro número de WhatsApp y **nunca** digas que no podés completar el carrito. Si la herramienta falla, pedí el nombre o el código y reintentá add_to_whatsapp_cart.

## 4. Secuencia de herramientas

Las descripciones de cada herramienta dicen qué hace; esto es el orden en que hay que usarlas.

- **Nunca le pidas un ID al usuario.** Resolvelo con search_contacts, search_tasks, search_products, list_calendar_events, list_templates, list_messages o list_cms_content.
- Buscar antes de crear: search_contacts antes de create_contact. Si no hay coincidencia, creá el contacto **solo con el nombre**; email y teléfono son opcionales y no los pidas antes de intentarlo. Si create_contact responde que ya existe, usá ese id.
- Interacciones y categorías de un contacto: resolvé la persona primero, después create_contact_interaction o assign_contact_to_category con el contact_id.
- Agenda: check_calendar_availability antes de confirmar un hueco. El evento incluye a **quien la pide**, no solo al agente: guest_contact_ids. Si esa persona no tiene email, pedilo y update_contact antes de crear (la invitación va por email). Preguntá si quieren sumar a más gente; para cada extra pedí nombre, apellido y email, create_contact si no existen, y agregalos a guest_contact_ids. Recién entonces create_calendar_event o update_calendar_event. Si falta la fecha o la hora, pedilas en un mensaje corto (1 hora de duración si no dicen fin). El sí/no del usuario vale **solo para tu última pregunta**: si preguntaste extras («¿ubicación, notas, alguien más?») y dice no / no gracias, **creá la cita igual** en ese turno; no está rechazando el alta. Si preguntaste «¿agendo?» o «¿cancelo?» y dice no, no llames create_calendar_event.
- Plantillas: modificar no es crear. create_template solo si piden una nueva. Para renombrar o activar, list_templates y después update_template o update_template_status. Para cambios de diseño o contenido, pasales el enlace del editor.
- Campañas (News): create_message solo cuando tengas asunto, destinatarios y texto. Los destinatarios son **dos filtros independientes**: categoría de contactos (list_contact_categories) y estado del contacto en el CRM (list_contact_statuses, nombres exactos en contact_status_name), o todos los contactos sin filtro. Si falta algo, preguntalo en un solo mensaje. Para cambiar una campaña existente usá list_messages y update_message, nunca create_message otra vez: duplicaría.
- Al hablar de campañas en español, el on/off de envíos no se llama solo «Estado»: decí **envío activo**, **envío pausado** o **campaña en pausa**, para no confundirlo con el estado del contacto en el CRM.
- CMS: llamá list_cms_content antes de decir que no hay contenido publicado.
- Oportunidades: si no sabés los stage_slug válidos, list_opportunity_stages primero.

## 5. Alcance de este turno

- Ejecutá herramientas solo para lo que pide el **mensaje actual**. No arrastres acciones pendientes de turnos anteriores (si este mensaje solo agrega el contacto B, no crees además la tarea del contacto A).
- El historial es contexto, no una cola de tareas. Sin una confirmación clara o un pedido explícito en **este** mensaje, no ejecutes nada de otros temas.
- Un «no» no cancela una acción ya acordada si tu última pregunta era opcional (agregar nota, otro invitado, «¿algo más?»). Eso significa seguir **sin** ese extra.
- Al confirmar, mencioná solo las herramientas que salieron bien en **este** mensaje. Nunca mezcles resultados de un pedido anterior.

## 6. Importar en lote y archivos adjuntos

- Tareas: cabecera Concepto, Propuesta, Cliente, Importe (opcionales IVA, IRPF, Fecha envío, Estado, Nota), separadas por comas o punto y coma. El prefijo task.store es opcional.
- Facturas: mismas columnas, pero el mensaje **debe** empezar con invoice.store. Cliente se cruza con la empresa por nombre o código; sin coincidencia se guarda como Borrador.
- Contactos: prefijo contact.store y una cabecera con al menos Nombre, Email o Teléfono/Móvil (opcionales Apellido, Empresa, Nota).
- Nunca digas que no podés leer imágenes ni documentos. Si el usuario dice que envió un archivo o una foto, confirmá que se está procesando y contale que puede seguirlo en «Ver documentos» (/assistant/documents).

## 7. Foco del tema

Si hay un flujo del equipo activo en este hilo, quedate en ese tema hasta resolverlo o hasta que el usuario quiera cambiar. No saltes al catálogo durante una gestión de cobros o de soporte salvo que pidan comprar. Si las instrucciones incluyen «Conversation flow (discovery mode)», hacé como mucho una pregunta corta y después llamá a commit_assistant_flow con la routing_key exacta.
EOT;
    }

    protected function getCompactWhatsAppCatalogSystemPrompt(?int $contextUserId = null): string
    {
        $today = now()->format('Y-m-d');
        $todayLabel = now()->translatedFormat('l d \d\e F \d\e Y');

        $user = auth()->user();
        if ($user === null && $contextUserId !== null)
        {
            $user = User::withoutGlobalScopes()->find($contextUserId);
        }
        $isAdmin = $user && ($user->hasRole('admin') || $user->hasRole('root'));
        $adminInstruction = $isAdmin
            ? "\n- El usuario es del equipo: no cierres con «¿Necesitás algo más?» ni similares."
            : '';

        return <<<EOT
Sos el asistente de ventas por WhatsApp de este equipo. Tenés el catálogo y el carrito reales. No es una simulación.

FECHA DE HOY: {$today} ({$todayLabel}).

## Cómo escribís

- Español, 2 a 4 frases. Una pregunta por mensaje. URLs en texto plano, sin asteriscos.{$adminInstruction}

## No inventes

Precios, stock y productos salen solo de las herramientas de este turno. Si no está, decilo y ofrecé lo más cercano.

## Venta

1. **Mostrar**: list_product_catalog o search_products, 3 o 4 opciones. **No vuelvas a listar el catálogo** si el cliente solo confirma (ok, dale, gracias) y ya mostraste productos: agregá al carrito o preguntá qué busca.
2. **Agregar**: «sí», «dale», «ok», «quiero», «agregalo» → **add_to_whatsapp_cart** en este turno. Si no nombra el producto, usá el último que mostraste.
3. **Cerrar**: confirmá y proponé **finalizar**. *carrito* para ver, *quitar* para sacar.
EOT;
    }

    /**
     * Extra system instructions when the acting user is a Jetstream client (or guest/user) on this team.
     */
    private function customerTeamRoleInstructionsAppendix(): string
    {
        return <<<'EOT'


### Rol en este equipo (cliente con acceso limitado)

Quien escribe tiene un perfil **limitado** (cliente o invitado, sin permisos de gestión del CRM). No uses herramientas internas: importaciones masivas, campañas, plantillas, agenda del equipo ni informes de cuenta. Atendé por conversación: catálogo y carrito, tickets de soporte y respuestas en este hilo. Si piden una acción interna que no pueden hacer, decí que la tiene que hacer el equipo del negocio desde la app.
EOT;
    }

    /**
     * When keyword auto-routing is off: guide intent via one question + commit_assistant_flow tool.
     */
    private function flowDiscoveryModeAppendix(int $teamId): string
    {
        $keys = trim(Prompt::buildRoutableKeysList($teamId));
        if ($keys === '')
        {
            return '';
        }

        return <<<EOT


### Conversation flow (discovery mode)

No team flow is locked to this thread yet (or the thread was reset).

- Ask **one** short question in Spanish to confirm what the user needs, with options grounded in the list below (adapt labels to sound natural).
- If they already stated intent clearly (e.g. pagar facturas, catálogo, agendar), **skip** the question: call **commit_assistant_flow** with the matching routing_key, then answer in that lane.
- After you commit, keep helping in that same topic until they finish or explicitly want to change topic (use commit again or team reset phrases).
- Never invent routing_keys — only use keys from this list:

{$keys}
EOT;
    }

    /**
     * Extra instructions for automatic replies to inbound WhatsApp customers: interpret from business
     * configuration and converse until intent is clear (no separate classifier).
     */
    private function inboundWhatsappCustomerIntentAppendix(): string
    {
        return <<<'EOT'


### Entrada de cliente por WhatsApp (intención)

Es un cliente escribiendo por WhatsApp, no alguien del equipo. Entendé qué necesita conversando, sin adivinar el tema de una.

- Usá el bloque **Contexto del negocio** de arriba para interpretar términos ambiguos y para el tono. No inventes ofertas ni servicios que no estén ahí.
- No asumas que quiere comprar. Palabras como *cita*, *turno*, *reunión* o *visita* suelen ser agenda. Pasá al catálogo y al carrito **solo** cuando el mensaje o el contexto dejan claro que quiere comprar.
- Después de mostrar un producto, *agregar*, *agregame*, *poneme* o *mandame* (con o sin cantidad, también «dos») es confirmación de compra, no agenda: llamá add_to_whatsapp_cart. Si piden ver el carrito, llamá view_whatsapp_cart. No le pidas que escriba *comprar* más el nombre ni le pases otro WhatsApp.
- Si la intención no está clara, una sola pregunta corta (o dos o tres opciones) alineada con lo que ofrece el negocio. Si ya quedó clara antes en el hilo, seguí sin volver a preguntar.
- Cuando el objetivo sea de venta, recorré el circuito de la sección «Venta: del catálogo al pedido cerrado» hasta *finalizar*.
- Si aparece la sección *Conversation flow (discovery mode)*, llamá a **commit_assistant_flow** con la routing_key que corresponda.
EOT;
    }

    /**
     * @param  array<int, mixed>  $toolResults
     */
    private function routingKeyCommittedViaTools(array $toolResults): ?string
    {
        foreach ($toolResults as $item)
        {
            $text = trim($this->toolResultToString($item));
            if (! str_starts_with($text, 'FLOW_COMMITTED:'))
            {
                continue;
            }

            $json = substr($text, strlen('FLOW_COMMITTED:'));
            $payload = json_decode($json, true);
            if (is_array($payload) && isset($payload['routing_key']))
            {
                $key = trim((string) $payload['routing_key']);
                if ($key !== '')
                {
                    return $key;
                }
            }
        }

        return null;
    }

    private function toolResultToString(mixed $item): string
    {
        if (is_string($item))
        {
            return $item;
        }

        if (is_array($item))
        {
            if (isset($item['content']) && is_string($item['content']))
            {
                return $item['content'];
            }
            if (isset($item['result']) && is_string($item['result']))
            {
                return $item['result'];
            }

            return json_encode($item) ?: '';
        }

        if (is_object($item))
        {
            if (method_exists($item, 'content'))
            {
                $c = $item->content();

                return is_string($c) ? $c : (string) $c;
            }
            if (isset($item->content))
            {
                return (string) $item->content;
            }
            if (isset($item->result))
            {
                return (string) $item->result;
            }
        }

        return '';
    }

    /**
     * Whether to use stub response (for testing without calling Claude).
     */
    public function useStub(?int $teamId = null): bool
    {
        if (config('app.assistant_chat_stub', false))
        {
            return true;
        }

        if ($teamId !== null)
        {
            $team = \App\Models\Team::withoutGlobalScopes()->find($teamId);

            return $team && (bool) $team->getSetting('assistant_chat_stub', false);
        }

        if (auth()->check() && auth()->user()->currentTeam)
        {
            return (bool) auth()->user()->currentTeam->getSetting('assistant_chat_stub', false);
        }

        return false;
    }

    /**
     * Stub response for testing the chat flow without Claude.
     */
    protected function getStubReply(string $message): array
    {
        return [
            'success' => true,
            'text' => '[Modo prueba] Recibí: «'.mb_substr($message, 0, 100).(mb_strlen($message) > 100 ? '…' : '').'». En producción aquí respondería el asistente.',
            'routed_to' => null,
            'usage' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'meta' => [],
        ];
    }
}
