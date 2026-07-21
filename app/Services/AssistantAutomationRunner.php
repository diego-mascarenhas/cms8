<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationStep;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssistantAutomationRunner
{
    public function __construct(
        protected AssistantChatService $assistantChat,
        protected AutomationFlowEngine $flowEngine,
    ) {}

    public function findById(int $id, ?int $teamId = null): ?Automation
    {
        $query = Automation::query()->withoutGlobalScope('team')->whereKey($id);
        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        return $query->first();
    }

    public function findBySlug(string $slug, int $teamId): ?Automation
    {
        return Automation::forTeam($teamId)
            ->where('slug', $slug)
            ->first();
    }

    public function findByPublicToken(string $token): ?Automation
    {
        $token = trim($token);
        if ($token === '')
        {
            return null;
        }

        return Automation::query()
            ->withoutGlobalScope('team')
            ->where('public_token', $token)
            ->first();
    }

    public function findDefaultForChannel(int $teamId, string $channel): ?Automation
    {
        return Automation::forTeam($teamId)
            ->active()
            ->orderBy('id')
            ->get()
            ->first(fn (Automation $automation) => $automation->allowsChannel($channel));
    }

    /**
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     */
    public function requireActive(Automation $automation, string $channel): Automation
    {
        if (! $automation->is_active)
        {
            throw new NotFoundHttpException(__('Automation is not active.'));
        }

        if (! $automation->allowsChannel($channel))
        {
            throw new AccessDeniedHttpException(__('This automation does not allow the :channel channel.', [
                'channel' => $channel,
            ]));
        }

        return $automation;
    }

    public function resolveForcedPromptKey(Automation $automation, string $channel): ?string
    {
        $this->requireActive($automation, $channel);

        return $automation->resolvedEntryPromptKey();
    }

    /**
     * Resolve funnel step + prompt key + appendix for tools-based channels (chat / WhatsApp).
     *
     * @return array{
     *     prompt_key: string|null,
     *     appendix: string|null,
     *     step: AutomationStep|null,
     *     completed: bool,
     *     automation: Automation|null,
     *     exit_automation?: Automation|null,
     *     session?: mixed
     * }
     */
    public function resolveFlowContext(
        int $teamId,
        string $channel,
        string $message,
        string $externalKey,
        ?string $automationSlug = null,
        ?int $automationId = null,
        ?string $existingForcedKey = null,
    ): array {
        $automation = null;
        if ($automationId !== null)
        {
            $automation = $this->findById($automationId, $teamId);
        } elseif ($automationSlug !== null && trim($automationSlug) !== '')
        {
            $automation = $this->findBySlug(trim($automationSlug), $teamId);
        } else
        {
            $automation = $this->findDefaultForChannel($teamId, $channel);
        }

        if ($automation === null || ! $automation->allowsChannel($channel) || ! $automation->is_active)
        {
            return [
                'prompt_key' => ($existingForcedKey !== null && trim($existingForcedKey) !== '') ? trim($existingForcedKey) : null,
                'appendix' => null,
                'step' => null,
                'completed' => false,
                'automation' => null,
            ];
        }

        if (! $automation->hasFlowGraph())
        {
            $key = ($existingForcedKey !== null && trim($existingForcedKey) !== '')
                ? trim($existingForcedKey)
                : $automation->resolvedEntryPromptKey();

            return [
                'prompt_key' => $key,
                'appendix' => null,
                'step' => null,
                'completed' => false,
                'automation' => $automation,
            ];
        }

        $session = $this->flowEngine->sessionFor($automation, $channel, $externalKey);
        $resolved = $this->flowEngine->resolveStepForMessage($session, $message);

        if (($resolved['exit_automation_id'] ?? null) !== null)
        {
            $exit = $this->findById((int) $resolved['exit_automation_id'], $teamId);
            $this->flowEngine->resetSession($session);

            if ($exit && $exit->is_active && $exit->allowsChannel($channel))
            {
                return [
                    'prompt_key' => $exit->resolvedEntryPromptKey(),
                    'appendix' => null,
                    'step' => null,
                    'completed' => false,
                    'automation' => $exit,
                    'exit_automation' => $exit,
                ];
            }

            return [
                'prompt_key' => null,
                'appendix' => null,
                'step' => null,
                'completed' => true,
                'automation' => $automation,
                'exit_automation' => $exit,
            ];
        }

        if ($resolved['completed'])
        {
            return [
                'prompt_key' => null,
                'appendix' => null,
                'step' => null,
                'completed' => true,
                'automation' => $automation,
            ];
        }

        $step = $resolved['step'];
        $promptKey = $step?->resolvedPromptKey()
            ?? (($existingForcedKey !== null && trim($existingForcedKey) !== '') ? trim($existingForcedKey) : $automation->resolvedEntryPromptKey());
        $appendix = $step ? $this->flowEngine->stepSystemAppendix($step) : null;

        return [
            'prompt_key' => $promptKey,
            'appendix' => $appendix !== '' ? $appendix : null,
            'step' => $step,
            'completed' => false,
            'automation' => $automation,
            'session' => $session,
        ];
    }

    public function markFlowAwaitingReply(mixed $session): void
    {
        if ($session)
        {
            $this->flowEngine->markAwaitingReply($session);
        }
    }

    /**
     * Run via AssistantChatService (API / embed / simple assistant path).
     *
     * @return array{response: string, routed_to: string|null, automation_id: int, automation_slug: string, step_key?: string|null, flow_completed?: bool, audio_base64?: string, audio_mime?: string}
     */
    public function run(
        Automation $automation,
        string $channel,
        string $message,
        ?UploadedFile $image = null,
        ?UploadedFile $audio = null,
        bool $respondWithVoice = false,
        ?string $sessionKey = null,
    ): array {
        $this->requireActive($automation, $channel);

        $promptKey = $automation->resolvedEntryPromptKey();
        $runMessage = $message;
        $stepKey = null;
        $session = null;

        if ($automation->hasFlowGraph())
        {
            $session = $this->flowEngine->sessionFor(
                $automation,
                $channel,
                $sessionKey ?: 'default',
            );
            $resolved = $this->flowEngine->resolveStepForMessage($session, $message);

            if (($resolved['exit_automation_id'] ?? null) !== null)
            {
                $exit = $this->findById((int) $resolved['exit_automation_id'], (int) $automation->team_id);
                $this->flowEngine->resetSession($session);

                if ($exit && $exit->is_active && $exit->allowsChannel($channel))
                {
                    $handed = $this->run($exit, $channel, $message, $image, $audio, $respondWithVoice, $sessionKey);

                    return array_merge($handed, [
                        'from_automation_id' => $automation->id,
                        'from_automation_slug' => $automation->slug,
                        'flow_exited' => true,
                    ]);
                }

                return [
                    'response' => __('Gracias. Hemos completado este flujo. Si necesitás algo más, escribime de nuevo.'),
                    'routed_to' => null,
                    'automation_id' => $automation->id,
                    'automation_slug' => $automation->slug,
                    'step_key' => null,
                    'flow_completed' => true,
                ];
            }

            if ($resolved['completed'])
            {
                $this->flowEngine->resetSession($session);

                return [
                    'response' => __('Gracias. Hemos completado este flujo. Si necesitás algo más, escribime de nuevo.'),
                    'routed_to' => null,
                    'automation_id' => $automation->id,
                    'automation_slug' => $automation->slug,
                    'step_key' => null,
                    'flow_completed' => true,
                ];
            }

            $step = $resolved['step'];
            if ($step instanceof AutomationStep)
            {
                $stepKey = $step->key;
                $promptKey = $step->resolvedPromptKey() ?? $promptKey;
                $appendix = $this->flowEngine->stepSystemAppendix($step);
                if ($appendix !== '')
                {
                    $runMessage = $appendix."\n\n---\n".__('Mensaje del usuario').":\n".$message;
                }
            }
        }

        $result = $this->assistantChat->run(
            $runMessage,
            (int) $automation->team_id,
            $image,
            $audio,
            $respondWithVoice,
            $promptKey,
        );

        if ($session !== null)
        {
            $this->flowEngine->markAwaitingReply($session);
        }

        return array_merge($result, [
            'automation_id' => $automation->id,
            'automation_slug' => $automation->slug,
            'step_key' => $stepKey,
            'flow_completed' => false,
        ]);
    }

    /**
     * @return array{response: string, routed_to: string|null, automation_id: int|null, automation_slug: string|null, step_key?: string|null, flow_completed?: bool, audio_base64?: string, audio_mime?: string}
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedHttpException
     */
    public function runForTeam(
        int $teamId,
        string $channel,
        string $message,
        ?string $automationSlug = null,
        ?int $automationId = null,
        ?string $promptKeyOverride = null,
        ?string $sessionKey = null,
    ): array {
        $automation = null;
        $requestedExplicitly = false;

        if ($automationId !== null)
        {
            $requestedExplicitly = true;
            $automation = $this->findById($automationId, $teamId);
        } elseif ($automationSlug !== null && trim($automationSlug) !== '')
        {
            $requestedExplicitly = true;
            $automation = $this->findBySlug(trim($automationSlug), $teamId);
        }

        if ($requestedExplicitly && $automation === null)
        {
            throw new NotFoundHttpException(__('Automation not found.'));
        }

        if ($automation !== null)
        {
            return $this->run($automation, $channel, $message, null, null, false, $sessionKey);
        }

        $result = $this->assistantChat->run(
            $message,
            $teamId,
            null,
            null,
            false,
            ($promptKeyOverride !== null && trim($promptKeyOverride) !== '') ? trim($promptKeyOverride) : null,
        );

        return array_merge($result, [
            'automation_id' => null,
            'automation_slug' => null,
        ]);
    }

    public function resolveChannelPromptKey(
        int $teamId,
        string $channel,
        ?string $automationSlug = null,
        ?int $automationId = null,
        ?string $existingForcedKey = null,
    ): ?string {
        if ($existingForcedKey !== null && trim($existingForcedKey) !== '')
        {
            return trim($existingForcedKey);
        }

        $automation = null;
        if ($automationId !== null)
        {
            $automation = $this->findById($automationId, $teamId);
        } elseif ($automationSlug !== null && trim($automationSlug) !== '')
        {
            $automation = $this->findBySlug(trim($automationSlug), $teamId);
        } else
        {
            $automation = $this->findDefaultForChannel($teamId, $channel);
        }

        if ($automation === null)
        {
            return null;
        }

        try
        {
            return $this->resolveForcedPromptKey($automation, $channel);
        } catch (AccessDeniedHttpException|NotFoundHttpException)
        {
            return null;
        }
    }
}
