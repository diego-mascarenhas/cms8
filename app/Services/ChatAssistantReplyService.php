<?php

namespace App\Services;

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
    ) {}

    /**
     * Get assistant reply for the given message and history.
     * When stub mode is enabled (config or team), returns a canned response for testing.
     * When writing via WhatsApp, pass contextUserId so tools (e.g. get_my_profile) run as that user.
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @return array{success: bool, text?: string, message?: string, routed_to?: string|null}
     */
    public function getReply(string $message, array $history = [], ?int $teamId = null, bool $withTools = false, ?int $contextUserId = null): array
    {
        if ($this->useStub($teamId))
        {
            return $this->getStubReply($message);
        }

        if ($withTools && $contextUserId !== null && $teamId !== null)
        {
            $this->assistantTools->setRequestContext($contextUserId, $teamId);
        }

        $instructions = $withTools
            ? $this->getAssistantToolsSystemPrompt($contextUserId)
            : AssistantSystemPrompt::get();
        $tools = $withTools ? $this->buildLaravelAiTools() : [];

        return $this->getReplyWithLaravelAi($message, $history, $instructions, $tools);
    }

    /**
     * Use laravel/ai agent (Prism gateway) for assistant reply.
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @param  array<int, \Laravel\Ai\Contracts\Tool>  $tools
     * @return array{success: bool, text?: string, message?: string, routed_to?: string|null}
     */
    protected function getReplyWithLaravelAi(string $message, array $history, string $instructions, array $tools = []): array
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
                'routed_to' => null,
                'usage' => $usage,
                'tool_calls' => $toolCalls,
                'tool_results' => $toolResults,
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

When the user asks to see their contacts, list of contacts, "lista de contactos", tasks, report, summary, or similar, USE the appropriate tool:
- get_account_report with report_type "contacts" → list of contacts (real data from their team)
- get_account_report with report_type "tasks" → recent tasks
- get_account_report with report_type "summary" → counts of contacts and tasks
- list_contact_categories → all contact categories in the team
- get_contact_categories (with contact_id) → categories that a specific contact belongs to
- list_team_users → team members

When they ask to create or modify something, use:
- create_contact, update_contact (to add or change phone, email, or name), get_contact_categories (to see a contact's categories), assign_contact_to_category (to add another category to a contact), create_task, send_whatsapp_message

When they ask to schedule an event, appointment, or meeting ("agendar", "cita", "reunión", "evento", "reservar", "poner en el calendario"), use the calendar tools:
- check_calendar_availability (start, end) → to see if the slot is free before confirming
- create_calendar_event (title, start, end; optional: notes, url, label) → to create the event. For "hoy"/"today" use the CURRENT DATE given above in start/end (e.g. {$today} 15:00:00). Use ISO or Y-m-d H:i format. For "mañana" use tomorrow; for weekday names use the next occurrence. Confirm the created event briefly (title, date/time).
When they ask to edit, change, or modify an existing event ("modificar", "editar", "cambiar el evento", "cambia la hora de", "reprogramar"), use:
- list_calendar_events (start, end) → to find the event in that date range and get its id
- update_calendar_event (event_id, and only the fields to change: title, start, end, notes, url, label, all_day) → to apply the change. Confirm the update briefly.

When they ask for their profile, "mis datos", "mi perfil", "quién soy", or "qué rol tengo", use get_my_profile and reply with the returned data in a friendly way.

Support tickets (if the team has the tickets module): When the user asks to create a ticket, "crear ticket", "abrir un ticket", or report an issue, use create_ticket (subject, description; optional priority: low, medium, high, urgent). When an admin asks to respond to a ticket, "responder al ticket X", "contestá el ticket #N", or "añade una respuesta al ticket", use add_ticket_response (ticket_id, message; optional is_internal_note true for internal notes not visible to the client).

Email templates: When the user asks to list templates, "plantillas", "lista de plantillas", or "qué plantillas hay", use list_templates. When they ask to create a template, "crear plantilla", "nueva plantilla", use create_template (name; optional ai_prompt to generate HTML with AI). Always include in your reply the view link and editor link returned by create_template so they can open or share the template and continue editing. When they ask to activate or suspend a template ("activar plantilla X", "suspender plantilla Y", "desactivar la plantilla"), use update_template_status (template_id, status: active or suspended). When they ask to rename or change template details ("cambiar el nombre de la plantilla", "renombrar plantilla"), use update_template (template_id, name). For changing the HTML content of a template, tell them to open the editor link they received when the template was created, or from list_templates they can go to the template list and open the editor from there.

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
        ];
    }
}
