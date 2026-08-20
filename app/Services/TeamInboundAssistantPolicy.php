<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Services\Billing\AssistantSubscriptionService;

/**
 * Inbound WhatsApp assistant rules.
 *
 * Precedence: paid plan → blacklist → per-contact opt-out → team global auto-respond (master) → silent team default → admins-when-off exception.
 * Inbox "Sin asistente" blocks WhatsApp auto-reply even for team admins.
 * A silent team default (no responder) still allows a contact with a pinned prompt.
 */
class TeamInboundAssistantPolicy
{
    /**
     * Billing answers per team, cached for the life of this instance. The inbox asks once per
     * conversation, and each miss costs a subscription lookup.
     *
     * @var array<int, bool>
     */
    private array $planInEffect = [];

    public function assistantPlanIsInEffect(Team $team): bool
    {
        if (! config('humano_pricing.require_paid_plan_for_ai', true))
        {
            return true;
        }

        return $this->planInEffect[(int) $team->id] ??= app(AssistantSubscriptionService::class)->teamHasPaidAccessForAi($team);
    }

    public function lockedReason(Team $team): ?string
    {
        return $this->assistantPlanIsInEffect($team) ? null : 'plan';
    }

    /**
     * The plan gates whether the AI actually answers, not whether the team can configure it: the
     * prompt picker stays usable so the line is set up before (or after) the plan is paid.
     *
     * @return array{assistant_inbound_enabled: bool, assistant_toggle_available: bool, assistant_plan_active: bool, assistant_locked_reason: string|null}
     */
    public function presentWhatsAppAssistantState(Team $team, bool $inboundEnabled, bool $toggleAvailable): array
    {
        $planActive = $this->assistantPlanIsInEffect($team);

        return [
            'assistant_inbound_enabled' => $inboundEnabled,
            'assistant_toggle_available' => $toggleAvailable,
            'assistant_plan_active' => $planActive,
            'assistant_locked_reason' => $planActive ? null : 'plan',
        ];
    }

    public function allowsWhatsAppAutoReply(Team $team, ?User $inboundSender = null, ?int $membershipTeamId = null, ?string $inboundSenderPhone = null): bool
    {
        if (! $this->assistantPlanIsInEffect($team))
        {
            return false;
        }

        return $this->autoReplyPreferencesAllow($team, $inboundSender, $membershipTeamId, $inboundSenderPhone);
    }

    /**
     * Everything the team chose (blacklist, master switch, per-contact opt-out) without the billing
     * gate, so the UI can show the configured state while the plan is dormant.
     */
    public function autoReplyPreferencesAllow(Team $team, ?User $inboundSender = null, ?int $membershipTeamId = null, ?string $inboundSenderPhone = null): bool
    {
        if ($this->isBlacklistedWhatsAppPhone($team, $inboundSenderPhone))
        {
            return false;
        }

        // Inbox "Sin asistente" always wins. An admin answers that chat by hand.
        if ($inboundSenderPhone !== null && ! $this->contactAllowsAutoReply((int) $team->id, $inboundSenderPhone))
        {
            return false;
        }

        $teamAutoRespond = filter_var($team->getSetting('assistant_auto_respond', '1'), FILTER_VALIDATE_BOOLEAN);

        if (! $teamAutoRespond)
        {
            if (! filter_var($team->getSetting('assistant_auto_respond_admins_when_off', '0'), FILTER_VALIDATE_BOOLEAN))
            {
                return false;
            }

            return $this->inboundSenderIsTeamAdministrator($inboundSender, $membershipTeamId ?? (int) $team->id);
        }

        if (app(TeamSiteAssistantPromptService::class)->isSilentDefault($team)
                && ! $this->contactHasPinnedInboundPrompt((int) $team->id, $inboundSenderPhone))
        {
            return false;
        }

        return true;
    }

    /**
     * A connected WhatsApp on another team is usually a person, not a bot.
     * Skip only when that other team has a configured inbound bot that would answer us.
     * Internal sales lines and chats with a pinned prompt still answer.
     */
    public function shouldSkipLinkedPeerAutoReply(Team $team, ?string $inboundSenderPhone, ?int $peerTeamId = null): bool
    {
        if (in_array((int) $team->id, config('humano_pricing.plan_access_team_ids', []), true))
        {
            return false;
        }

        if ($this->contactHasPinnedInboundPrompt((int) $team->id, $inboundSenderPhone))
        {
            return false;
        }

        if ($peerTeamId === null || $peerTeamId < 1)
        {
            return false;
        }

        $peer = Team::withoutGlobalScopes()->find($peerTeamId);
        if ($peer === null || ! $this->teamHasConfiguredInboundBot($peer))
        {
            return false;
        }

        $ourNumber = preg_replace('/\D/', '', (string) $team->getWhatsAppFrom());

        return $this->allowsWhatsAppAutoReply(
            $peer,
            null,
            (int) $peer->id,
            $ourNumber !== '' ? $ourNumber : null,
        );
    }

    /**
     * True when the team turned inbound auto-reply on and chose a default prompt.
     * Empty / Sin asistente means a person answers that line.
     */
    public function teamHasConfiguredInboundBot(Team $team): bool
    {
        if (! filter_var($team->getSetting('assistant_auto_respond', '1'), FILTER_VALIDATE_BOOLEAN))
        {
            return false;
        }

        $key = app(TeamSiteAssistantPromptService::class)->selectedRoutingKey($team);

        return $key !== null && $key !== TeamSiteAssistantPromptService::OFF_KEY;
    }

    /**
     * Returns false only when a CRM contact exists for the phone AND has explicitly disabled assistant auto-reply.
     * If no contact is found the default is true (allow).
     */
    public function contactAllowsAutoReply(int $teamId, string $phone): bool
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '')
        {
            return true;
        }

        $contacts = app(UserResolverService::class)->findContactsInTeamByPhone($teamId, $digits);

        if ($contacts->isEmpty())
        {
            return true;
        }

        return $contacts->every(fn ($contact) => $contact->allowsInboundChatAssistant());
    }

    /**
     * True only when this phone has a CRM contact with a pinned inbound prompt.
     * Unknown numbers and Automático contacts follow the team default.
     */
    public function contactHasPinnedInboundPrompt(int $teamId, ?string $phone): bool
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '')
        {
            return false;
        }

        $contact = app(UserResolverService::class)->findContactInTeamByPhone($teamId, $digits);

        return $contact !== null && $contact->inboundChatAssistantPromptKey() !== null;
    }

    public function isBlacklistedWhatsAppPhone(Team $team, ?string $phone): bool
    {
        $normalizedSenderPhone = preg_replace('/\D/', '', (string) $phone);
        if ($normalizedSenderPhone === '')
        {
            return false;
        }

        $rawBlacklist = $team->getSetting('assistant_whatsapp_blacklist_numbers', '');
        if (! is_string($rawBlacklist) || trim($rawBlacklist) === '')
        {
            return false;
        }

        $items = preg_split('/[\r\n,;]+/', $rawBlacklist) ?: [];

        foreach ($items as $item)
        {
            $normalizedItem = preg_replace('/\D/', '', (string) $item);
            if ($normalizedItem === '')
            {
                continue;
            }

            if ($normalizedItem === $normalizedSenderPhone)
            {
                return true;
            }

            if (strlen($normalizedItem) > 9 && strlen($normalizedSenderPhone) > 9)
            {
                if (substr($normalizedItem, -9) === substr($normalizedSenderPhone, -9))
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Team WhatsApp line admins/editors (same idea as sheet-import checks), plus global admin/root.
     */
    public function inboundSenderIsTeamAdministrator(?User $user, int $teamId): bool
    {
        if ($user === null)
        {
            return false;
        }

        if ($user->hasRole('root'))
        {
            return true;
        }

        $membership = $user->teams()->where('teams.id', $teamId)->first();
        if ($membership === null)
        {
            return false;
        }

        $pivotRole = strtolower((string) ($membership->pivot->role ?? ''));

        return in_array($pivotRole, ['admin', 'editor'], true)
            || $user->hasRole('admin');
    }
}
