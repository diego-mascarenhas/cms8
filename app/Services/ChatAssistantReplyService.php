<?php

namespace App\Services;

use App\Models\Prompt;
use App\Tools\AssistantTool;
use Laravel\Ai\Enums\Lab;
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
    ) {}

    /**
     * Get assistant reply for the given message and history.
     * When stub mode is enabled (config or team), returns a canned response for testing.
     * When writing via WhatsApp, pass contextUserId so tools (e.g. get_my_profile) run as that user.
     * When $forcedFlowRoutingKey is set (module_prompts routing key), that team flow prompt is merged instead of intent detection.
     * When $contactId is set and the flow is invoices:collections, CRM + Stripe invoice context is appended for that contact.
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @return array{
     *     success: bool,
     *     text?: string,
     *     message?: string,
     *     routed_to?: string|null,
     *     assistant_flow_routing_key_specified: bool,
     *     assistant_flow_routing_key: ?string,
     * }
     */
    public function getReply(string $message, array $history = [], ?int $teamId = null, bool $withTools = false, ?int $contextUserId = null, ?string $contextCustomerPhone = null, ?string $forcedFlowRoutingKey = null, ?int $contactId = null): array
    {
        if ($this->useStub($teamId))
        {
            return $this->mergeFlowPersistMeta($this->getStubReply($message), false, null);
        }

        $this->assistantTools->clearRequestContext();
        if ($withTools && $teamId !== null)
        {
            $this->assistantTools->setRequestContext($contextUserId, $teamId, $contextCustomerPhone);
        }

        $flowRoutedTo = null;
        $flowPersistSpecified = false;
        $flowPersistKey = null;
        $instructions = $withTools
            ? $this->getAssistantToolsSystemPrompt($contextUserId)
            : AssistantSystemPrompt::get();

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
                if ($flowPrompt && $contactId !== null && $contactId > 0 && $teamId !== null)
                {
                    $flowPrompt->loadMissing('module');
                    $routingKey = $flowPrompt->module
                        ? $flowPrompt->module->key.':'.$flowPrompt->section_key
                        : $flowPrompt->section_key;
                    if ($routingKey === 'invoices:collections')
                    {
                        $collectionsContext = $this->collectionAssistantContext->buildMarkdownForContact($contactId, $teamId);
                        if ($collectionsContext !== '')
                        {
                            $instructions .= "\n\n---\n\n".$collectionsContext;
                        }
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
        }

        $tools = $withTools ? $this->buildLaravelAiTools() : [];

        return $this->mergeFlowPersistMeta(
            $this->getReplyWithLaravelAi($message, $history, $instructions, $tools, $flowRoutedTo),
            $flowPersistSpecified,
            $flowPersistKey,
        );
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
        try
        {
            $historyMessages = $this->historyToMessages($history);

            $agent = agent(
                instructions: $instructions,
                messages: $historyMessages,
                tools: $tools,
            );

            $response = $agent->prompt($message, [], Lab::Anthropic);
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
            $body = $item['body'] ?? '';
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
     * Build laravel/ai Tool instances from AssistantToolsService definitions.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    protected function buildLaravelAiTools(): array
    {
        $tools = [];
        foreach ($this->assistantTools->getDefinitions() as $def)
        {
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

When the user asks to see their contacts, list of contacts, "lista de contactos", tasks, report, summary, or similar, USE the appropriate tool:
- get_account_report with report_type "contacts" → list of contacts (real data from their team)
- get_account_report with report_type "tasks" → recent tasks
- get_account_report with report_type "summary" → counts of contacts and tasks
- list_contact_categories → all contact categories in the team
- get_contact_categories (with contact_id) → categories that a specific contact belongs to
- list_team_users → team members

When they ask to create or modify something, use:
- create_contact, update_contact (to add or change phone, email, or name), get_contact_categories (to see a contact's categories), assign_contact_to_category (to add another category to a contact), create_task, send_whatsapp_message

Product catalog and WhatsApp PURCHASE flow (priority when the user wants to buy — team has products module):
- list_product_catalog (optional category_name) → full catalog with id, code, name, price. Use for "catálogo", "productos", "qué venden".
- search_products (query) → find by name or code. Use before offering to add to cart.
- add_to_whatsapp_cart (product_id OR product_code OR product_name; optional quantity) → YOU MUST call this tool as soon as the user confirms they want the product (e.g. "sí", "si", "dale", "ok", "agregalo", "quiero", "sí por favor", "añadilo", "mandale") after you showed them a specific product in the same conversation. Do NOT only reply with text — actually add to cart with the same id/code/name you found. If they confirm without naming again, use the product from your previous search_products / list result.
- After a successful add_to_whatsapp_cart, reply in Spanish with: what was added, cart reminder (*carrito*), and next step (*checkout* to close the order). Say clearly that *SÍ* alone only confirms checkout AFTER they run *checkout*, not before.
- If the tool says there is no phone context, tell them to write *comprar [nombre o código]* from WhatsApp.

When they ask to schedule an event, appointment, or meeting ("agendar", "cita", "reunión", "evento", "reservar", "poner en el calendario"), use the calendar tools:
- check_calendar_availability (start, end) → to see if the slot is free before confirming
- create_calendar_event (title, start, end; optional: notes, url, label) → to create the event. For "hoy"/"today" use the CURRENT DATE given above in start/end (e.g. {$today} 15:00:00). Use ISO or Y-m-d H:i format. For "mañana" use tomorrow; for weekday names use the next occurrence. Confirm the created event briefly (title, date/time).
When they ask to edit, change, or modify an existing event ("modificar", "editar", "cambiar el evento", "cambia la hora de", "reprogramar"), use:
- list_calendar_events (start, end) → to find the event in that date range and get its id
- update_calendar_event (event_id, and only the fields to change: title, start, end, notes, url, label, all_day) → to apply the change. Confirm the update briefly.

When they ask for their profile, "mis datos", "mi perfil", "quién soy", or "qué rol tengo", use get_my_profile and reply with the returned data in a friendly way.

Support tickets (if the team has the tickets module): When the user asks to create a ticket, "crear ticket", "abrir un ticket", or report an issue, use create_ticket (subject, description; optional priority: low, medium, high, urgent). When an admin asks to respond to a ticket, "responder al ticket X", "contestá el ticket #N", or "añade una respuesta al ticket", use add_ticket_response (ticket_id, message; optional is_internal_note true for internal notes not visible to the client).

Email templates:
- List: "plantillas", "lista de plantillas" → list_templates.
- Create NEW only: Use create_template ONLY when the user explicitly asks to CREATE a new template ("crear plantilla", "nueva plantilla"). Always return the view and editor links. Do NOT use create_template when the user wants to change or modify an existing template — that would create a duplicate and lose the original.
- Modify EXISTING: When the user asks to change, edit, or modify an existing template ("cambia la plantilla", "modifica X", "cambia el nombre", "activa/suspende la plantilla"), first use list_templates to identify which template (by name or context from the conversation), then use update_template (template_id, name) for renaming or update_template_status (template_id, status) for activate/suspend. For design or content changes (colors, text, layout, HTML), do NOT create a new template; tell the user to open the editor link for that template so they can edit it without losing the current content.
- If it's unclear which template they mean, call list_templates and ask which one, or use the template they mentioned by name in the same conversation.

Campaign messages (News / Campañas):
- When the user asks to create a NEW campaign, "crear mensaje", "crear campaña", "crear News", use create_message. Call list_templates (and list_contact_categories if needed). Required: name, template_id, channel, text. Optional: category_name, contact_status_name, active. Return the edit and view/send links.
- When the user asks to CHANGE an existing campaign (e.g. "enviar a categoría Staff y activarlo", "cambiar la campaña a categoría X", "activar la campaña", "poner en categoría Y" after a campaign was just created or mentioned), do NOT use create_message — that would create a duplicate. Use list_messages to identify the campaign (same name or the one just created, usually the last or the one with the same name), get its message_id, then call update_message with message_id and the requested changes (category_name, contact_status_name, status: "active" or "paused"). Only one campaign should exist; updating keeps the same ID and links.
- To list campaign messages: "lista de campañas", "mensajes", "campañas" → list_messages.
- To only stop or activate (no category change): update_message_status (message_id, status: "paused" or "active"). For change category and/or activate in one go: update_message (message_id, category_name?, contact_status_name?, status?).

IMPORTANT: Never reply that you "do not have access" to contacts/tasks/database, that "this is a simulation", that you have "no real data", or that you are "not connected to any system". You ARE connected: use the tools and return the real results. If the user asks to confirm something you already showed (e.g. a list), confirm it briefly with the same data. If a tool returns an error, explain it and suggest what to do next.
EOT;
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
