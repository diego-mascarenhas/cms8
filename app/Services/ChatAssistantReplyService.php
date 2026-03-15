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
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @return array{success: bool, text?: string, message?: string, routed_to?: string|null}
     */
    public function getReply(string $message, array $history = [], ?int $teamId = null, bool $withTools = false): array
    {
        if ($this->useStub($teamId))
        {
            return $this->getStubReply($message);
        }

        $instructions = $withTools
            ? $this->getAssistantToolsSystemPrompt()
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
    protected function getAssistantToolsSystemPrompt(): string
    {
        return <<<'EOT'
You are a helpful assistant for the Humano CRM. The user can ask you to manage contacts, tasks, send WhatsApp messages, or get account reports.

When the user asks to create a contact, create a task, assign a contact to a category, send a WhatsApp message, or get a report (e.g. "reporte de cuentas", "resumen"), use the appropriate tool. You have tools to:
- list_contact_categories: list contact categories
- create_contact: create a contact (name required; optional email, phone, category_name — category is created if it does not exist)
- assign_contact_to_category: assign a contact to a category by contact_id and category_name
- get_account_report: get a summary (report_type: summary), list of contacts (report_type: contacts), or recent tasks (report_type: tasks)
- send_whatsapp_message: send a WhatsApp message (contact_id or phone, and message)
- create_task: create a task (title required; optional description, responsible_email, due_days)
- list_team_users: list team members for task assignment

After running a tool, summarize the result in a short, friendly reply in the same language as the user. If the user did not ask for an action, answer normally without calling tools. If a tool returns an error, explain it clearly and suggest what to do next.
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
