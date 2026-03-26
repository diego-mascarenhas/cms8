<?php

namespace App\Services;

use App\Models\User;

/**
 * Restricts assistant tools (WhatsApp / Humano Assistant) by the user's Jetstream role on the team.
 * Spatie roles are used elsewhere; for assistant tools the team pivot role is the source of truth.
 */
class AssistantToolAuthorizationService
{
    /**
     * Roles that only get customer-safe tools (no CRM bulk, campaigns, team calendar, etc.).
     *
     * @var list<string>
     */
    public const RESTRICTED_TEAM_ROLES = ['client', 'guest', 'user'];

    /**
     * Tools allowed for RESTRICTED_TEAM_ROLES when acting in that team context.
     *
     * @var list<string>
     */
    public const CLIENT_SAFE_TOOLS = [
        'commit_assistant_flow',
        'get_my_profile',
        'list_product_catalog',
        'search_products',
        'add_to_whatsapp_cart',
        'create_ticket',
        'send_whatsapp_message',
    ];

    /**
     * Effective Jetstream role for this user on the team (pivot), or null if not a member.
     */
    public function jetstreamTeamRole(User $user, int $teamId): ?string
    {
        $membership = $user->teams()->where('team_id', $teamId)->first();
        $role = $membership?->pivot?->role;

        return is_string($role) && $role !== '' ? $role : null;
    }

    public function isRestrictedTeamMember(User $user, int $teamId): bool
    {
        $role = $this->jetstreamTeamRole($user, $teamId);

        return $role !== null && in_array($role, self::RESTRICTED_TEAM_ROLES, true);
    }

    /**
     * If the tool is not allowed, returns a short Spanish error for the model; otherwise null.
     */
    public function denyReasonForTool(string $toolName, User $user, int $teamId): ?string
    {
        if (! $this->isRestrictedTeamMember($user, $teamId))
        {
            return null;
        }

        if (in_array($toolName, self::CLIENT_SAFE_TOOLS, true))
        {
            return null;
        }

        return 'No disponible para tu rol en este equipo (cliente). Pedí ayuda al equipo o usá las opciones de compra o soporte que te ofrecen.';
    }
}
