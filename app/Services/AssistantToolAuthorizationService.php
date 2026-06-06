<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Assistant tool access mirrors Laravel policies for the acting user on the team.
 * Channel (web, WhatsApp) does not change authorization — only role/permissions do.
 */
class AssistantToolAuthorizationService
{
    /**
     * Tools that are always available to any resolved team member (profile, flow routing, WhatsApp reply).
     *
     * @var list<string>
     */
    private const ALWAYS_ALLOWED_TOOLS = [
        'commit_assistant_flow',
        'get_my_profile',
        'send_whatsapp_message',
    ];

    /**
     * Shopping helpers when the team sells via WhatsApp (no ProductPolicy client view required).
     *
     * @var list<string>
     */
    private const WHATSAPP_CART_TOOLS = [
        'list_product_catalog',
        'search_products',
        'add_to_whatsapp_cart',
    ];

    /**
     * @var array<string, array{0: string, 1: class-string}>
     */
    private const TOOL_POLICY = [
        'list_contact_categories' => ['viewAny', Contact::class],
        'list_contact_statuses' => ['viewAny', Contact::class],
        'search_contacts' => ['viewAny', Contact::class],
        'create_contact' => ['create', Contact::class],
        'get_contact_categories' => ['viewAny', Contact::class],
        'assign_contact_to_category' => ['update', Contact::class],
        'update_contact' => ['update', Contact::class],
        'check_calendar_availability' => ['viewAny', CalendarEvent::class],
        'create_calendar_event' => ['create', CalendarEvent::class],
        'list_calendar_events' => ['viewAny', CalendarEvent::class],
        'update_calendar_event' => ['update', CalendarEvent::class],
        'create_ticket' => ['create', Ticket::class],
        'list_opportunity_stages' => ['viewAny', Opportunity::class],
        'create_opportunity' => ['create', Opportunity::class],
        'list_templates' => ['viewAny', Prompt::class],
        'create_template' => ['create', Prompt::class],
        'update_template_status' => ['update', Prompt::class],
        'update_template' => ['update', Prompt::class],
        'list_messages' => ['viewAny', Prompt::class],
        'create_message' => ['create', Prompt::class],
        'update_message_status' => ['update', Prompt::class],
        'update_message' => ['update', Prompt::class],
        'list_product_catalog' => ['viewAny', Product::class],
        'search_products' => ['viewAny', Product::class],
        'get_financial_projection' => ['viewAny', Invoice::class],
        'get_financial_category_breakdown' => ['viewAny', Invoice::class],
        'run_financial_growth_scenario' => ['viewAny', Invoice::class],
    ];

    /**
     * CRM bulk / staff tools without a dedicated policy mapping yet.
     *
     * @var list<string>
     */
    private const STAFF_CRM_TOOLS = [
        'get_account_report',
        'list_team_users',
        'create_task',
        'search_tasks',
        'list_task_statuses',
        'update_task_status',
        'add_ticket_response',
    ];

    public function prepareTeamContext(User $user, int $teamId): void
    {
        $team = Team::withoutGlobalScopes()->find($teamId);
        if ($team !== null)
        {
            $user->setRelation('currentTeam', $team);
        }
    }

    /**
     * Broad CRM staff profile (contacts + campaigns). Used only for prompt tone and legacy checks.
     */
    public function hasFullAssistantToolAccess(User $user, int $teamId): bool
    {
        if ($user->hasAnyRole(['admin', 'root']))
        {
            return true;
        }

        $membership = $user->teams()->where('team_id', $teamId)->first();
        $pivotRole = $membership?->pivot?->role;
        if (is_string($pivotRole) && in_array($pivotRole, ['admin', 'editor', 'collaborator'], true))
        {
            return true;
        }

        $this->prepareTeamContext($user, $teamId);

        return Gate::forUser($user)->allows('viewAny', Contact::class)
            && Gate::forUser($user)->allows('create', Contact::class);
    }

    /**
     * @deprecated Use {@see hasFullAssistantToolAccess()} or {@see usesCustomerAssistantPrompts()}.
     */
    public function isTeamStaffMember(User $user, int $teamId): bool
    {
        return $this->hasFullAssistantToolAccess($user, $teamId);
    }

    /**
     * Whether to use the narrow "customer / WhatsApp buyer" system prompts (not tool access).
     */
    public function usesCustomerAssistantPrompts(User $user, int $teamId): bool
    {
        if ($this->hasFullAssistantToolAccess($user, $teamId))
        {
            return false;
        }

        $this->prepareTeamContext($user, $teamId);

        if (Gate::forUser($user)->allows('create', CalendarEvent::class))
        {
            return false;
        }

        if (Gate::forUser($user)->allows('create', Ticket::class))
        {
            return false;
        }

        return true;
    }

    /**
     * @deprecated Use {@see usesCustomerAssistantPrompts()}.
     */
    public function usesLimitedAssistantToolset(User $user, int $teamId): bool
    {
        return $this->usesCustomerAssistantPrompts($user, $teamId);
    }

    /**
     * @deprecated Use {@see usesCustomerAssistantPrompts()}.
     */
    public function isRestrictedTeamMember(User $user, int $teamId): bool
    {
        return $this->usesCustomerAssistantPrompts($user, $teamId);
    }

    /**
     * If the tool is not allowed, returns a short Spanish error for the model; otherwise null.
     */
    public function denyReasonForTool(string $toolName, User $user, int $teamId): ?string
    {
        $this->prepareTeamContext($user, $teamId);

        if (in_array($toolName, self::ALWAYS_ALLOWED_TOOLS, true))
        {
            return null;
        }

        if (in_array($toolName, self::WHATSAPP_CART_TOOLS, true))
        {
            return null;
        }

        $policy = self::TOOL_POLICY[$toolName] ?? null;
        if ($policy !== null)
        {
            [$ability, $modelClass] = $policy;

            if ($this->allowsPolicyAbility($user, $ability, $modelClass))
            {
                return null;
            }

            return $this->denialMessage();
        }

        if (in_array($toolName, self::STAFF_CRM_TOOLS, true))
        {
            return $this->hasFullAssistantToolAccess($user, $teamId)
                ? null
                : $this->denialMessage();
        }

        return null;
    }

    /**
     * @param  class-string  $modelClass
     */
    private function allowsPolicyAbility(User $user, string $ability, string $modelClass): bool
    {
        if ($ability === 'update')
        {
            if ($modelClass === CalendarEvent::class)
            {
                return Gate::forUser($user)->allows('create', CalendarEvent::class);
            }

            if ($modelClass === Contact::class)
            {
                $teamId = $user->currentTeam?->id;
                if ($teamId === null)
                {
                    return false;
                }

                $probe = new Contact(['team_id' => $teamId]);

                return Gate::forUser($user)->allows('update', $probe);
            }

            if ($modelClass === Prompt::class)
            {
                return Gate::forUser($user)->allows('create', Prompt::class);
            }
        }

        return Gate::forUser($user)->allows($ability, $modelClass);
    }

    private function denialMessage(): string
    {
        return 'No disponible para tu rol en este equipo. Pedí ayuda al equipo o usá las opciones que tu cuenta tiene habilitadas.';
    }
}
