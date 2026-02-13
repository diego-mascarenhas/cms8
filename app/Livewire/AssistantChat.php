<?php

namespace App\Livewire;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Services\AssistantChatService;
use Illuminate\Support\Str;
use Livewire\Component;

class AssistantChat extends Component
{
    public const AGENT_NAME = 'assistant_chat';

    /** @var array<int, array{role: string, content: string, routed_to: string|null}> */
    public array $messages = [];

    public string $input = '';

    public bool $loading = false;

    public ?string $conversationId = null;

    public function mount(): void
    {
        if (! auth()->check())
        {
            return;
        }

        $conversation = AgentConversation::where('user_id', auth()->id())
            ->whereHas('messages', fn ($q) => $q->where('agent', self::AGENT_NAME))
            ->orderByDesc('updated_at')
            ->first();

        if ($conversation)
        {
            $this->conversationId = $conversation->id;
            $this->messages = $conversation->messages()
                ->where('agent', self::AGENT_NAME)
                ->orderBy('created_at')
                ->get()
                ->map(fn (AgentConversationMessage $m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                    'routed_to' => $m->meta['routed_to'] ?? null,
                ])
                ->toArray();
        }
    }

    public function sendMessage(AssistantChatService $assistant): void
    {
        $text = trim($this->input);
        if ($text === '' || $this->loading)
        {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $text,
            'routed_to' => null,
        ];
        $this->input = '';
        $this->loading = true;

        $teamId = auth()->check() && auth()->user()->currentTeam
            ? auth()->user()->currentTeam->id
            : null;

        $result = $assistant->run($text, $teamId);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $result['response'],
            'routed_to' => $result['routed_to'],
        ];
        $this->loading = false;

        if (auth()->check())
        {
            $this->persistMessages($text, $result['response'], $result['routed_to']);
        }

        $this->dispatch('scroll-to-bottom');
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $this->conversationId = null;
    }

    protected function persistMessages(string $userContent, string $assistantContent, ?string $routedTo): void
    {
        $userId = auth()->id();
        if (! $userId)
        {
            return;
        }

        if ($this->conversationId === null)
        {
            $conversation = AgentConversation::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'title' => Str::limit($userContent, 50),
            ]);
            $this->conversationId = $conversation->id;
        }

        $conversation = AgentConversation::find($this->conversationId);
        if (! $conversation)
        {
            return;
        }

        $conversation->touch();

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'agent' => self::AGENT_NAME,
            'role' => 'user',
            'content' => $userContent,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'agent' => self::AGENT_NAME,
            'role' => 'assistant',
            'content' => $assistantContent,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => ['routed_to' => $routedTo],
        ]);
    }

    public function render()
    {
        return view('livewire.assistant-chat');
    }
}
