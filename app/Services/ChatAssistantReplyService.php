<?php

namespace App\Services;

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

        $flowRoutedTo = null;
        $flowRoutingKey = null;
        $flowPersistSpecified = false;
        $flowPersistKey = null;
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

        $businessAppendix = $this->businessAssistantContext->buildMarkdownAppendix($teamId);
        if ($businessAppendix !== '')
        {
            $instructions .= "\n\n---\n\n".$businessAppendix;
        }

        if ($withTools && $teamId !== null && $contextUserId !== null)
        {
            $forced = $forcedFlowRoutingKey !== null ? trim($forcedFlowRoutingKey) : '';
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
                } else
                {
                    $stickyKey = $this->agentConversationContext->getAssistantToolFlowRoutingKey($contextUserId, $teamId);
                    $resolution = $this->toolIntentPrompts->resolveFlowForToolAssistant($teamId, $message, $stickyKey);
                }
            } else
            {
                $stickyKey = $this->agentConversationContext->getAssistantToolFlowRoutingKey($contextUserId, $teamId);
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
                    $instructions .= "Stay in this flow for follow-up messages in the same conversation until the user clearly changes topic (e.g. reset phrases). You still have access to all tools above.\n\n";
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

        if ($humanoGuideAppendix !== null && trim($humanoGuideAppendix) !== '')
        {
            $instructions .= "\n\n---\n\n".trim($humanoGuideAppendix);
        } elseif ($withTools && $teamId !== null && $actorContext !== null)
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

        $tools = $withTools ? $this->buildLaravelAiTools($previewOnly) : [];

        $reply = $this->getReplyWithLaravelAi($message, $history, $instructions, $tools, $flowRoutedTo);
        if ($withTools && ($reply['success'] ?? false))
        {
            $committedKey = $this->routingKeyCommittedViaTools($reply['tool_results'] ?? []);
            if ($committedKey !== null)
            {
                $flowPersistSpecified = true;
                $flowPersistKey = $committedKey;
            }
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

            $usage = [];
            if (isset($response->usage))
            {
                $promptTokens = $response->usage->promptTokens ?? 0;
                $completionTokens = $response->usage->completionTokens ?? 0;
                $usage = [
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $promptTokens + $completionTokens,
                ];
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

            return [
                'success' => true,
                'text' => $text !== '' ? $text : 'No response text',
                'routed_to' => $routedTo,
                'usage' => $usage,
                'tool_calls' => $toolCalls,
                'tool_results' => $toolResults,
                'meta' => [],
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
     * Build laravel/ai Tool instances from AssistantToolsService definitions.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    protected function buildLaravelAiTools(bool $excludeWhatsAppSend = false): array
    {
        $tools = [];
        foreach ($this->assistantTools->getDefinitions() as $def)
        {
            if ($excludeWhatsAppSend && ($def['name'] ?? '') === 'send_whatsapp_message')
            {
                continue;
            }
            $tools[] = new AssistantTool(
                $this->assistantTools,
                $def['name'],
                $def['description'],
                $def['input_schema'] ?? ['type' => 'object', 'properties' => [], 'required' => []],
            );
        }

        return $tools;
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
            ? "\nIf the user is an admin, do not end your message with closing questions like \"¿Necesitás algo más?\", \"¿Algo más?\", \"¿En qué más puedo ayudarte?\" or similar; just end with the useful answer.\n"
            : '';

        return <<<EOT
You are the Humano CRM assistant. You HAVE REAL ACCESS to the user's data (contacts, tasks, team) through the tools below. The data is from their actual team and database — this is not a simulation or demo.
{$adminInstruction}

CURRENT DATE: Today is {$today} ({$todayLabel}). When the user says "hoy", "today", or "ahora" for a calendar event, you MUST use this date ({$today}) in start and end — e.g. "hoy a las 15" → start {$today} 15:00:00, end {$today} 15:30:00.

WhatsApp formatting: When the reply may be read on WhatsApp (including when you use send_whatsapp_message), write URLs as plain text (https://...) with NO Markdown bold or italics wrapping the URL. Patterns like **https://...** or *https://...* break link detection; use ** only around non-URL words if you need emphasis.
When replying for WhatsApp, keep it concise and human: 2-4 short sentences, no long blocks, no markdown tables, and avoid asterisk emphasis.

Bulk sheet import on WhatsApp (and Humano Assistant chat):
- Tasks: header line Concepto, Propuesta, Cliente, Importe (optional IVA, IRPF, Fecha envío, Estado, Nota), commas or semicolons. Optional prefix task.store is stripped. Creates tasks from one message.
- Invoices: same columns, but they MUST start the message with invoice.store (line before the header or same line). Cliente is matched to an enterprise by name or code; if there is no match, the invoice is saved as Borrador (draft) on a placeholder client for the team.
- Contacts: prefix contact.store, then a header with at least one of Nombre, Email, Teléfono/Móvil; optional Apellido, Empresa, Nota. Unknown extra columns are ignored.
Attached documents and images (cards, invoices, payment proofs):
- Never say you cannot read images or documents.
- If the user says they sent a file/photo/document, confirm processing and tell them they can track it in "Ver documentos" (/assistant/documents).
- Keep contact.store/invoice.store/task.store as optional manual fallback only when the user asks to paste data manually.
If they ask how to import, explain the matching prefix and headers (no tool call needed for the bulk paste).

When the user asks to see their contacts, list of contacts, "lista de contactos", tasks, report, summary, or similar, USE the appropriate tool:
- search_contacts (query) → find a person by name, email, or phone; returns contact id. Use whenever you need a contact id. NEVER ask the user for a contact id.
- get_contact_detail (contact_id or query) → returns full CRM detail for one contact (email, phone, status, categories, notes, profile URL).
- get_account_report with report_type "contacts" → list of contacts (real data from their team)
- get_account_report with report_type "tasks" → recent tasks
- get_account_report with report_type "summary" → counts of contacts and tasks
- list_contact_categories → all contact categories in the team
- list_contact_statuses → CRM lifecycle statuses for contacts (Lead, En seguimiento, Conversión, Perdido, Cliente, Finalizado, etc.); use exact names as contact_status_name when filtering campaign recipients
- get_contact_categories (with contact_id) → categories that a specific contact belongs to
- list_team_users → team members

When they ask to create or modify something, use:
- search_contacts before create_contact when the user names someone; use get_contact_detail when the user asks for one contact's full data; create_contact, update_contact (to add or change phone, email, or name), get_contact_categories (to see a contact's categories), assign_contact_to_category (to add another category to a contact), create_task, send_whatsapp_message
- Never say a contact was created, updated, or "already registered correctly" unless create_contact, update_contact, or get_contact_detail succeeded in this turn.

Tasks (kanban):
- search_tasks (query) → find a task by title fragment; returns task id and current status. Use BEFORE update_task_status when the user names a task. NEVER ask the user for a task id.
- list_task_statuses → TO_DO, IN_PROGRESS, REVIEW, DONE (and translated labels) when the target column is unclear.
- get_account_report with report_type "tasks" → recent tasks with ids.
- update_task_status (task_id, status) → move a task to another column when they ask to mark done/finalizada, send to review/revisión, start progress, back to por hacer, etc. Call the tool in the same turn once you have task_id and status — do not only confirm in text.
- NEVER tell the user a task status changed (e.g. "está ahora en En progreso", "marcada como finalizada") unless update_task_status ran successfully in this turn. If you proposed a change and the user confirms with sí/ok/dale/si/confirmo, call update_task_status immediately with the same task_id and status before replying.

Product catalog and WhatsApp PURCHASE flow (priority when the user wants to buy — team has products module):
- list_product_catalog (optional category_name) → full catalog with id, code, name, price. Use for "catálogo", "productos", "qué venden".
- search_products (query) → find by name or code. Use before offering to add to cart.
- add_to_whatsapp_cart (product_id OR product_code OR product_name; optional quantity) → YOU MUST call this tool as soon as the user confirms they want the product (e.g. "sí", "si", "dale", "ok", "agregalo", "quiero", "sí por favor", "añadilo", "mandale") after you showed them a specific product in the same conversation. Do NOT only reply with text — actually add to cart with the same id/code/name you found. If they confirm without naming again, use the product from your previous search_products / list result.
- After a successful add_to_whatsapp_cart, reply in Spanish with: what was added, cart reminder (*carrito*), that they can *quitar* cantidad y producto (or *quitar todo* el producto) to remove items, and next step: suggest only the word *finalizar* to close the order (the bot also accepts pagar/cerrar pedido/checkout, but do not list those to the customer). Say clearly that *SÍ* alone only confirms AFTER they run *finalizar*, not before.
- If the tool says there is no phone context, tell them to write *comprar* plus the product name or code from WhatsApp.

When they ask to schedule an event, appointment, or meeting ("agendar", "cita", "reunión", "evento", "reservar", "poner en el calendario"), use the calendar tools:
- search_contacts (query with the guest's name) → get contact id BEFORE create_calendar_event when a person is named. Do NOT ask the user for contact ids.
- If they also ask to add someone to the CRM ("agregar contacto", "nuevo contacto", a name like "Pepe"): call search_contacts first; if no match, call create_contact with that name only — email and phone are optional; do NOT ask for email/phone before trying create_contact.
- check_calendar_availability (start, end) → to see if the slot is free before confirming
- create_calendar_event (title, start, end; optional: guest_contact_ids, guest_name, notes, url, label) → to create the event. For "hoy"/"today" use the CURRENT DATE given above in start/end (e.g. {$today} 15:00:00). Use Y-m-d H:i:s as local wall-clock times (same as the user says, e.g. 14:00:00 to 15:00:00). For "mañana" use tomorrow; for weekday names use the next occurrence. When the meeting is with a CRM contact: use guest_contact_ids from search_contacts or create_contact in the same turn; guest_name also works as fallback. Never ask the user for ids and never offer "create without guest" unless they explicitly decline linking a contact. If only the meeting title and guest name are given but date/time is missing, ask in one short message for date and start time (default 1 hour duration if they do not specify end).
When they ask to edit, change, or modify an existing event ("modificar", "editar", "cambiar el evento", "cambia la hora de", "reprogramar"), use:
- list_calendar_events (start, end) → to find the event in that date range and get its id
- update_calendar_event (event_id, and only the fields to change: title, start, end, guest_contact_ids, notes, url, label, all_day) → to apply the change. Confirm the update briefly.

When they ask for their profile, "mis datos", "mi perfil", "quién soy", or "qué rol tengo", use get_my_profile and reply with the returned data in a friendly way.

Support tickets (if the team has the tickets module): When the user asks to create a ticket, "crear ticket", "abrir un ticket", or report an issue, use create_ticket (subject, description; optional priority: low, medium, high, urgent). When an admin asks to respond to a ticket, "responder al ticket X", "contestá el ticket #N", or "añade una respuesta al ticket", use add_ticket_response (ticket_id, message; optional is_internal_note true for internal notes not visible to the client).

CRM opportunities (if the team has the opportunities module): When the user asks to create or register an opportunity, "crear oportunidad", "nueva oportunidad", "registrar oportunidad", use create_opportunity with contact_id (numeric CRM contact id) and name (title). If you need valid stage slugs, call list_opportunity_stages first. Optional: stage_slug (qualification, proposal, negotiation, won, lost), opened_at (Y-m-d), description, estimated_amount, offering_summary. Optional responsible_email only for admins to assign another team member.

Email templates:
- List: "plantillas", "lista de plantillas" → list_templates.
- Create NEW only: Use create_template ONLY when the user explicitly asks to CREATE a new template ("crear plantilla", "nueva plantilla"). Always return the view and editor links. Do NOT use create_template when the user wants to change or modify an existing template — that would create a duplicate and lose the original.
- Modify EXISTING: When the user asks to change, edit, or modify an existing template ("cambia la plantilla", "modifica X", "cambia el nombre", "activa/suspende la plantilla"), first use list_templates to identify which template (by name or context from the conversation), then use update_template (template_id, name) for renaming or update_template_status (template_id, status) for activate/suspend. For design or content changes (colors, text, layout, HTML), do NOT create a new template; tell the user to open the editor link for that template so they can edit it without losing the current content.
- If it's unclear which template they mean, call list_templates and ask which one, or use the template they mentioned by name in the same conversation.

Campaign messages (News / newsletter / email campaigns):
- When the user asks to create a NEW campaign, newsletter, bulk email, "crear mensaje", "crear campaña", "crear News", use create_message only after you have (or the user already gave): **(1) subject/title** for the campaign, **(2) who receives it (destinatarios)** — spell this out as two independent filters, not vague "audiencia": (a) optional **contact category** (segmentación por categoría de contactos; names from list_contact_categories), and/or (b) optional **CRM contact status** (estado del contacto: Lead, En seguimiento, Conversión, Perdido, Cliente, Finalizado — exact names from list_contact_statuses, passed as contact_status_name), or they clearly want **all contacts** with no category filter and no status filter, **(3) what they want to communicate** — short text for the `text` field. If any of these are missing, ask in one concise message before calling tools. Then call list_templates; call list_contact_categories and/or list_contact_statuses when the user is unsure of names. Required: name, template_id, channel, text. Optional: category_name, contact_status_name, active (whether campaign **sending** starts on). After creation, the app may open the message editor automatically; still mention they can continue editing there.
- When the user asks to CHANGE an existing campaign (e.g. "enviar a categoría Staff y activarlo", "cambiar la campaña a categoría X", "activar la campaña", "solo a leads", "perdidos y seguimiento" after a campaign was just created or mentioned), do NOT use create_message — that would create a duplicate. Use list_messages to identify the campaign (same name or the one just created, usually the last or the one with the same name), get its message_id, then call update_message with message_id and the requested changes (category_name, contact_status_name, status: "active" or "paused" for **campaign sending**). Only one campaign should exist; updating keeps the same ID and links.
- To list campaign messages: "lista de campañas", "mensajes", "campañas" → list_messages.
- To only stop or activate sending (no change to category or CRM contact status): update_message_status (message_id, status: "paused" or "active"). For change category and/or CRM contact-status filter and/or sending in one go: update_message (message_id, category_name?, contact_status_name?, status?).
- Spanish UX: In Humano, **estado del contacto** (Lead, En seguimiento, Conversión, Perdido, Cliente, Finalizado, …) is the **CRM lifecycle** filter for who receives the campaign; **categoría** is a separate audience filter. Whether the newsletter **sends** is **envío de la campaña** / **campaña pausada o enviando**. Never summarize sending on/off using the bare word **"Estado"** — say **envío activo/pausado**, **envío de la campaña**, or **campaña en pausa** so it is not confused with contact status.

Topic locking: When a team flow is active for this thread, stay on that topic until it is resolved (e.g. payment sent, order placed) or the user clearly wants to switch topic. Do not jump to the product catalog or shopping tools during billing or support unless the user clearly asks about buying. When the instructions include "Conversation flow (discovery mode)", ask at most one short clarifying question if needed, then call commit_assistant_flow with the exact routing_key once intent is clear.

IMPORTANT: Never reply that you "do not have access" to contacts/tasks/database, that "this is a simulation", that you have "no real data", or that you are "not connected to any system". You ARE connected: use the tools and return the real results. If the user asks to confirm something you already showed (e.g. a list), confirm it briefly with the same data. If a tool returns an error, explain it and suggest what to do next.
NEVER invent "problema técnico", "problema momentáneo", "problema con la base de datos", or that contact search is broken. "No contacts found" means: call create_contact with the name the user gave (or ask one clarifying question if the name is ambiguous). "You do not have permission" means: say their role cannot do that action — do not blame search or the system. If a tool failed internally, retry create_contact or create_calendar_event with guest_name — never tell the user the database is down.
When proposing meeting times (e.g. after another event ends at 11:00), call check_calendar_availability and create_calendar_event in the same turn once the user confirms — do not only ask in text without calling tools.
EOT;
    }

    /**
     * Extra system instructions when the acting user is a Jetstream client (or guest/user) on this team.
     */
    private function customerTeamRoleInstructionsAppendix(): string
    {
        return <<<'EOT'


### User role on this team (limited customer)

This user has a **limited** assistant profile on this team (customer / guest, or without CRM create permissions). Do not use internal CRM bulk tools, campaigns, templates, team-wide calendar, or account reports unless a tool succeeds. Prefer conversational help, catalog/cart, support tickets, and WhatsApp replies in this thread only. If they ask for internal staff actions they cannot perform, say the business team must do it from the app or grant the needed access.
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

Escriben desde WhatsApp. Priorizá entender qué necesitan **en conversación** (no hace falta una sola frase adivinando el tema).

- El bloque **Contexto del negocio (configuración del equipo)**, arriba, es referencia: **no inventes** ofertas ni servicios; usalo para interpretar términos ambiguos y para el tono.
- **No asumas** que el mensaje es de compra o catálogo. Palabras como *agregar*, *cita*, *turno*, *reunión*, *visita* suelen ser agenda u otro trámite; el carrito de productos es **solo** si el contexto o el propio mensaje dejan claro que quieren **comprar** o manejan **productos** explícitamente.
- Si la intención no está clara, hacé **una** pregunta corta de aclaración (o 2–3 opciones) alineada con lo que ofrece el negocio según el contexto. Si ya está claro (misma conversación o mensaje inequívoco), seguí sin re-preguntar de más.
- Cuando (y solo cuando) quede claro un objetivo de **venta/catálogo**, usá las herramientas de búsqueda y carrito (p. ej. *add_to_whatsapp_cart*) según corresponde.
- Si aplica, la sección *Conversation flow (discovery mode)* indica claves: usá **commit_assistant_flow** con la routing_key adecuada.
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
