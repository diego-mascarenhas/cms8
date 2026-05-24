<?php

namespace App\Livewire;

use App\Models\TaskStatus;
use App\Models\Team;
use App\Services\AdminProactiveOutreachSlashDispatcher;
use App\Services\AgentConversationContextService;
use App\Services\Assistant\AssistantInboundTaskStatusService;
use App\Services\AssistantChatService;
use App\Services\ChatAssistantReplyService;
use App\Services\PerformanceInsightSlashDispatcher;
use App\Support\AssistantCreatedMessageRedirect;
use App\Support\AssistantTaskStatusUpdate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;
use Livewire\Component;
use Livewire\WithFileUploads;

class AssistantChat extends Component
{
    use WithFileUploads;

    /**
     * Legacy agent id for {@see AssistantChatService}-only flows (guests or uploads). In-app assistant uses {@see AgentConversationContextService::AGENT_NAME}.
     */
    public const AGENT_NAME = 'assistant_chat';

    /** @var array<int, array{role: string, content: string, routed_to: string|null, audio_base64?: string, audio_mime?: string}> */
    public array $messages = [];

    public string $input = '';

    public bool $loading = false;

    public ?string $conversationId = null;

    public $image = null;

    public $audio = null;

    public bool $respondWithAudio = false;

    /** When non-null and non-blank, forces that routing key in the same way as the chat assistant (Humano Assistant). */
    public ?string $promptKey = null;

    /** When true (e.g. layout FAB panel), the card toolbar (flow title, voice toggle, new chat) is omitted. */
    public bool $hideHeader = false;

    protected $listeners = [
        'assistant-reset-context' => 'clearChat',
    ];

    public function mount(
        AgentConversationContextService $conversationContext,
        ?string $promptKey = null,
        bool $hideHeader = false,
    ): void {
        $this->promptKey = $promptKey;
        $this->hideHeader = $hideHeader;

        if (! auth()->check())
        {
            return;
        }

        $teamId = auth()->user()->currentTeam?->id;
        $conversation = $conversationContext->getAssistantConversationForUser(auth()->id(), $teamId);
        if ($conversation === null)
        {
            return;
        }

        $this->conversationId = $conversation->id;
        $this->messages = collect($conversationContext->getMessagesForDisplay(auth()->id(), 100, $teamId))
            ->map(fn (array $m) => [
                'role' => $m['role'],
                'content' => $m['content'],
                'routed_to' => $m['routed_to'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function sendMessage(
        AssistantChatService $assistant,
        AgentConversationContextService $conversationContext,
        ChatAssistantReplyService $replyService,
    ): void {
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

        $useHumanoAssistantPipeline = auth()->check() && ! $hasImage && ! $hasAudio;

        if ($useHumanoAssistantPipeline)
        {
            $this->runHumanoAssistantTurn($text, $userContent, $conversationContext, $replyService);
        } else
        {
            $this->runLegacyAssistantTurn($text, $userContent, $assistant);
        }

        $this->image = null;
        $this->audio = null;
        $this->loading = false;
        $this->dispatch('scroll-to-bottom');
    }

    public function clearChat(AgentConversationContextService $conversationContext): void
    {
        if (auth()->check())
        {
            $conversationContext->startFreshAssistantContext(
                auth()->id(),
                auth()->user()->currentTeam?->id,
            );
        }

        $this->messages = [];
        $this->conversationId = null;
    }

    public function render()
    {
        return view('livewire.assistant-chat');
    }

    protected function runHumanoAssistantTurn(
        string $text,
        string $userContent,
        AgentConversationContextService $conversationContext,
        ChatAssistantReplyService $replyService,
    ): void {
        $user = auth()->user();
        $teamId = $user->currentTeam?->id;
        $history = $conversationContext->getHistoryForPrompt(
            $user->id,
            AgentConversationContextService::DEFAULT_HISTORY_LIMIT,
            $teamId,
        );

        $forcedKeyRaw = $this->promptKey;
        $forcedFlowRoutingKey = \is_string($forcedKeyRaw) && trim($forcedKeyRaw) !== ''
            ? trim($forcedKeyRaw)
            : null;

        if ($teamId !== null && $text !== '')
        {
            if ($this->tryWebSlashCommands($text, $user, (int) $teamId, $conversationContext))
            {
                $conversation = $conversationContext->getAssistantConversationForUser($user->id, $teamId);
                $this->conversationId = $conversation?->id;

                return;
            }
        }

        $replyResponse = $replyService->getReply(
            $text,
            $history,
            $teamId,
            $teamId !== null,
            $user->id,
            null,
            $forcedFlowRoutingKey,
            null,
            false,
        );

        $toolResults = is_array($replyResponse['tool_results'] ?? null) ? $replyResponse['tool_results'] : [];
        $serverTaskApply = $teamId !== null
            ? app(AssistantInboundTaskStatusService::class)->tryApplyFromUserMessage(
                $user,
                (int) $teamId,
                $text,
                $history,
                $toolResults,
            )
            : null;

        if ($serverTaskApply !== null)
        {
            $toolResults[] = $serverTaskApply['tool_result'];
            $replyResponse['tool_results'] = $toolResults;
        }

        if (! ($replyResponse['success'] ?? false))
        {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => (string) ($replyResponse['message'] ?? __('Error al comunicar con el asistente.')),
                'routed_to' => null,
            ];
            Log::warning('AssistantChat Livewire: ChatAssistantReplyService failed', [
                'message' => $replyResponse['message'] ?? null,
            ]);

            return;
        }

        $assistantText = (string) ($replyResponse['text'] ?? '');
        if ($serverTaskApply !== null)
        {
            $assistantText = $this->formatServerAppliedTaskStatusReply($serverTaskApply['update']);
        }
        $assistantMessage = [
            'role' => 'assistant',
            'content' => $assistantText,
            'routed_to' => $replyResponse['routed_to'] ?? null,
        ];

        if ($this->respondWithAudio && $assistantText !== '' && config('ai.providers.eleven.key'))
        {
            $maxCharsForTts = 1000;
            $textForTts = strlen($assistantText) > $maxCharsForTts ? substr($assistantText, 0, $maxCharsForTts).'…' : $assistantText;
            try
            {
                $audioResponse = Audio::of($textForTts)->generate(provider: Lab::ElevenLabs);
                $assistantMessage['audio_base64'] = $audioResponse->audio;
                $assistantMessage['audio_mime'] = $audioResponse->mimeType() ?? 'audio/mpeg';
            } catch (\Throwable $e)
            {
                Log::warning('AssistantChat Livewire TTS failed', ['error' => $e->getMessage()]);
            }
        }

        $this->messages[] = $assistantMessage;

        $conversationContext->persistMessages(
            $user->id,
            $userContent,
            $assistantText,
            $replyResponse['routed_to'] ?? null,
            $replyResponse['usage'] ?? [],
            $replyResponse['meta'] ?? [],
            $replyResponse['tool_calls'] ?? [],
            $replyResponse['tool_results'] ?? [],
            $teamId,
            (bool) ($replyResponse['assistant_flow_routing_key_specified'] ?? false),
            $replyResponse['assistant_flow_routing_key'] ?? null,
        );

        $createdMessageId = AssistantCreatedMessageRedirect::extractCreatedMessageIdFromToolResults($toolResults);
        if ($createdMessageId !== null)
        {
            $redirectUrl = AssistantCreatedMessageRedirect::resolveMessageEditUrlForUser($user, $createdMessageId);
            if ($redirectUrl !== null)
            {
                $this->js('window.location.assign('.json_encode($redirectUrl).')');
            }
        }

        $taskStatusUpdate = $serverTaskApply !== null
            ? $serverTaskApply['update']
            : AssistantTaskStatusUpdate::extractFromToolResults($toolResults);
        if ($taskStatusUpdate !== null)
        {
            $this->dispatch(
                'assistant-task-status-updated',
                taskId: $taskStatusUpdate['task_id'],
                statusId: $taskStatusUpdate['status_id'],
                statusName: $taskStatusUpdate['status_name'],
            );
        }

        $conversation = $conversationContext->getAssistantConversationForUser($user->id, $teamId);
        $this->conversationId = $conversation?->id;
    }

    protected function tryWebSlashCommands(
        string $text,
        \App\Models\User $user,
        int $teamId,
        AgentConversationContextService $conversationContext,
    ): bool {
        $slashOutreach = app(AdminProactiveOutreachSlashDispatcher::class)->tryWebAssistantMessage(
            $text,
            $user,
            $teamId,
            $user,
            false,
        );
        if ($slashOutreach !== null)
        {
            $this->appendSlashCommandResult($slashOutreach, $text, $user->id, $teamId, $conversationContext);

            return true;
        }

        $slashInsight = app(PerformanceInsightSlashDispatcher::class)->tryWebAssistantMessage(
            $text,
            $user,
            $teamId,
            $user,
            false,
        );
        if ($slashInsight !== null)
        {
            $this->appendSlashCommandResult($slashInsight, $text, $user->id, $teamId, $conversationContext);

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function appendSlashCommandResult(
        array $result,
        string $userMessage,
        int $userId,
        int $teamId,
        AgentConversationContextService $conversationContext,
    ): void {
        $assistantText = ($result['success'] ?? false)
            ? (string) ($result['response'] ?? '')
            : (string) ($result['message'] ?? __('Error al procesar el comando.'));

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $assistantText,
            'routed_to' => null,
        ];

        if (! ($result['success'] ?? false))
        {
            $conversationContext->persistMessages(
                $userId,
                $userMessage,
                $assistantText,
                null,
                [],
                [],
                [],
                [],
                $teamId,
                false,
                null,
            );
        }
    }

    protected function runLegacyAssistantTurn(
        string $text,
        string $userContent,
        AssistantChatService $assistant,
    ): void {
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

        if (auth()->check())
        {
            app(AgentConversationContextService::class)->persistMessages(
                auth()->id(),
                $userContent,
                $result['response'],
                $result['routed_to'],
                $result['usage'] ?? [],
                [],
                $result['tool_calls'] ?? [],
                $result['tool_results'] ?? [],
                auth()->user()->currentTeam?->id,
                false,
                null,
            );
            $conversation = app(AgentConversationContextService::class)->getAssistantConversationForUser(
                auth()->id(),
                auth()->user()->currentTeam?->id,
            );
            $this->conversationId = $conversation?->id;
        }
    }

    /**
     * @param  array{task_id: int, status_id: int, status_name: string}  $update
     */
    protected function formatServerAppliedTaskStatusReply(array $update): string
    {
        $task = \App\Models\Task::withoutGlobalScopes()->find($update['task_id']);
        $status = TaskStatus::query()->find($update['status_id']);
        $title = $task?->title ?? __('Task');
        $label = $status?->translated_name ?? $update['status_name'];

        return '✅ Listo. La tarea "'.$title.'" quedó en '.$label.'.';
    }
}
