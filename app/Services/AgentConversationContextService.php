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
     * Get or create an agent conversation for the given user_id.
     */
    public function getOrCreateConversation(int $userId, string $title = 'Chat'): AgentConversation
    {
        $conversation = AgentConversation::where('user_id', $userId)
            ->whereHas('messages', fn ($q) => $q->where('agent', self::AGENT_NAME))
            ->orderByDesc('updated_at')
            ->first();

        if ($conversation)
        {
            return $conversation;
        }

        return AgentConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'title' => Str::limit($title, 255),
        ]);
    }

    /**
     * Load last N messages from the conversation for the given user_id, formatted for Claude.
     * Returns array of ['direction' => 'inbound'|'outbound', 'body' => string].
     *
     * @return array<int, array{direction: string, body: string}>
     */
    public function getHistoryForPrompt(int $userId, int $limit = self::DEFAULT_HISTORY_LIMIT): array
    {
        $conversation = AgentConversation::where('user_id', $userId)
            ->whereHas('messages', fn ($q) => $q->where('agent', self::AGENT_NAME))
            ->orderByDesc('updated_at')
            ->first();

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
     * Get messages for display in the UI (e.g. web chat). Same conversation as terminal chat:simulate.
     *
     * @return array<int, array{role: string, content: string, created_at: \Carbon\Carbon}>
     */
    public function getMessagesForDisplay(int $userId, int $limit = 50): array
    {
        $conversation = AgentConversation::where('user_id', $userId)
            ->whereHas('messages', fn ($q) => $q->where('agent', self::AGENT_NAME))
            ->orderByDesc('updated_at')
            ->first();

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
    ): void {
        $conversation = $this->getOrCreateConversation($userId, 'Chat');

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
    ): void {
        $conversation = $this->getOrCreateConversation($userId, $userContent);

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
}
