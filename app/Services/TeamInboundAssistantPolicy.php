<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

/**
 * Team-level inbound WhatsApp assistant rules. General team settings prevail over per-contact preferences.
 */
class TeamInboundAssistantPolicy
{
    public function allowsWhatsAppAutoReply(Team $team, ?User $inboundSender = null, ?int $membershipTeamId = null, ?string $inboundSenderPhone = null): bool
    {
        if ($this->isBlacklistedWhatsAppPhone($team, $inboundSenderPhone))
        {
            return false;
        }

        if (filter_var($team->getSetting('assistant_auto_respond', '1'), FILTER_VALIDATE_BOOLEAN))
        {
            return true;
        }

        if (! filter_var($team->getSetting('assistant_auto_respond_admins_when_off', '0'), FILTER_VALIDATE_BOOLEAN))
        {
            return false;
        }

        return $this->inboundSenderIsTeamAdministrator($inboundSender, $membershipTeamId ?? (int) $team->id);
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
