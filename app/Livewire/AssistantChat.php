<?php

namespace App\Livewire;

use App\Models\Automation;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Services\AdminProactiveOutreachSlashDispatcher;
use App\Services\AgentConversationContextService;
use App\Services\Assistant\AssistantInboundContactCreationService;
use App\Services\Assistant\AssistantInboundTaskStatusService;
use App\Services\AssistantAutomationRunner;
use App\Services\AssistantChatService;
use App\Services\AutomationFunnelCompletionNotifier;
use App\Services\ChatAssistantReplyService;
use App\Services\PerformanceInsightSlashDispatcher;
use App\Support\AssistantCreatedMessageRedirect;
use App\Support\AssistantTaskStatusUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    /**
     * Active funnel/action automation for the Humano assistant channel.
     * Set by typing the slug, the Probar embudo button, or startAutomation event.
     */
    public ?int $automationId = null;

    protected $listeners = [
        'assistant-reset-context' => 'clearChat',
        'finance-assistant-prefill' => 'prefillInput',
        'assistant-start-automation' => 'startAutomation',
    ];

    public function mount(
        AgentConversationContextService $conversationContext,
        ?string $promptKey = null,
        bool $hideHeader = false,
        ?int $automationId = null,
    ): void {
        $this->promptKey = $promptKey;
        $this->hideHeader = $hideHeader;
        $this->automationId = $automationId;

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

    /**
     * @param  string|array{message?: string}  $message  Livewire 3 passes named params from dispatch({ message: '...' }).
     */
    public function prefillInput(string|array $message = ''): void
    {
        if (is_array($message))
        {
            $message = (string) ($message['message'] ?? '');
        }

        $this->input = trim((string) $message);
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
        $this->automationId = null;
    }

    /**
     * Start (or switch to) a team automation funnel from the FAB / funnel show page.
     *
     * @param  int|string|null  $automationId
     */
    public function startAutomation(mixed $automationId = null, mixed $slug = null): void
    {
        if (! auth()->check())
        {
            return;
        }

        if (is_array($automationId))
        {
            $payload = $automationId;
            $id = (int) ($payload['automationId'] ?? $payload['automation_id'] ?? 0);
            $slugValue = trim((string) ($payload['slug'] ?? ''));
        } else
        {
            $id = (int) ($automationId ?? 0);
            $slugValue = is_string($slug) ? trim($slug) : '';
        }

        $teamId = auth()->user()->currentTeam?->id;
        if ($teamId === null)
        {
            return;
        }

        $runner = app(AssistantAutomationRunner::class);
        $automation = $id > 0
            ? $runner->findById($id, (int) $teamId)
            : ($slugValue !== '' ? $runner->findBySlug($slugValue, (int) $teamId) : null);

        if ($automation === null || ! $automation->is_active || ! $automation->allowsChannel(Automation::CHANNEL_HUMANO))
        {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => __('No se pudo iniciar ese embudo en el asistente. Revisá que esté activo y con el canal Humano habilitado.'),
                'routed_to' => null,
            ];

            return;
        }

        $conversationContext = app(AgentConversationContextService::class);
        $this->clearChat($conversationContext);
        $this->automationId = $automation->id;
        $this->input = 'empezar';
        $this->sendMessage(
            app(AssistantChatService::class),
            $conversationContext,
            app(ChatAssistantReplyService::class),
        );
    }

    protected function bindAutomationFromUserMessage(string $text, int $teamId, AssistantAutomationRunner $runner): void
    {
        $normalized = Str::lower(trim($text));
        if ($normalized === '')
        {
            return;
        }

        if (preg_match('/^\/(?:embudo|funnel)\s+([a-z0-9\-_]+)$/i', $normalized, $matches) === 1)
        {
            $automation = $runner->findBySlug($matches[1], $teamId);
            if ($automation && $automation->is_active && $automation->allowsChannel(Automation::CHANNEL_HUMANO))
            {
                $this->automationId = $automation->id;
            }

            return;
        }

        if ($this->automationId !== null)
        {
            return;
        }

        if (! preg_match('/^[a-z0-9][a-z0-9\-_]*$/', $normalized))
        {
            return;
        }

        $automation = $runner->findBySlug($normalized, $teamId);
        if ($automation && $automation->is_active && $automation->allowsChannel(Automation::CHANNEL_HUMANO))
        {
            $this->automationId = $automation->id;
        }
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

        $flowAppendix = null;
        $flowSession = null;
        $flowStep = null;
        $flowAutomation = null;
        $runner = app(AssistantAutomationRunner::class);

        if ($teamId !== null && $text !== '')
        {
            if ($this->tryWebSlashCommands($text, $user, (int) $teamId, $conversationContext))
            {
                $conversation = $conversationContext->getAssistantConversationForUser($user->id, $teamId);
                $this->conversationId = $conversation?->id;

                return;
            }

            $this->bindAutomationFromUserMessage($text, (int) $teamId, $runner);
        }

        if ($teamId !== null && $this->automationId !== null)
        {
            $flowContext = $runner->resolveFlowContext(
                (int) $teamId,
                Automation::CHANNEL_HUMANO,
                $text,
                'user:'.(string) $user->id,
                null,
                $this->automationId,
                $forcedFlowRoutingKey,
            );

            if (! empty($flowContext['completed']))
            {
                $completedAutomation = $flowContext['automation'] ?? null;
                $completionMessage = trim((string) ($flowContext['completion_message'] ?? ''));
                if ($completionMessage === '')
                {
                    if ($completedAutomation instanceof Automation)
                    {
                        app(AutomationFunnelCompletionNotifier::class)->notifyIfEligible(
                            $completedAutomation,
                            $flowContext['session'] ?? null,
                            $flowContext['step'] ?? null,
                            true,
                        );
                    }

                    $completionMessage = __('Gracias. Hemos completado este flujo. Si necesitás algo más, escribime de nuevo.');
                }

                $this->automationId = null;
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => $completionMessage,
                    'routed_to' => null,
                ];
                $conversationContext->persistMessages(
                    $user->id,
                    $userContent,
                    $completionMessage,
                    null,
                    [],
                    [],
                    [],
                    [],
                    $teamId,
                    false,
                    null,
                );
                $conversation = $conversationContext->getAssistantConversationForUser($user->id, $teamId);
                $this->conversationId = $conversation?->id;

                return;
            }

            if (($flowContext['prompt_key'] ?? null) !== null && trim((string) $flowContext['prompt_key']) !== '')
            {
                $forcedFlowRoutingKey = trim((string) $flowContext['prompt_key']);
            }
            $flowAppendix = $flowContext['appendix'] ?? null;
            $flowSession = $flowContext['session'] ?? null;
            $flowStep = $flowContext['step'] ?? null;
            $flowAutomation = $flowContext['automation'] ?? null;

            if ($flowAutomation instanceof Automation)
            {
                $this->automationId = (int) $flowAutomation->id;
            }
        } elseif ($forcedFlowRoutingKey === null && $teamId !== null)
        {
            $forcedFlowRoutingKey = $runner->resolveChannelPromptKey(
                (int) $teamId,
                Automation::CHANNEL_HUMANO,
            );
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
            null,
            false,
            is_string($flowAppendix) ? $flowAppendix : null,
        );

        if ($flowSession !== null)
        {
            $runner->markFlowAwaitingReply($flowSession);
        }

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

        $serverContactApply = $teamId !== null
            ? app(AssistantInboundContactCreationService::class)->tryApplyFromUserMessage(
                $user,
                (int) $teamId,
                $text,
                $toolResults,
            )
            : null;

        if ($serverContactApply !== null)
        {
            $toolResults[] = $serverContactApply['tool_result'];
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
        } elseif ($serverContactApply !== null)
        {
            $assistantText = $serverContactApply['whatsapp_reply'];
        } else
        {
            $assistantText = app(AssistantInboundContactCreationService::class)->applyContactOnlyReplyIfApplicable(
                $text,
                $toolResults,
                $assistantText,
            );
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

        if ($flowAutomation instanceof Automation && $flowSession !== null)
        {
            app(AutomationFunnelCompletionNotifier::class)->notifyIfEligible(
                $flowAutomation,
                $flowSession->fresh(),
                $flowStep instanceof \App\Models\AutomationStep ? $flowStep : null,
                false,
            );
        }

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
