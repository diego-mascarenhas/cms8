<?php

namespace App\Services\Assistant;

use App\Models\User;

/**
 * Single source of truth for how the assistant should behave for a user on a team.
 * Used by web chat, WhatsApp auto-reply, mail, and CLI — not duplicated per channel.
 */
final class AssistantActorContext
{
    public function __construct(
        public readonly User $user,
        public readonly int $teamId,
        public readonly bool $limitedToolset,
        public readonly bool $whatsappInboundCustomerPrompts,
        public readonly string $interactiveGuideHintKey,
    ) {}

    public function usesWebInteractiveGuideHint(): bool
    {
        return $this->interactiveGuideHintKey === 'web';
    }
}
