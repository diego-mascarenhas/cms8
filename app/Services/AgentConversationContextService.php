<?php

namespace App\Services;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use Illuminate\Support\Str;

class AgentConversationContextService
{
    public const AGENT_NAME = 'chat_assistant';

    public const DEFAULT_HISTORY_LIMIT = 20;

    /**
     * Resolve team ID for scoping: from auth user's current team, or null (conversations without team stay hidden in team context).
     */
    private function resolveTeamId(?int $teamId = null): ?int
    {
        if ($teamId !== null)
        {
            return $teamId;
        }

        return auth()->check() && auth()->user()->currentTeam
            ? (int) auth()->user()->currentTeam->id
            : null;
    }

    /**
     * Get or create an agent conversation for the given user_id, scoped to the given team (or current user's team).
     */
    public function getOrCreateConversation(int $userId, string $title = 'Chat', ?int $teamId = null): AgentConversation
    {
        $teamId = $this->resolveTeamId($teamId);
        $query = AgentConversation::where('user_id', $userId)
            ->whereHas('messages', fn ($q) => $q->where('agent', self::AGENT_NAME))
            ->orderByDesc('updated_at');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        } else
        {
            $query->whereNull('team_id');
        }

        $conversation = $query->first();

        if ($conversation)
        {
            return $conversation;
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'title' => $this->normalizeConversationTitle($title),
        ];
        if ($teamId !== null)
        {
            $payload['team_id'] = $teamId;
        }

        return AgentConversation::create($payload);
    }

    /**
     * Load last N messages from the conversation for the given user_id, formatted for Claude.
     * Returns array of ['direction' => 'inbound'|'outbound', 'body' => string].
     *
     * @return array<int, array{direction: string, body: string}>
     */
    public function getHistoryForPrompt(int $userId, int $limit = self::DEFAULT_HISTORY_LIMIT, ?int $teamId = null): array
    {
        $teamId = $this->resolveTeamId($teamId);
        $query = AgentConversation::where('user_id', $userId)
            ->whereHas('messages', fn ($q) => $q->where('agent', self::AGENT_NAME))
            ->orderByDesc('updated_at');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        } else
        {
            $query->whereNull('team_id');
        }

        $conversation = $query->first();

        if (! $conversation)
        {
            return [];
        }

        $messages = $conversation->messages()
            ->where('agent', self::AGENT_NAME)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return $messages->map(fn (AgentConversationMessage $m) => [
            'direction' => $m->role === 'user' ? 'inbound' : 'outbound',
            'body' => $m->content,
        ])->values()->toArray();
    }

    /**
     * Get messages for display in the UI (e.g. web chat). Scoped to team so each team only sees its own assistant conversations.
     *
     * @return array<int, array{role: string, content: string, created_at: \Carbon\Carbon}>
     */
    public function getMessagesForDisplay(int $userId, int $limit = 50, ?int $teamId = null): array
    {
        $teamId = $this->resolveTeamId($teamId);
        $query = AgentConversation::where('user_id', $userId)
            ->whereHas('messages', fn ($q) => $q->where('agent', self::AGENT_NAME))
            ->orderByDesc('updated_at');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        } else
        {
            $query->whereNull('team_id');
        }

        $conversation = $query->first();

        if (! $conversation)
        {
            return [];
        }

        return $conversation->messages()
            ->where('agent', self::AGENT_NAME)
            ->orderBy('created_at')
            ->orderByRaw("CASE WHEN role = 'user' THEN 0 ELSE 1 END")
            ->limit($limit)
            ->get()
            ->map(fn (AgentConversationMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Persist only the agent's reply (when toggle is OFF - human reply). Keeps conversation context for the AI.
     *
     * @param  array<string, int>  $usage  e.g. ['prompt_tokens' => 1, 'completion_tokens' => 2, 'total_tokens' => 3]
     * @param  array<string, mixed>  $meta
     * @param  array<int, mixed>  $toolCalls
     * @param  array<int, mixed>  $toolResults
     */
    public function persistAgentReply(
        int $userId,
        string $assistantContent,
        array $usage = [],
        array $meta = [],
        array $toolCalls = [],
        array $toolResults = [],
        ?int $teamId = null,
    ): void {
        $conversation = $this->getOrCreateConversation($userId, 'Chat', $teamId);

        $conversation->touch();

        $actorId = auth()->id() ?? $userId;

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $actorId,
            'agent' => self::AGENT_NAME,
            'role' => 'assistant',
            'content' => $assistantContent,
            'attachments' => [],
            'tool_calls' => $toolCalls,
            'tool_results' => $toolResults,
            'usage' => $usage,
            'meta' => $meta,
        ]);
    }

    /**
     * Append user and assistant messages to the conversation and update conversation timestamp.
     *
     * @param  array<string, int>  $assistantUsage  e.g. ['prompt_tokens' => 1, 'completion_tokens' => 2, 'total_tokens' => 3]
     * @param  array<string, mixed>  $assistantMeta  e.g. ['routed_to' => '...']
     * @param  array<int, mixed>  $assistantToolCalls
     * @param  array<int, mixed>  $assistantToolResults
     */
    public function persistMessages(
        int $userId,
        string $userContent,
        string $assistantContent,
        ?string $routedTo = null,
        array $assistantUsage = [],
        array $assistantMeta = [],
        array $assistantToolCalls = [],
        array $assistantToolResults = [],
        ?int $teamId = null,
    ): void {
        $conversation = $this->getOrCreateConversation(
            $userId,
            $this->previewTitleFromUserMessage($userContent),
            $teamId,
        );

        $conversation->touch();

        $actorId = auth()->id() ?? $userId;

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $actorId,
            'agent' => self::AGENT_NAME,
            'role' => 'user',
            'content' => $userContent,
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);

        $meta = $routedTo !== null ? array_merge($assistantMeta, ['routed_to' => $routedTo]) : $assistantMeta;

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $actorId,
            'agent' => self::AGENT_NAME,
            'role' => 'assistant',
            'content' => $assistantContent,
            'attachments' => [],
            'tool_calls' => $assistantToolCalls,
            'tool_results' => $assistantToolResults,
            'usage' => $assistantUsage,
            'meta' => $meta,
        ]);
    }

    /**
     * Ensure title fits MySQL varchar(255) safely (multibyte / promos pasted on WhatsApp).
     */
    private function normalizeConversationTitle(string $title): string
    {
        $t = preg_replace('/\s+/u', ' ', trim($title));
        if ($t === '')
        {
            return 'Chat';
        }

        if (function_exists('mb_substr'))
        {
            return mb_substr($t, 0, 191);
        }

        return substr($t, 0, 191);
    }

    /**
     * Short preview for new conversations (full body stays in agent_conversation_messages.content).
     */
    private function previewTitleFromUserMessage(string $userContent): string
    {
        $t = preg_replace('/\s+/u', ' ', trim($userContent));
        if ($t === '')
        {
            return 'Chat';
        }

        if (function_exists('mb_substr'))
        {
            return mb_substr($t, 0, 100);
        }

        return substr($t, 0, 100);
    }
}
