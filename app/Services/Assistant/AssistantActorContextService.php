<?php

namespace App\Services\Assistant;

use App\Models\User;
use App\Services\AssistantToolAuthorizationService;

class AssistantActorContextService
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public function __construct(
        private readonly AssistantToolAuthorizationService $toolAuthorization,
    ) {}

    public function resolve(?int $userId, int $teamId, ?string $channel = null): ?AssistantActorContext
    {
        if ($userId === null || $userId < 1)
        {
            return null;
        }

        $user = User::withoutGlobalScopes()->find($userId);
        if ($user === null)
        {
            return null;
        }

        return $this->resolveForUser($user, $teamId, $channel);
    }

    public function resolveForUser(User $user, int $teamId, ?string $channel = null): AssistantActorContext
    {
        $normalizedChannel = $this->normalizeChannel($channel);
        $limitedToolset = $this->toolAuthorization->usesCustomerAssistantPrompts($user, $teamId);

        return new AssistantActorContext(
            user: $user,
            teamId: $teamId,
            limitedToolset: $limitedToolset,
            whatsappInboundCustomerPrompts: $normalizedChannel === self::CHANNEL_WHATSAPP && $limitedToolset,
            interactiveGuideHintKey: $normalizedChannel === self::CHANNEL_WHATSAPP ? 'whatsapp' : 'web',
        );
    }

    private function normalizeChannel(?string $channel): string
    {
        $channel = $channel !== null ? strtolower(trim($channel)) : self::CHANNEL_WEB;

        return $channel === self::CHANNEL_WHATSAPP ? self::CHANNEL_WHATSAPP : self::CHANNEL_WEB;
    }
}
