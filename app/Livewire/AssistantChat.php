<?php

namespace App\Livewire;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Team;
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

    public ?string $promptKey = null;

    public function mount(?string $promptKey = null): void
    {
        $this->promptKey = $promptKey;

        if (! auth()->check())
        {
            return;
        }

        $teamId = auth()->user()->currentTeam?->id;
        $query = AgentConversation::where('user_id', auth()->id())
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
            'image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,bmp,svg|max:20480',
            'audio' => 'nullable|file|mimes:mp3,wav,m4a,webm,ogg,mp4,mpeg|max:25600',
        ], [
            'image.mimes' => __('El archivo debe ser una imagen (JPG, PNG, GIF, WebP, BMP o SVG).'),
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

        $teamId = null;
        if (auth()->check())
        {
            $user = auth()->user();
            $team = $user->currentTeam ?? $user->teams()->first();
            $teamId = $team?->id;
        }
        if ($teamId === null && config('app.assistant_default_team_id'))
        {
            $defaultId = (int) config('app.assistant_default_team_id');
            if (Team::withoutGlobalScopes()->find($defaultId))
            {
                $teamId = $defaultId;
            }
        }

        $result = $assistant->run($text, $teamId, $this->image, $this->audio, $this->respondWithAudio, $this->promptKey);

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
            $this->persistMessages(
                $userContent,
                $result['response'],
                $result['routed_to'],
                $result['usage'] ?? [],
                $result['tool_calls'] ?? [],
                $result['tool_results'] ?? [],
            );
        }

        $this->dispatch('scroll-to-bottom');
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $this->conversationId = null;
    }

    /**
     * @param  array<string, int>  $usage
     * @param  array<int, mixed>  $toolCalls
     * @param  array<int, mixed>  $toolResults
     */
    protected function persistMessages(
        string $userContent,
        string $assistantContent,
        ?string $routedTo,
        array $usage = [],
        array $toolCalls = [],
        array $toolResults = [],
    ): void {
        $userId = auth()->id();
        if (! $userId)
        {
            return;
        }

        if ($this->conversationId === null)
        {
            $teamId = auth()->user()->currentTeam?->id;
            $payload = [
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'title' => Str::limit($userContent, 50),
            ];
            if ($teamId !== null)
            {
                $payload['team_id'] = $teamId;
            }
            $conversation = AgentConversation::create($payload);
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
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'agent' => self::AGENT_NAME,
            'role' => 'assistant',
            'content' => $assistantContent,
            'attachments' => [],
            'tool_calls' => $toolCalls,
            'tool_results' => $toolResults,
            'usage' => $usage,
            'meta' => array_filter(['routed_to' => $routedTo]),
        ]);
    }

    public function render()
    {
        return view('livewire.assistant-chat');
    }
}
