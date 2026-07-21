<?php

namespace App\Services;

use App\Models\Automation;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssistantAutomationRunner
{
    public function __construct(
        protected AssistantChatService $assistantChat,
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

    /**
     * First active automation for a team that allows the given channel.
     */
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

    /**
     * Resolve entry prompt key for tools-based channels (WhatsApp, chat UI).
     * Returns null when the automation uses the general router.
     */
    public function resolveForcedPromptKey(Automation $automation, string $channel): ?string
    {
        $this->requireActive($automation, $channel);

        return $automation->resolvedEntryPromptKey();
    }

    /**
     * Run via AssistantChatService (API / embed / simple assistant path).
     *
     * @return array{response: string, routed_to: string|null, automation_id: int, automation_slug: string, audio_base64?: string, audio_mime?: string}
     */
    public function run(
        Automation $automation,
        string $channel,
        string $message,
        ?UploadedFile $image = null,
        ?UploadedFile $audio = null,
        bool $respondWithVoice = false,
    ): array {
        $this->requireActive($automation, $channel);

        $result = $this->assistantChat->run(
            $message,
            (int) $automation->team_id,
            $image,
            $audio,
            $respondWithVoice,
            $automation->resolvedEntryPromptKey(),
        );

        return array_merge($result, [
            'automation_id' => $automation->id,
            'automation_slug' => $automation->slug,
        ]);
    }

    /**
     * Resolve automation from optional id/slug within a team, then run.
     *
     * @return array{response: string, routed_to: string|null, automation_id: int|null, automation_slug: string|null, audio_base64?: string, audio_mime?: string}
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
            return $this->run($automation, $channel, $message);
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

    /**
     * Resolve forced prompt key for chat/WhatsApp when an automation is requested or a default exists.
     */
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
