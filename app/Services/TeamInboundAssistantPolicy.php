<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Services\Billing\AssistantSubscriptionService;

/**
 * Inbound WhatsApp assistant rules.
 *
 * Precedence: paid plan → blacklist → team global auto-respond (master) → per-contact opt-out → admins-when-off exception.
 * The header contact toggle cannot override a disabled team global setting.
 */
class TeamInboundAssistantPolicy
{
    public function assistantPlanIsInEffect(Team $team): bool
    {
        if (! config('humano_pricing.require_paid_plan_for_ai', true))
        {
            return true;
        }

        return app(AssistantSubscriptionService::class)->teamHasPaidAccessForAi($team);
    }

    public function lockedReason(Team $team): ?string
    {
        return $this->assistantPlanIsInEffect($team) ? null : 'plan';
    }

    /**
     * @return array{assistant_inbound_enabled: bool, assistant_toggle_available: bool, assistant_plan_active: bool, assistant_locked_reason: string|null}
     */
    public function presentWhatsAppAssistantState(Team $team, bool $inboundEnabled, bool $toggleAvailable): array
    {
        $planActive = $this->assistantPlanIsInEffect($team);

        return [
            'assistant_inbound_enabled' => $planActive && $inboundEnabled,
            'assistant_toggle_available' => $planActive && $toggleAvailable,
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

        if ($this->isBlacklistedWhatsAppPhone($team, $inboundSenderPhone))
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

        // Team global is on: contact may still opt out via the header toggle / contact form.
        if ($inboundSenderPhone !== null && ! $this->contactAllowsAutoReply((int) $team->id, $inboundSenderPhone))
        {
            return false;
        }

        return true;
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

        $contact = app(UserResolverService::class)->findContactInTeamByPhone($teamId, $digits);

        if ($contact === null)
        {
            return true;
        }

        return $contact->allowsInboundChatAssistant();
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

        if ($user->hasAnyRole(['admin', 'root']))
        {
            return true;
        }

        $membership = $user->teams()->where('teams.id', $teamId)->first();
        $pivotRole = strtolower((string) ($membership?->pivot?->role ?? ''));

        return in_array($pivotRole, ['admin', 'editor'], true);
    }
}
