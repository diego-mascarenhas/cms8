<?php

namespace App\Livewire;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Services\AssistantChatService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class AssistantChat extends Component
{
    use WithFileUploads;

    public const AGENT_NAME = 'assistant_chat';

    /** @var array<int, array{role: string, content: string, routed_to: string|null, audio_base64?: string, audio_mime?: string}> */
    public array $messages = [];

    public string $input = '';

    public bool $loading = false;

    public ?string $conversationId = null;

    public $image = null;

    public $audio = null;

    public bool $respondWithAudio = false;

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
        $text = trim($this->input ?? '');
        $hasImage = $this->image !== null;
        $hasAudio = $this->audio !== null;

        if ($text === '' && ! $hasImage && ! $hasAudio)
        {
            return;
        }
        if ($this->loading)
        {
            return;
        }

        $this->validate([
            'image' => 'nullable|image|max:20480',
            'audio' => 'nullable|file|mimes:mp3,wav,m4a,webm,ogg,mp4,mpeg|max:25600',
        ], [
            'image.image' => __('El archivo debe ser una imagen.'),
            'image.max' => __('La imagen no puede superar 20 MB.'),
            'audio.mimes' => __('El audio debe ser mp3, wav, m4a, webm u ogg.'),
            'audio.max' => __('El audio no puede superar 25 MB.'),
        ]);

        $userContent = $text !== '' ? $text : __('[Imagen o audio adjunto]');
        $this->messages[] = [
            'role' => 'user',
            'content' => $userContent,
            'routed_to' => null,
        ];
        $this->input = '';
        $this->loading = true;

        $teamId = auth()->check() && auth()->user()->currentTeam
            ? auth()->user()->currentTeam->id
            : null;

        $result = $assistant->run($text, $teamId, $this->image, $this->audio, $this->respondWithAudio);

        $assistantMessage = [
            'role' => 'assistant',
            'content' => $result['response'],
            'routed_to' => $result['routed_to'],
        ];
        if (! empty($result['audio_base64']) && ! empty($result['audio_mime']))
        {
            $assistantMessage['audio_base64'] = $result['audio_base64'];
            $assistantMessage['audio_mime'] = $result['audio_mime'];
        }
        $this->messages[] = $assistantMessage;
        $this->loading = false;

        $this->image = null;
        $this->audio = null;

        if (auth()->check())
        {
            $this->persistMessages($userContent, $result['response'], $result['routed_to']);
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
