<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Helpers\WhatsAppCartSessionKey;
use App\Helpers\WhatsAppOutboundText;
use App\Jobs\GenerateTemplateHtmlJob;
use App\Jobs\PushCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\Template;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use App\Services\Contacts\TeamContactMatcher;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Support\AssistantCreatedMessageRedirect;
use App\Support\AssistantTaskStatusUpdate;
use App\Support\CalendarEventDateTimeParser;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Defines and executes tools available to the chat assistant (create contact, task, send WhatsApp, etc.).
 * All operations are scoped to the authenticated user's current team (or to the request context when e.g. writing via WhatsApp).
 */
class AssistantToolsService
{
    public const MAX_TOOL_RESULT_LENGTH = 4000;

    protected ?int $contextUserId = null;

    protected ?int $contextTeamId = null;

    /** Digits-only WhatsApp number of the customer (inbound thread), for cart session. */
    protected ?string $contextCustomerPhone = null;

    /**
     * When true, only the first {@see sendWhatsAppMessage()} in this request actually sends; further calls are no-ops
     * (stops duplicate customer messages when the model calls the tool twice, e.g. opening + meta confirmation).
     */
    protected bool $whatsappToolSingleCustomerSendPerTurn = false;

    protected int $whatsappToolSendCount = 0;

    /**
     * Contact IDs touched via create_contact in this request (for auto-linking calendar guests).
     *
     * @var list<int>
     */
    protected array $recentContactIdsInRequest = [];

    /**
     * Tool names executed during the current request (Laravel AI may not populate tool_results).
     *
     * @var list<string>
     */
    protected array $executedToolsInRequest = [];

    /**
     * Test-only or explicit override; production uses {@see resolveWhatsAppGatewayForToolSend()}.
     */
    protected ?WhatsAppGateway $whatsAppGatewayOverride = null;

    public function __construct(
        protected AssistantToolAuthorizationService $toolAuthorization,
    ) {}

    /**
     * @internal Testing: force a specific gateway for send_whatsapp_message (e.g. mock).
     */
    public function setWhatsAppGatewayOverride(?WhatsAppGateway $gateway): void
    {
        $this->whatsAppGatewayOverride = $gateway;
    }

    /**
     * Reset tool execution context (avoid leaking phone/team between requests; service may be singleton).
     */
    public function clearRequestContext(): void
    {
        $this->contextUserId = null;
        $this->contextTeamId = null;
        $this->contextCustomerPhone = null;
        $this->whatsappToolSingleCustomerSendPerTurn = false;
        $this->whatsappToolSendCount = 0;
        $this->whatsAppGatewayOverride = null;
        $this->recentContactIdsInRequest = [];
        $this->executedToolsInRequest = [];
    }

    public function wasToolExecuted(string $name): bool
    {
        return in_array($name, $this->executedToolsInRequest, true);
    }

    /**
     * @return list<string>
     */
    public function getExecutedToolsInRequest(): array
    {
        return $this->executedToolsInRequest;
    }

    private function recordExecutedTool(string $name): void
    {
        if (! in_array($name, $this->executedToolsInRequest, true))
        {
            $this->executedToolsInRequest[] = $name;
        }
    }

    /**
     * Set user and team context for tool execution when not in an HTTP auth context (e.g. WhatsApp webhook).
     * Optional customer phone (digits) links add_to_whatsapp_cart to the correct Cart session.
     */
    public function setRequestContext(?int $userId, ?int $teamId, ?string $customerPhoneDigits = null): void
    {
        $this->contextUserId = $userId;
        $this->contextTeamId = $teamId;
        $this->contextCustomerPhone = $customerPhoneDigits !== null && $customerPhoneDigits !== ''
            ? WhatsAppCartSessionKey::fromPhone($customerPhoneDigits)
            : null;
    }

    /**
     * Restrict {@see sendWhatsAppMessage()} to a single successful send per HTTP/tool turn (admin proactive opening).
     */
    public function setWhatsAppToolSingleCustomerSendPerTurn(bool $enabled): void
    {
        $this->whatsappToolSingleCustomerSendPerTurn = $enabled;
    }

    /**
     * Resolve the gateway for assistant tool sends. Local driver must use the team's Baileys base URL and team_id
     * so outbound messages go through the correct socket (never the global container binding without team_id).
     */
    private function resolveWhatsAppGatewayForToolSend(): WhatsAppGateway
    {
        if ($this->whatsAppGatewayOverride !== null)
        {
            return $this->whatsAppGatewayOverride;
        }

        if (config('whatsapp.driver') === 'local' && $this->contextTeamId !== null)
        {
            $team = Team::withoutGlobalScopes()->find($this->contextTeamId);
            if ($team !== null && $team->getWhatsAppServiceBaseUrl() !== '')
            {
                return new LocalWhatsAppGateway(
                    $team->getWhatsAppServiceBaseUrl(),
                    config('whatsapp.local.webhook_secret'),
                    $team->id,
                );
            }
        }

        return app(WhatsAppGateway::class);
    }

    /**
     * Tool definitions for Claude Messages API (name, description, input_schema).
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public function getDefinitions(): array
    {
        return [
            [
                'name' => 'list_contact_categories',
                'description' => 'List all contact categories for the current team. Use this to show categories or to find a category name before creating or assigning contacts.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'list_contact_statuses',
                'description' => 'List CRM contact lifecycle statuses (estado del contacto), e.g. Lead, En seguimiento, Conversión, Perdido, Cliente, Finalizado. Use before create_message or update_message when the user wants to filter recipients by status; pass the exact name as contact_status_name.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'search_contacts',
                'description' => 'Search CRM contacts by name, email, or phone. Use BEFORE create_contact or create_calendar_event when the user names a person (e.g. "Francisco Caballero") — returns id, full name, email, phone. Never ask the user for a contact id; use this tool instead.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Name, email, phone, or part of them to search'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results (default 10, max 25)'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'get_contact_detail',
                'description' => 'Get detailed information for one CRM contact. Provide contact_id when available; otherwise pass query (name, email, or phone) to resolve one match and return full detail.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Contact ID (preferred when known)'],
                        'query' => ['type' => 'string', 'description' => 'Name, email, or phone when contact_id is unknown'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_contact',
                'description' => 'Create a new contact. Provide at least name (full name is ok, e.g. Francisco Caballero); optionally email, phone, category name (created if missing), notes (stored in contact data JSON), and birthday (Y-m-d). Call search_contacts first when the user names someone. ALWAYS checks for an existing contact first (same email, phone, or matching full name) and returns that contact id instead of creating a duplicate.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Full name of the contact'],
                        'email' => ['type' => 'string', 'description' => 'Email address (optional)'],
                        'phone' => ['type' => 'string', 'description' => 'Phone number (optional)'],
                        'category_name' => ['type' => 'string', 'description' => 'Category name to assign; created if missing (optional)'],
                        'notes' => ['type' => 'string', 'description' => 'Free-text notes stored in contact data.notes (optional)'],
                        'birthday' => ['type' => 'string', 'description' => 'Birthday date Y-m-d (optional)'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'get_contact_categories',
                'description' => 'List the categories a contact belongs to. Use when the user asks "en qué categorías está", "qué categorías tiene", or which categories a contact is in.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Contact ID'],
                    ],
                    'required' => ['contact_id'],
                ],
            ],
            [
                'name' => 'assign_contact_to_category',
                'description' => 'Assign an existing contact to a category (adds the category without removing others). The category is created if it does not exist. Use to add more categories to a contact.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Contact ID'],
                        'category_name' => ['type' => 'string', 'description' => 'Category name (created if missing)'],
                    ],
                    'required' => ['contact_id', 'category_name'],
                ],
            ],
            [
                'name' => 'update_contact',
                'description' => 'Update an existing contact. Provide contact_id and any of: phone, email, name, surname, notes (contact data.notes JSON), birthday (Y-m-d).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Contact ID to update'],
                        'phone' => ['type' => 'string', 'description' => 'Phone number (with country code, digits only). Optional.'],
                        'email' => ['type' => 'string', 'description' => 'Email address. Optional.'],
                        'name' => ['type' => 'string', 'description' => 'Full name. Optional.'],
                        'surname' => ['type' => 'string', 'description' => 'Surname/last name. Optional.'],
                        'notes' => ['type' => 'string', 'description' => 'Free-text notes stored in contact data.notes. Optional.'],
                        'birthday' => ['type' => 'string', 'description' => 'Birthday date Y-m-d. Optional.'],
                    ],
                    'required' => ['contact_id'],
                ],
            ],
            [
                'name' => 'get_account_report',
                'description' => 'Get a short report: summary (counts of contacts and tasks), or list of contacts, or list of recent tasks.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'report_type' => [
                            'type' => 'string',
                            'description' => 'One of: summary, contacts, tasks',
                            'enum' => ['summary', 'contacts', 'tasks'],
                        ],
                    ],
                    'required' => ['report_type'],
                ],
            ],
            [
                'name' => 'send_whatsapp_message',
                'description' => 'Send a WhatsApp message to a contact. Provide either contact_id or phone (with country code, digits only).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Contact ID (optional if phone is set)'],
                        'phone' => ['type' => 'string', 'description' => 'Phone number digits, e.g. 34612345678 (optional if contact_id is set)'],
                        'message' => ['type' => 'string', 'description' => 'Message text to send'],
                    ],
                    'required' => ['message'],
                ],
            ],
            [
                'name' => 'create_task',
                'description' => 'Create a task. Optionally assign to a team member by email; if not provided, assigned to the current user. due_days is number of days from today for due date.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Task title'],
                        'description' => ['type' => 'string', 'description' => 'Task description (optional)'],
                        'responsible_email' => ['type' => 'string', 'description' => 'Email of the team member to assign (optional)'],
                        'due_days' => ['type' => 'integer', 'description' => 'Due date in days from today (default 7)'],
                    ],
                    'required' => ['title'],
                ],
            ],
            [
                'name' => 'search_tasks',
                'description' => 'Search team tasks by title (partial match). Use BEFORE update_task_status when the user names a task without giving its id — returns id, title, and current status. Never ask the user for a task id; use this tool instead.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Part of the task title to search'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results (default 10, max 25)'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'list_task_statuses',
                'description' => 'List kanban task statuses for the team (TO_DO, IN_PROGRESS, REVIEW, DONE with translated labels). Use before update_task_status when the target status is unclear.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'update_task_status',
                'description' => 'Move an existing task to another kanban column. Required task_id (from search_tasks / get_account_report). Status must be one of: TO_DO (por hacer), IN_PROGRESS (en progreso), REVIEW (en revisión), DONE (completada/finalizada) — keys or Spanish phrases.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'integer', 'description' => 'Task ID to update'],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Target status: TO_DO | IN_PROGRESS | REVIEW | DONE, or Spanish/English phrase (finalizada, revisión, en progreso, por hacer)',
                        ],
                    ],
                    'required' => ['task_id', 'status'],
                ],
            ],
            [
                'name' => 'list_team_users',
                'description' => 'List team members (name and email) for task assignment or reference.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_my_profile',
                'description' => 'Get the current user\'s profile: name, email, phone, current team, and their role in that team. Use when the user asks for their profile, "mis datos", "mi perfil", "quién soy", or similar.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'commit_assistant_flow',
                'description' => 'After the user clearly chose what they need, lock this conversation to one team flow by routing_key (same format as module_prompts, e.g. invoices:collections). Call once their intent is explicit; do not guess from vague greetings.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'routing_key' => [
                            'type' => 'string',
                            'description' => 'Exact routing key from the team flow list in system instructions (e.g. invoices:collections, products:chat_commerce).',
                        ],
                    ],
                    'required' => ['routing_key'],
                ],
            ],
            [
                'name' => 'check_calendar_availability',
                'description' => 'Check if the team calendar has events (busy slots) between start and end datetimes. Use ISO 8601 format like 2026-03-16T10:00:00.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'start' => ['type' => 'string', 'description' => 'Start datetime (Y-m-d H:i:s or ISO 8601).'],
                        'end' => ['type' => 'string', 'description' => 'End datetime (optional, defaults to one hour after start).'],
                    ],
                    'required' => ['start'],
                ],
            ],
            [
                'name' => 'create_calendar_event',
                'description' => 'Create an event in the team calendar. REQUIRED: pass start and end as local wall-clock times in Y-m-d H:i:s (e.g. 2026-06-10 14:00:00) or ISO 8601 with timezone. Refuses creation if another event overlaps that time (use check_calendar_availability first to suggest free slots). When the user says "today", "hoy", or "ahora", you MUST use the actual current date (YYYY-MM-DD) for that day — e.g. if today is 2026-03-16 and they say "hoy a las 15", use start 2026-03-16 15:00:00. Never use a different year or date unless the user explicitly states it. To invite CRM contacts, pass guest_contact_ids (array of contact ids from search/list) — do NOT only mention guests in notes.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Event title'],
                        'start' => ['type' => 'string', 'description' => 'Start datetime: Y-m-d H:i:s (local wall clock) or ISO 8601. For "today"/"hoy" use the real current date (e.g. 2026-03-16).'],
                        'end' => ['type' => 'string', 'description' => 'End datetime: Y-m-d H:i:s (local wall clock) or ISO 8601. Same date as start when user says "today"/"hoy" unless they specify duration.'],
                        'guest_contact_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Contact IDs to link as calendar guests (required when a CRM contact is the meeting guest — use id from create_contact or lookup).',
                        ],
                        'guest_name' => [
                            'type' => 'string',
                            'description' => 'Full name of a CRM contact guest (e.g. Francisco Caballero). Resolved to guest_contact_ids automatically if id is omitted.',
                        ],
                        'notes' => ['type' => 'string', 'description' => 'Optional notes (not for guest list — use guest_contact_ids)'],
                        'url' => ['type' => 'string', 'description' => 'Optional URL'],
                        'label' => ['type' => 'string', 'description' => 'Optional label such as Business, Personal, etc.'],
                        'location' => ['type' => 'string', 'description' => 'Optional location/place for the event (e.g. office, Zoom, address).'],
                    ],
                    'required' => ['title', 'start', 'end'],
                ],
            ],
            [
                'name' => 'list_calendar_events',
                'description' => 'List calendar events in a date range. Use this to find an event by title or date before updating or when the user asks what events they have. Returns event id, title, start and end so you can use the id in update_calendar_event.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'start' => ['type' => 'string', 'description' => 'Start of range (Y-m-d or Y-m-d H:i or ISO 8601).'],
                        'end' => ['type' => 'string', 'description' => 'End of range (Y-m-d or Y-m-d H:i or ISO 8601). Optional; defaults to 7 days after start.'],
                    ],
                    'required' => ['start'],
                ],
            ],
            [
                'name' => 'update_calendar_event',
                'description' => 'Update an existing calendar event. Use list_calendar_events first to get the event id when the user says "modifica el evento X" or "cambia la reunión de hoy". Pass event_id and only the fields to change (title, start, end, guest_contact_ids, notes, url, label, all_day). Use guest_contact_ids to link CRM contacts as guests — do not only write guest names in notes.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => ['type' => 'integer', 'description' => 'ID of the event to update (from list_calendar_events).'],
                        'title' => ['type' => 'string', 'description' => 'New title (optional).'],
                        'start' => ['type' => 'string', 'description' => 'New start datetime Y-m-d H:i (local wall clock) or ISO 8601 (optional).'],
                        'end' => ['type' => 'string', 'description' => 'New end datetime (optional).'],
                        'guest_contact_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Replace linked guest contacts with these contact IDs (optional).',
                        ],
                        'all_day' => ['type' => 'boolean', 'description' => 'Whether the event is all-day (optional).'],
                        'notes' => ['type' => 'string', 'description' => 'New notes (optional).'],
                        'url' => ['type' => 'string', 'description' => 'New URL (optional).'],
                        'label' => ['type' => 'string', 'description' => 'New label (optional).'],
                        'location' => ['type' => 'string', 'description' => 'New location/place (optional).'],
                    ],
                    'required' => ['event_id'],
                ],
            ],
            [
                'name' => 'create_ticket',
                'description' => 'Create a support ticket. Use when the user asks to open a ticket, "crear ticket", "abrir un ticket", or report an issue. Requires team tickets module. Subject and description required; priority optional (low, medium, high, urgent).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Ticket subject/title'],
                        'description' => ['type' => 'string', 'description' => 'Ticket description or initial message'],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priority: low, medium, high, or urgent (optional, default medium)',
                            'enum' => ['low', 'medium', 'high', 'urgent'],
                        ],
                    ],
                    'required' => ['subject', 'description'],
                ],
            ],
            [
                'name' => 'list_opportunity_stages',
                'description' => 'List CRM opportunity pipeline stages (slug and label). Use before create_opportunity if the user asks about stages or you need valid stage_slug values.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_opportunity',
                'description' => 'Create a CRM sales opportunity linked to an existing contact. Requires the opportunities module for the team. Required: contact_id (numeric id from contacts), name (short title). Optional: stage_slug (qualification, proposal, negotiation, won, lost — default qualification), opened_at (Y-m-d, default today), description, estimated_amount (number), offering_summary (text). Optional responsible_email only for team admins — otherwise the current user is set as responsible.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Contact ID in the CRM (same team)'],
                        'name' => ['type' => 'string', 'description' => 'Opportunity title'],
                        'stage_slug' => ['type' => 'string', 'description' => 'Pipeline stage slug from list_opportunity_stages (optional)'],
                        'opened_at' => ['type' => 'string', 'description' => 'Open date Y-m-d (optional, default today)'],
                        'description' => ['type' => 'string', 'description' => 'Longer description (optional)'],
                        'estimated_amount' => ['type' => 'number', 'description' => 'Estimated value (optional)'],
                        'offering_summary' => ['type' => 'string', 'description' => 'Free-text offering summary when no catalog product is linked (optional)'],
                        'responsible_email' => ['type' => 'string', 'description' => 'Assign to this team member by email (optional; admins only)'],
                    ],
                    'required' => ['contact_id', 'name'],
                ],
            ],
            [
                'name' => 'add_ticket_response',
                'description' => 'Add a reply to an existing support ticket (as admin/support). Use when the user asks to respond to a ticket, "responder al ticket X", or "contestá el ticket". Pass ticket_id and message. Optionally set is_internal_note to true for internal notes (admin only, not visible to client).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'integer', 'description' => 'ID of the ticket to reply to'],
                        'message' => ['type' => 'string', 'description' => 'Reply message text'],
                        'is_internal_note' => ['type' => 'boolean', 'description' => 'If true, add as internal note (admin only, not visible to client). Default false.'],
                    ],
                    'required' => ['ticket_id', 'message'],
                ],
            ],
            [
                'name' => 'list_templates',
                'description' => 'List email templates for the current team. Use when the user asks for templates, "lista de plantillas", "plantillas", or to choose one before activating/suspending or modifying. Returns id, name, status (active/suspended).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_template',
                'description' => 'Create a NEW email template from scratch only. Use ONLY when the user explicitly asks to create a new template (e.g. "crear plantilla", "nueva plantilla"). Do NOT use for modifying an existing template — use update_template or update_template_status instead, or direct the user to the editor link for content/design changes. Provide name; optionally ai_prompt for AI-generated HTML. Returns view and editor links.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Template name'],
                        'ai_prompt' => ['type' => 'string', 'description' => 'Optional: describe the template to generate HTML with AI (e.g. "Newsletter with logo and CTA button")'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'update_template_status',
                'description' => 'Activate or suspend an email template. Use when the user says "activar plantilla X", "suspender plantilla Y", "activar la plantilla", "desactivar la plantilla". Pass template_id and status: active or suspended.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'integer', 'description' => 'Template ID (from list_templates)'],
                        'status' => [
                            'type' => 'string',
                            'description' => 'active or suspended',
                            'enum' => ['active', 'suspended'],
                        ],
                    ],
                    'required' => ['template_id', 'status'],
                ],
            ],
            [
                'name' => 'update_template',
                'description' => 'Modify an EXISTING template (e.g. rename). Use when the user asks to change an existing template\'s name or details ("cambia el nombre", "renombrar plantilla"). Pass template_id (from list_templates) and name. If the user said "la plantilla" or "esa", use list_templates first to get the template_id. For HTML/design changes, tell the user to open the editor link — do not create a new template.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'integer', 'description' => 'Template ID to update'],
                        'name' => ['type' => 'string', 'description' => 'New template name (optional)'],
                    ],
                    'required' => ['template_id'],
                ],
            ],
            [
                'name' => 'list_messages',
                'description' => 'List campaign messages (News/campaigns) for the current team. Use when the user asks for "mensajes", "campañas", "lista de campañas", or to choose one before creating or editing. Returns id, name, channel (email/WhatsApp), template, category, status (active/inactive).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_message',
                'description' => 'Create a NEW campaign message (News / newsletter / bulk email or WhatsApp) only. Use ONLY when the user explicitly wants a new campaign ("crear mensaje", "crear newsletter", "crear email masivo", "crear campaña", "crear News"). Do NOT use to change an existing campaign — use update_message or update_message_status. BEFORE calling this tool: if the user has not already given all of (1) subject/title for the campaign, (2) recipient filters — optional contact category (category_name from list_contact_categories) and/or optional CRM contact status (contact_status_name from list_contact_statuses: Lead, En seguimiento, Conversión, Perdido, Cliente, Finalizado, etc.), or they clearly want all contacts with neither filter, and (3) what they want to communicate (short summary for the text / preview field), ask them in one friendly message — do not guess names. Map (1) to name, (3) to text (min 3 chars). Call list_templates first; pick template_id from the list (match channel to email vs whatsapp). If the user did not choose a template, you may use the first suitable active template for that channel after listing. Optional: category_name, contact_status_name, active (default false). After success, say they are being taken to the editor to continue. In user-facing summaries (especially Spanish), never label sending on/off as bare "Estado" — that is ambiguous with CRM contact status; say campaign sending on/off, envío activo/pausado, or campaña pausada.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Campaign subject/title (asunto del envío)'],
                        'template_id' => ['type' => 'integer', 'description' => 'Template ID (from list_templates)'],
                        'channel' => [
                            'type' => 'string',
                            'description' => 'Channel: email or whatsapp',
                            'enum' => ['email', 'whatsapp'],
                        ],
                        'text' => ['type' => 'string', 'description' => 'What to communicate: short summary or alternative/preview text (required, min 3 chars; user may refine in the editor after)'],
                        'category_name' => ['type' => 'string', 'description' => 'Optional audience filter: contact category/segment (use list_contact_categories for exact names). Independent from CRM contact status.'],
                        'contact_status_name' => ['type' => 'string', 'description' => 'Optional audience filter: CRM contact lifecycle status — exact name from list_contact_statuses (e.g. Lead, En seguimiento, Conversión, Perdido, Cliente, Finalizado). Not campaign sending on/off.'],
                        'active' => ['type' => 'boolean', 'description' => 'If true, campaign sending is enabled (default false: sending paused until enabled in Messages/News)'],
                    ],
                    'required' => ['name', 'template_id', 'channel', 'text'],
                ],
            ],
            [
                'name' => 'update_message_status',
                'description' => 'Enable or pause campaign sending (whether the newsletter/campaign delivers). Not the same as CRM contact status. Use when the user says "detener la campaña X", "pausar mensaje", "activar la campaña Y", "parar el mensaje". Pass message_id (from list_messages) and status: active or paused. Paused stops deliveries; active allows sending per rules.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'integer', 'description' => 'Campaign message ID (from list_messages)'],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Campaign sending: active (deliveries allowed) or paused (stopped)',
                            'enum' => ['active', 'paused'],
                        ],
                    ],
                    'required' => ['message_id', 'status'],
                ],
            ],
            [
                'name' => 'update_message',
                'description' => 'Update an EXISTING campaign message: optional contact category, optional CRM contact-status filter (estado del contacto: Lead, En seguimiento, etc. from list_contact_statuses), and/or campaign sending on/off. "Contact status" in tool args is the CRM lifecycle audience filter, not whether the campaign sends. Use when the user asks to change the current/last campaign. Do NOT use create_message for these — use list_messages to get message_id, then update_message. Pass message_id (required); optionally category_name, contact_status_name, status (active = sending on, paused = sending off).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'integer', 'description' => 'Campaign message ID to update (from list_messages)'],
                        'category_name' => ['type' => 'string', 'description' => 'Optional new contact category audience filter (use list_contact_categories)'],
                        'contact_status_name' => ['type' => 'string', 'description' => 'Optional CRM contact lifecycle filter; exact name from list_contact_statuses (e.g. Lead, Perdido). Not campaign sending on/off'],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Campaign sending: active or paused (optional; not CRM contact status)',
                            'enum' => ['active', 'paused'],
                        ],
                    ],
                    'required' => ['message_id'],
                ],
            ],
            [
                'name' => 'list_product_catalog',
                'description' => 'List WhatsApp-enabled published products for the team catalog, grouped by category. Use when the user asks for catalog, "catálogo", "productos", "qué venden", or to browse by category. Optional category_name filters by category name (partial match).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'category_name' => ['type' => 'string', 'description' => 'Optional: only products in categories whose name contains this text'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'search_products',
                'description' => 'Search sellable products by name fragment or by internal code (SKU). Use for "busco X", "tenés código ABC", "precio de la camiseta", etc. Returns id, name, code, price, category.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Text to match against product name (partial) or exact/partial code'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'add_to_whatsapp_cart',
                'description' => 'Add a product to the WhatsApp customer\'s shopping cart. Requires the customer\'s WhatsApp session (inbound chat). Use when they want to buy, "agregar al carrito", "quiero comprar", after confirming product id or code. Pass exactly one of: product_id, product_code, or product_name.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer', 'description' => 'Product database id (from list_product_catalog or search_products)'],
                        'product_code' => ['type' => 'string', 'description' => 'Product SKU/code (case-insensitive match)'],
                        'product_name' => ['type' => 'string', 'description' => 'Product name (partial match, first hit)'],
                        'quantity' => ['type' => 'integer', 'description' => 'Quantity (default 1, min 1)'],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * Execute a tool by name and return a short text result for Claude.
     * User and team are resolved from auth() or from setRequestContext() (e.g. WhatsApp).
     */
    public function execute(string $name, array $input): string
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam?->id;

        if ((! $user || ! $teamId) && $this->contextUserId !== null && $this->contextTeamId !== null)
        {
            $user = User::withoutGlobalScopes()->find($this->contextUserId);
            $team = $user ? \App\Models\Team::withoutGlobalScopes()->find($this->contextTeamId) : null;
            if ($team)
            {
                $user?->setRelation('currentTeam', $team);
            }
            $teamId = $team?->id ?? $this->contextTeamId;
            // Log in the context user so Gate/policies see them (e.g. create contact from WhatsApp).
        }

        if (! $user || ! $teamId)
        {
            return 'Error: Not authenticated or no team selected.';
        }

        $denied = $this->toolAuthorization->denyReasonForTool($name, $user, $teamId);
        if ($denied !== null)
        {
            return $denied;
        }

        $this->recordExecutedTool($name);

        try
        {
            $result = match ($name)
            {
                'list_contact_categories' => $this->listContactCategories($teamId),
                'list_contact_statuses' => $this->listContactStatuses(),
                'get_contact_categories' => $this->getContactCategories($teamId, $user, $input),
                'search_contacts' => $this->searchContacts($teamId, $user, $input),
                'get_contact_detail' => $this->getContactDetail($teamId, $user, $input),
                'create_contact' => $this->createContact($teamId, $user, $input),
                'assign_contact_to_category' => $this->assignContactToCategory($teamId, $user, $input),
                'update_contact' => $this->updateContact($teamId, $user, $input),
                'get_account_report' => $this->getAccountReport($teamId, $input),
                'send_whatsapp_message' => $this->sendWhatsAppMessage($user, $input),
                'create_task' => $this->createTask($teamId, $user->id, $input),
                'search_tasks' => $this->searchTasks($teamId, $input),
                'list_task_statuses' => $this->listTaskStatuses(),
                'update_task_status' => $this->updateTaskStatus($teamId, $user, $input),
                'list_team_users' => $this->listTeamUsers($teamId),
                'get_my_profile' => $this->getMyProfile($user, $teamId),
                'commit_assistant_flow' => $this->commitAssistantFlow($teamId, $input),
                'check_calendar_availability' => $this->checkCalendarAvailability($teamId, $input),
                'create_calendar_event' => $this->createCalendarEvent($teamId, $input),
                'list_calendar_events' => $this->listCalendarEvents($teamId, $input),
                'update_calendar_event' => $this->updateCalendarEvent($teamId, $input),
                'create_ticket' => $this->createTicket($teamId, $user, $input),
                'list_opportunity_stages' => $this->listOpportunityStages(),
                'create_opportunity' => $this->createOpportunity($teamId, $user, $input),
                'add_ticket_response' => $this->addTicketResponse($teamId, $user, $input),
                'list_templates' => $this->listTemplates($teamId),
                'create_template' => $this->createTemplate($teamId, $user->id, $input),
                'update_template_status' => $this->updateTemplateStatus($teamId, $user, $input),
                'update_template' => $this->updateTemplate($teamId, $user, $input),
                'list_messages' => $this->listMessages($teamId),
                'create_message' => $this->createMessage($teamId, $user, $input),
                'update_message_status' => $this->updateMessageStatus($teamId, $user, $input),
                'update_message' => $this->updateMessage($teamId, $user, $input),
                'list_product_catalog' => $this->listProductCatalog($teamId, $input),
                'search_products' => $this->searchProducts($teamId, $input),
                'add_to_whatsapp_cart' => $this->addToWhatsAppCart($teamId, $input),
                default => "Unknown tool: {$name}.",
            };

            return $result;
        } catch (\Throwable $e)
        {
            report($e);

            return $this->formatToolFailureForModel($name, $e);
        }
    }

    private function formatToolFailureForModel(string $toolName, \Throwable $e): string
    {
        return 'Tool "'.$toolName.'" failed internally. Do NOT tell the user there is a database outage, '
            .'"problema técnico", or that contact search is broken. '
            .'For contact names: try create_contact with the name the user gave. '
            .'For meetings: use create_calendar_event with guest_name if guest_contact_ids are unavailable. '
            .'You may ask for missing date/time only. (Logged for support.)';
    }

    private function listContactCategories(int $teamId): string
    {
        $module = Module::where('key', 'contacts')->first();
        if (! $module)
        {
            return 'Contact module not found.';
        }

        $categories = Category::withoutGlobalScopes()
            ->where('module_id', $module->id)
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($categories->isEmpty())
        {
            return 'No contact categories defined yet. You can create one when creating a contact with category_name.';
        }

        $lines = $categories->map(fn ($c) => "  - {$c->name} (id: {$c->id})")->implode("\n");

        return "Contact categories:\n".$lines;
    }

    private function listContactStatuses(): string
    {
        $rows = ContactStatus::query()->orderBy('id')->get(['id', 'name']);

        if ($rows->isEmpty())
        {
            return 'No CRM contact statuses are configured yet.';
        }

        $lines = $rows->map(fn ($s) => "  - {$s->name} (id: {$s->id})")->implode("\n");

        return "CRM contact statuses (estado del contacto / lifecycle; use contact_status_name in create_message or update_message — exact name):\n".$lines;
    }

    private function searchContacts(int $teamId, User $user, array $input): string
    {
        Log::info('AssistantToolsService search_contacts start', [
            'team_id' => $teamId,
            'user_id' => $user->id,
            'query' => isset($input['query']) ? (string) $input['query'] : null,
            'limit' => isset($input['limit']) ? (int) $input['limit'] : null,
        ]);

        if (! Gate::forUser($user)->allows('viewAny', Contact::class))
        {
            return 'You do not have permission to list contacts.';
        }

        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '')
        {
            return 'query is required (name, email, or phone).';
        }

        $limit = isset($input['limit']) ? (int) $input['limit'] : TeamContactMatcher::SEARCH_DEFAULT_LIMIT;

        try
        {
            $contacts = app(TeamContactMatcher::class)->search($teamId, $query, $limit);
        } catch (\Throwable $e)
        {
            report($e);

            return 'Contact search failed for "'.$query.'". Do not mention database errors to the user. '
                .'Call create_contact with that name if they asked to add someone, or use guest_name on create_calendar_event.';
        }

        if ($contacts->isEmpty())
        {
            Log::info('AssistantToolsService search_contacts no matches', [
                'team_id' => $teamId,
                'user_id' => $user->id,
                'query' => $query,
            ]);

            return $this->truncate(
                'No contacts found for "'.$query.'". Call create_contact with name "'.$query.'" now (email/phone optional) if the user asked to add this person — do not tell the user search is broken.',
            );
        }

        $lines = $contacts->map(function (Contact $contact)
        {
            $fullName = trim($contact->name.' '.($contact->surname ?? ''));
            $parts = ['id '.$contact->id.': '.$fullName];
            if ($contact->email)
            {
                $parts[] = $contact->email;
            }
            if ($contact->phone)
            {
                $parts[] = 'tel '.$contact->phone;
            }

            return '  - '.implode(' | ', $parts);
        })->implode("\n");

        $count = $contacts->count();
        $header = $count === 1
            ? 'Found 1 contact (use this id in guest_contact_ids or create_contact):'
            : "Found {$count} contacts (pick the correct id for guest_contact_ids):";

        Log::info('AssistantToolsService search_contacts success', [
            'team_id' => $teamId,
            'user_id' => $user->id,
            'query' => $query,
            'matches_count' => $count,
            'match_ids' => $contacts->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ]);

        return $this->truncate($header."\n".$lines);
    }

    private function getContactDetail(int $teamId, User $user, array $input): string
    {
        Log::info('AssistantToolsService get_contact_detail start', [
            'team_id' => $teamId,
            'user_id' => $user->id,
            'contact_id' => isset($input['contact_id']) ? (int) $input['contact_id'] : null,
            'query' => isset($input['query']) ? (string) $input['query'] : null,
        ]);

        $contact = null;
        $contactId = (int) ($input['contact_id'] ?? 0);

        if ($contactId > 0)
        {
            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->with(['status', 'categories:id,name'])
                ->find($contactId);
        } else
        {
            $query = trim((string) ($input['query'] ?? ''));
            if ($query === '')
            {
                return 'Provide contact_id or query to get contact detail.';
            }

            $matches = app(TeamContactMatcher::class)->search($teamId, $query, 2);
            if ($matches->isEmpty())
            {
                Log::info('AssistantToolsService get_contact_detail no matches by query', [
                    'team_id' => $teamId,
                    'user_id' => $user->id,
                    'query' => $query,
                ]);

                return 'No contacts found for "'.$query.'".';
            }

            if ($matches->count() > 1)
            {
                $options = $matches->map(function (Contact $match)
                {
                    $fullName = trim($match->name.' '.($match->surname ?? ''));

                    return '  - id '.$match->id.': '.$fullName;
                })->implode("\n");

                return $this->truncate("Multiple contacts found for \"{$query}\". Use contact_id:\n".$options);
            }

            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->with(['status', 'categories:id,name'])
                ->find((int) $matches->first()->id);
        }

        if (! $contact)
        {
            return $contactId > 0
                ? "Contact with id {$contactId} not found."
                : 'Contact not found.';
        }

        if (! Gate::forUser($user)->allows('view', $contact))
        {
            return 'You do not have permission to view this contact.';
        }

        $fullName = trim($contact->name.' '.($contact->surname ?? ''));
        $categories = $contact->categories
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->values()
            ->all();
        $notes = is_object($contact->data ?? null) ? (string) ($contact->data->notes ?? '') : '';
        $details = [
            "Contact detail: {$fullName} (id: {$contact->id})",
            'Email: '.($contact->email ?: 'N/A'),
            'Phone: '.($contact->phone ? (string) $contact->phone : 'N/A'),
            'Status: '.($contact->status->name ?? 'N/A'),
            'Categories: '.($categories !== [] ? implode(', ', $categories) : 'N/A'),
            'Notes: '.($notes !== '' ? $notes : 'N/A'),
            'Profile URL: '.route('contact.show', $contact->id),
        ];

        Log::info('AssistantToolsService get_contact_detail success', [
            'team_id' => $teamId,
            'user_id' => $user->id,
            'contact_id' => (int) $contact->id,
        ]);

        return $this->truncate(implode("\n", $details));
    }

    private function createContact(int $teamId, User $user, array $input): string
    {
        Log::info('AssistantToolsService create_contact start', [
            'team_id' => $teamId,
            'user_id' => $user->id,
            'input_name' => isset($input['name']) ? (string) $input['name'] : null,
            'input_email' => isset($input['email']) ? (string) $input['email'] : null,
            'input_phone' => isset($input['phone']) ? (string) $input['phone'] : null,
            'input_category_name' => isset($input['category_name']) ? (string) $input['category_name'] : null,
        ]);

        if (! Gate::forUser($user)->allows('create', Contact::class))
        {
            return 'You do not have permission to create contacts for this team. Tell the user their role cannot add CRM contacts; do not say search failed.';
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '')
        {
            return 'Name is required to create a contact.';
        }

        $email = isset($input['email']) ? trim((string) $input['email']) : null;
        $email = $email !== '' ? $email : null;
        $phone = isset($input['phone']) ? preg_replace('/[^0-9]/', '', (string) $input['phone']) : null;
        $phone = is_string($phone) && $phone !== '' ? (int) $phone : null;
        if ($phone !== null && $phone < 1)
        {
            $phone = null;
        }
        $categoryName = isset($input['category_name']) ? trim((string) $input['category_name']) : null;

        $matcher = app(TeamContactMatcher::class);
        $existing = $matcher->findExisting($teamId, $email, $phone, $name);

        if ($existing)
        {
            if (! Gate::forUser($user)->allows('update', $existing))
            {
                return "A contact matching this data already exists (id: {$existing->id}) but you cannot update it.";
            }

            $this->applyContactOptionalFields($existing, $input);
            $categoryId = $this->attachContactToCategoryName($existing, $teamId, $categoryName);

            $this->registerRecentContactId((int) $existing->id);

            $out = "Contact already exists: {$existing->name} (id: {$existing->id}). No duplicate was created.";
            if ($categoryId && $categoryName)
            {
                $out .= " Assigned to category: {$categoryName}.";
            }
            $out .= ' Use id '.$existing->id.' in guest_contact_ids when scheduling a calendar event with this person.';

            Log::info('AssistantToolsService create_contact reused existing contact', [
                'team_id' => $teamId,
                'user_id' => $user->id,
                'contact_id' => (int) $existing->id,
                'category_assigned' => $categoryId !== null,
            ]);

            return $this->truncate($out);
        }

        [$firstName, $surname] = $matcher->splitFullName($name);

        $payload = [
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => $firstName,
            'surname' => $surname,
            'email' => $email,
            'phone' => $phone,
            'status_id' => 1,
        ];

        $optional = $this->buildContactOptionalAttributes($input);
        $contact = Contact::withoutGlobalScopes()->create(array_merge($payload, $optional));

        $categoryId = $this->attachContactToCategoryName($contact, $teamId, $categoryName);

        $this->registerRecentContactId((int) $contact->id);

        $out = "Contact created: {$contact->name} (id: {$contact->id}).";
        if ($categoryId && $categoryName)
        {
            $out .= " Assigned to category: {$categoryName}.";
        }
        $out .= ' Use id '.$contact->id.' in guest_contact_ids when scheduling a calendar event with this person.';

        Log::info('AssistantToolsService create_contact created new contact', [
            'team_id' => $teamId,
            'user_id' => $user->id,
            'contact_id' => (int) $contact->id,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'category_assigned' => $categoryId !== null,
        ]);

        return $this->truncate($out);
    }

    private function assignContactToCategory(int $teamId, User $user, array $input): string
    {
        $contactId = (int) ($input['contact_id'] ?? 0);
        $categoryName = trim((string) ($input['category_name'] ?? ''));

        if ($contactId < 1 || $categoryName === '')
        {
            return 'contact_id and category_name are required.';
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($contactId);

        if (! $contact)
        {
            return "Contact with id {$contactId} not found.";
        }

        if (! Gate::forUser($user)->allows('update', $contact))
        {
            return 'You do not have permission to update this contact.';
        }

        $categoryId = $this->resolveOrCreateContactCategory($teamId, $categoryName);
        if (! $categoryId)
        {
            return "Could not resolve or create category: {$categoryName}.";
        }

        $contact->categories()->syncWithoutDetaching([$categoryId]);

        return $this->truncate("Contact {$contact->name} (id: {$contact->id}) assigned to category: {$categoryName}.");
    }

    private function getContactCategories(int $teamId, User $user, array $input): string
    {
        $contactId = (int) ($input['contact_id'] ?? 0);
        if ($contactId < 1)
        {
            return 'contact_id is required.';
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($contactId);

        if (! $contact)
        {
            return "Contact with id {$contactId} not found.";
        }

        if (! Gate::forUser($user)->allows('view', $contact))
        {
            return 'You do not have permission to view this contact.';
        }

        $categories = $contact->categories()->orderBy('name')->get(['id', 'name']);
        if ($categories->isEmpty())
        {
            return $this->truncate("Contact {$contact->name} (id: {$contact->id}) has no categories. You can add one with assign_contact_to_category.");
        }

        $lines = $categories->map(fn ($c) => "  - {$c->name} (id: {$c->id})")->implode("\n");

        return $this->truncate("Categories for {$contact->name} (id: {$contact->id}):\n".$lines);
    }

    private function updateContact(int $teamId, User $user, array $input): string
    {
        $contactId = (int) ($input['contact_id'] ?? 0);
        if ($contactId < 1)
        {
            return 'contact_id is required.';
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($contactId);

        if (! $contact)
        {
            return "Contact with id {$contactId} not found.";
        }

        if (! Gate::forUser($user)->allows('update', $contact))
        {
            return 'You do not have permission to update this contact.';
        }

        $updates = [];
        if (array_key_exists('phone', $input) && (string) $input['phone'] !== '')
        {
            $phone = preg_replace('/[^0-9]/', '', (string) $input['phone']);
            $updates['phone'] = $phone !== '' ? (int) $phone : null;
        }
        if (array_key_exists('email', $input))
        {
            $email = trim((string) $input['email']);
            $updates['email'] = $email !== '' ? $email : null;
        }
        if (array_key_exists('name', $input) && trim((string) $input['name']) !== '')
        {
            $nameInput = trim((string) $input['name']);
            $surnameInput = trim((string) ($input['surname'] ?? ''));

            if ($surnameInput !== '')
            {
                $updates['name'] = $nameInput;
            } else
            {
                [$firstName, $surnameFromName] = app(TeamContactMatcher::class)->splitFullName($nameInput);
                $updates['name'] = $firstName;
                if ($surnameFromName !== '')
                {
                    $updates['surname'] = $surnameFromName;
                }
            }
        }

        if (array_key_exists('surname', $input) && trim((string) $input['surname']) !== '')
        {
            $updates['surname'] = trim((string) $input['surname']);
        }

        if (array_key_exists('birthday', $input))
        {
            $birthdayRaw = trim((string) $input['birthday']);
            if ($birthdayRaw !== '')
            {
                try
                {
                    $updates['birthday'] = Carbon::parse($birthdayRaw)->format('Y-m-d');
                } catch (\Throwable)
                {
                    return 'Invalid birthday date format. Use Y-m-d.';
                }
            }
        }

        if (array_key_exists('notes', $input))
        {
            $notes = trim((string) $input['notes']);
            if ($notes !== '')
            {
                $data = (array) ($contact->data ?? []);
                $data['notes'] = $notes;
                $updates['data'] = (object) $data;
            }
        }

        if (empty($updates))
        {
            return 'Provide at least one field to update: phone, email, name, surname, notes, or birthday.';
        }

        $contact->update($updates);
        $updated = array_keys($updates);
        if (array_key_exists('notes', $input) && trim((string) $input['notes']) !== '')
        {
            $updated[] = 'notes';
        }

        return $this->truncate("Contact {$contact->name} (id: {$contact->id}) updated: ".implode(', ', array_unique($updated)).'.');
    }

    private function getAccountReport(int $teamId, array $input): string
    {
        $reportType = $input['report_type'] ?? 'summary';

        if ($reportType === 'summary')
        {
            $contactCount = Contact::withoutGlobalScopes()->where('team_id', $teamId)->count();
            $taskCount = Task::withoutGlobalScopes()->where('team_id', $teamId)->count();

            return $this->truncate("Account summary: {$contactCount} contacts, {$taskCount} tasks.");
        }

        if ($reportType === 'contacts')
        {
            $contacts = Contact::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'email']);

            $lines = $contacts->map(fn ($c) => "  - {$c->name} (id: {$c->id})".($c->email ? " - {$c->email}" : ''))->implode("\n");

            return $this->truncate("Contacts:\n".($lines ?: '  (none)'));
        }

        if ($reportType === 'tasks')
        {
            $tasks = Task::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->with('status')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'title', 'status_id', 'due_date']);

            $lines = $tasks->map(fn ($t) => '  - '.$t->title.' (id: '.$t->id.', status: '.($t->status->name ?? 'N/A').', due: '.($t->due_date?->format('Y-m-d') ?? 'N/A').')')->implode("\n");

            return $this->truncate("Recent tasks:\n".($lines ?: '  (none)'));
        }

        return "Unknown report_type: {$reportType}. Use summary, contacts, or tasks.";
    }

    private function sendWhatsAppMessage(User $user, array $input): string
    {
        $contactId = isset($input['contact_id']) ? (int) $input['contact_id'] : null;
        $phone = isset($input['phone']) ? preg_replace('/[^0-9]/', '', (string) $input['phone']) : null;
        $message = trim((string) ($input['message'] ?? ''));

        if ($message === '')
        {
            return 'Message text is required.';
        }

        if ($contactId !== null)
        {
            $contact = Contact::withoutGlobalScopes()->find($contactId);
            if (! $contact)
            {
                return "Contact id {$contactId} not found.";
            }
            $phone = $contact->getWhatsAppNumber();
            if ($phone)
            {
                $phone = preg_replace('/[^0-9]/', '', $phone);
            }
        }

        if (! $phone || $phone === '')
        {
            return 'Provide either contact_id or phone (digits with country code) to send WhatsApp.';
        }

        if ($this->contextCustomerPhone !== null && $this->contextCustomerPhone !== '')
        {
            if (WhatsAppCartSessionKey::fromPhone((string) $phone) !== $this->contextCustomerPhone)
            {
                Log::warning('AssistantToolsService blocked cross-thread WhatsApp send', [
                    'tool' => 'send_whatsapp_message',
                    'requested_phone' => $phone,
                    'context_customer_phone' => $this->contextCustomerPhone,
                    'context_user_id' => $this->contextUserId,
                    'context_team_id' => $this->contextTeamId,
                    'input_contact_id' => $contactId,
                ]);

                return 'For WhatsApp inbound conversations, send_whatsapp_message can only reply to the current customer phone in this chat.';
            }
        }

        $gateway = $this->resolveWhatsAppGatewayForToolSend();
        if (! $gateway->isConfigured())
        {
            return 'WhatsApp is not configured for this team.';
        }

        $outbound = WhatsAppOutboundText::stripInternalQaMarkers(WhatsAppOutboundText::sanitize($message));
        if ($outbound === '')
        {
            return 'Message text is empty after sanitization.';
        }

        if ($this->whatsappToolSingleCustomerSendPerTurn && $this->whatsappToolSendCount >= 1)
        {
            return 'Opening WhatsApp was already sent in this turn. Do not call send_whatsapp_message again; reply with a brief acknowledgement for the operator only (not a second customer message).';
        }

        $gateway->sendMessage($phone, $outbound, null, $user->id);

        if ($this->whatsappToolSingleCustomerSendPerTurn)
        {
            $this->whatsappToolSendCount++;
        }

        return $this->truncate("WhatsApp message sent to {$phone}.");
    }

    private function createTask(int $teamId, int $currentUserId, array $input): string
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '')
        {
            return 'Task title is required.';
        }

        $description = isset($input['description']) ? trim((string) $input['description']) : null;
        $responsibleEmail = isset($input['responsible_email']) ? trim((string) $input['responsible_email']) : null;
        $dueDays = isset($input['due_days']) ? (int) $input['due_days'] : 7;
        if ($dueDays < 1)
        {
            $dueDays = 7;
        }

        $responsibleId = $currentUserId;
        if ($responsibleEmail !== null && $responsibleEmail !== '')
        {
            $user = User::withoutGlobalScopes()
                ->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
                ->where('email', $responsibleEmail)
                ->first();

            if ($user)
            {
                $responsibleId = $user->id;
            }
        }

        $board = TaskBoard::getDefaultBoard();
        $boardId = $board ? $board->id : null;
        $defaultStatus = TaskStatus::orderBy('order')->first();
        $statusId = $defaultStatus ? $defaultStatus->id : 1;

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'board_id' => $boardId,
            'title' => $title,
            'description' => $description ?? '',
            'responsible_id' => $responsibleId,
            'status_id' => $statusId,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays($dueDays)->toDateString(),
            'order' => 0,
        ]);

        $assignee = $responsibleId === $currentUserId ? 'you' : "user id {$responsibleId}";
        $dueStr = $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d') : 'N/A';

        return $this->truncate("Task created: {$task->title} (id: {$task->id}), assigned to {$assignee}, due {$dueStr}.");
    }

    private function searchTasks(int $teamId, array $input): string
    {
        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '')
        {
            return 'Search query is required.';
        }

        $limit = isset($input['limit']) ? (int) $input['limit'] : 10;
        $limit = max(1, min(25, $limit));

        $tasks = Task::withoutGlobalScopes()
            ->with('status')
            ->where('team_id', $teamId)
            ->where('title', 'like', '%'.$query.'%')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($tasks->isEmpty())
        {
            return 'No tasks found matching "'.$query.'".';
        }

        $lines = $tasks->map(function (Task $task): string
        {
            $statusKey = $task->status?->name ?? 'UNKNOWN';
            $statusLabel = $task->status?->translated_name ?? $statusKey;

            return '  - '.$task->title.' (id: '.$task->id.', status: '.$statusKey.' / '.$statusLabel.')';
        })->implode("\n");

        $count = $tasks->count();

        return $this->truncate("Found {$count} task(s):\n".$lines);
    }

    private function listTaskStatuses(): string
    {
        $statuses = TaskStatus::orderBy('order')->get();
        $lines = $statuses->map(function (TaskStatus $status): string
        {
            return '  - '.$status->name.' → '.$status->translated_name;
        })->implode("\n");

        return $this->truncate("Task statuses:\n".($lines ?: '  (none)'));
    }

    private function updateTaskStatus(int $teamId, User $user, array $input): string
    {
        $taskId = (int) ($input['task_id'] ?? 0);
        $statusInput = trim((string) ($input['status'] ?? ''));

        if ($taskId < 1)
        {
            return 'task_id is required.';
        }
        if ($statusInput === '')
        {
            return 'status is required (e.g. DONE, REVIEW, finalizada, en revisión).';
        }

        $task = Task::withoutGlobalScopes()
            ->with('status')
            ->where('team_id', $teamId)
            ->find($taskId);

        if ($task === null)
        {
            return "Task with id {$taskId} not found in this team.";
        }

        $targetStatus = $this->resolveTaskStatusFromAssistantInput($statusInput);
        if ($targetStatus === null)
        {
            $available = TaskStatus::orderBy('order')->pluck('name')->implode(', ');

            return 'Unknown status "'.$statusInput.'". Use one of: '.$available.' (or Spanish: finalizada/completada, revisión, en progreso, por hacer).';
        }

        if ((int) $task->status_id === (int) $targetStatus->id)
        {
            $label = $targetStatus->translated_name;
            $sentinel = AssistantTaskStatusUpdate::formatSentinelLine(
                (int) $task->id,
                (int) $targetStatus->id,
                (string) $targetStatus->name,
            );

            return $this->truncate(
                $sentinel."\n"
                ."Task \"{$task->title}\" (id: {$task->id}) is already in status {$targetStatus->name} ({$label}).",
            );
        }

        $previousLabel = $task->status?->translated_name ?? (string) ($task->status?->name ?? '—');
        $maxOrder = (int) Task::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('status_id', $targetStatus->id)
            ->when($task->board_id !== null, fn ($q) => $q->where('board_id', $task->board_id))
            ->max('order');

        $task->status_id = $targetStatus->id;
        $task->order = $maxOrder + 1;
        $task->save();

        $newLabel = $targetStatus->translated_name;

        $sentinel = AssistantTaskStatusUpdate::formatSentinelLine(
            (int) $task->id,
            (int) $targetStatus->id,
            (string) $targetStatus->name,
        );

        return $this->truncate(
            $sentinel."\n"
            ."Task \"{$task->title}\" (id: {$task->id}) moved from {$previousLabel} to {$targetStatus->name} ({$newLabel}).",
        );
    }

    /**
     * Map assistant status input to a TaskStatus row (keys TO_DO, IN_PROGRESS, REVIEW, DONE or natural language).
     */
    private function resolveTaskStatusFromAssistantInput(string $statusInput): ?TaskStatus
    {
        $normalized = Str::ascii(mb_strtolower(trim($statusInput)));
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? $normalized;

        $aliases = [
            'to_do' => 'TO_DO',
            'todo' => 'TO_DO',
            'por_hacer' => 'TO_DO',
            'pendiente' => 'TO_DO',
            'in_progress' => 'IN_PROGRESS',
            'inprogress' => 'IN_PROGRESS',
            'en_progreso' => 'IN_PROGRESS',
            'progreso' => 'IN_PROGRESS',
            'review' => 'REVIEW',
            'en_revision' => 'REVIEW',
            'revision' => 'REVIEW',
            'done' => 'DONE',
            'finalizada' => 'DONE',
            'finalizado' => 'DONE',
            'completada' => 'DONE',
            'completado' => 'DONE',
            'terminada' => 'DONE',
            'terminado' => 'DONE',
            'hecha' => 'DONE',
            'hecho' => 'DONE',
        ];

        $key = $aliases[$normalized] ?? strtoupper(str_replace(' ', '_', $statusInput));

        if (in_array($key, ['TO_DO', 'IN_PROGRESS', 'REVIEW', 'DONE'], true))
        {
            return TaskStatus::query()->where('name', $key)->first();
        }

        if (ctype_digit($statusInput))
        {
            return TaskStatus::query()->find((int) $statusInput);
        }

        return null;
    }

    private function listTeamUsers(int $teamId): string
    {
        $users = User::withoutGlobalScopes()
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $lines = $users->map(fn ($u) => "  - {$u->name} ({$u->email}) id: {$u->id}")->implode("\n");

        return $this->truncate("Team members:\n".($lines ?: '  (none)'));
    }

    private function getMyProfile(User $user, int $teamId): string
    {
        $team = \App\Models\Team::withoutGlobalScopes()->find($teamId);
        $roleInTeam = null;
        if ($team)
        {
            $membership = $user->teams()->where('team_id', $team->id)->first();
            $roleInTeam = $membership?->pivot?->role ?? null;
        }

        $parts = [
            'Name: '.$user->name,
            'Email: '.($user->email ?? '—'),
            'Phone: '.($user->phone !== null ? (string) $user->phone : '—'),
            'Current team: '.($team ? $team->name : '—'),
            'Role in team: '.($roleInTeam ?? '—'),
            'Global roles: '.(implode(', ', $user->roles->pluck('name')->all()) ?: '—'),
        ];

        return $this->truncate("Profile:\n".implode("\n", $parts));
    }

    private function commitAssistantFlow(int $teamId, array $input): string
    {
        $raw = trim((string) ($input['routing_key'] ?? ''));
        if ($raw === '')
        {
            return 'Error: routing_key is required.';
        }

        $prompt = Prompt::findByRoutingKey($raw, $teamId);
        if (! $prompt || ! $prompt->is_active || $prompt->isGeneralRouter())
        {
            return 'Error: Unknown, inactive, or invalid routing_key for this team. Use the flow list from instructions or ask the user which topic they need.';
        }

        $prompt->loadMissing('module');
        $canonicalKey = $prompt->module
            ? $prompt->module->key.':'.$prompt->section_key
            : $prompt->section_key;

        $payload = [
            'routing_key' => $canonicalKey,
            'label' => $prompt->section_label,
        ];

        return 'FLOW_COMMITTED:'.json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function checkCalendarAvailability(int $teamId, array $input): string
    {
        $startRaw = (string) ($input['start'] ?? '');
        if ($startRaw === '')
        {
            return 'start is required.';
        }

        try
        {
            $start = CalendarEventDateTimeParser::parseForStorage($startRaw);
        } catch (\Throwable)
        {
            return 'Invalid start datetime format.';
        }

        $endRaw = (string) ($input['end'] ?? '');
        if ($endRaw !== '')
        {
            try
            {
                $end = CalendarEventDateTimeParser::parseForStorage($endRaw);
            } catch (\Throwable)
            {
                return 'Invalid end datetime format.';
            }
        } else
        {
            $end = (clone $start)->addHour();
        }

        if ($end->lessThanOrEqualTo($start))
        {
            return 'End must be after start.';
        }

        $busy = $this->calendarEventsOverlapping($teamId, $start, $end);

        if ($busy->isEmpty())
        {
            return $this->truncate(
                'The calendar is free between '
                .CalendarEventDateTimeParser::formatWallClock($start)
                .' and '
                .CalendarEventDateTimeParser::formatWallClock($end).'.',
            );
        }

        return $this->truncate("There are events in that range:\n".$this->formatBusyCalendarLines($busy));
    }

    private function createCalendarEvent(int $teamId, array $input): string
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '')
        {
            return 'title is required.';
        }

        $startRaw = (string) ($input['start'] ?? '');
        $endRaw = (string) ($input['end'] ?? '');
        if ($startRaw === '' || $endRaw === '')
        {
            return 'start and end are required.';
        }

        try
        {
            $start = CalendarEventDateTimeParser::parseForStorage($startRaw);
            $end = CalendarEventDateTimeParser::parseForStorage($endRaw);
        } catch (\Throwable)
        {
            return 'Invalid start or end datetime format.';
        }

        if ($end->lessThanOrEqualTo($start))
        {
            return 'End must be after start.';
        }

        $busy = $this->calendarEventsOverlapping($teamId, $start, $end);
        if ($busy->isNotEmpty())
        {
            return $this->truncate(
                "Cannot create the event: the calendar is busy in that time slot. Use check_calendar_availability or pick another time.\n"
                .$this->formatBusyCalendarLines($busy),
            );
        }

        $guestIds = [];
        $guestLinkWarning = '';
        try
        {
            $guestIds = $this->resolveGuestContactIdsForCalendarEvent($teamId, $input, $title);
        } catch (\Throwable $e)
        {
            report($e);
            $guestLinkWarning = ' Guest linking was skipped due to an internal error; the event was still created.';
        }

        $event = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'title' => $title,
            'start' => $start,
            'end' => $end,
            'all_day' => (bool) ($input['all_day'] ?? false),
            'notes' => isset($input['notes']) && $input['notes'] !== '' ? (string) $input['notes'] : null,
            'url' => isset($input['url']) && $input['url'] !== '' ? (string) $input['url'] : null,
            'label' => isset($input['label']) && $input['label'] !== '' ? (string) $input['label'] : 'Business',
            'location' => isset($input['location']) && trim((string) $input['location']) !== '' ? trim((string) $input['location']) : null,
        ]);

        if ($guestIds !== [])
        {
            try
            {
                $this->syncCalendarEventGuests($event, $teamId, $guestIds);
            } catch (\Throwable $e)
            {
                report($e);
                $guestLinkWarning = ' Guest linking failed; the event was still created.';
            }
        }

        PushCalendarEventToGoogleJob::dispatch($event->id, 'created');

        $expectedGuest = $this->calendarEventExpectsGuest($input, $title);
        $guestSummary = $guestIds !== []
            ? ' Guests linked: '.$this->formatCalendarGuestNames($event->fresh('guests')).'.'
            : ($expectedGuest
                ? ' Warning: no CRM guest was linked — pass guest_contact_ids with the contact id.'
                : '');

        return $this->truncate(
            'Calendar event created: '.$event->title.' (id: '.$event->id.') from '
            .CalendarEventDateTimeParser::formatWallClock($event->start)
            .' to '
            .CalendarEventDateTimeParser::formatWallClock($event->end).'.'
            .$guestSummary
            .$guestLinkWarning,
        );
    }

    private function listCalendarEvents(int $teamId, array $input): string
    {
        $startRaw = (string) ($input['start'] ?? '');
        if ($startRaw === '')
        {
            return 'start is required.';
        }

        try
        {
            $start = \Carbon\Carbon::parse($startRaw)->startOfDay();
        } catch (\Throwable)
        {
            return 'Invalid start date format.';
        }

        $endRaw = (string) ($input['end'] ?? '');
        if ($endRaw !== '')
        {
            try
            {
                $end = \Carbon\Carbon::parse($endRaw)->endOfDay();
            } catch (\Throwable)
            {
                return 'Invalid end date format.';
            }
        } else
        {
            $end = (clone $start)->addDays(7)->endOfDay();
        }

        if ($end->lessThan($start))
        {
            return 'End must be after start.';
        }

        $events = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->where('end', '>', $start)
            ->where('start', '<', $end)
            ->orderBy('start')
            ->get(['id', 'title', 'start', 'end']);

        if ($events->isEmpty())
        {
            return $this->truncate('No events found between '.$start->format('Y-m-d').' and '.$end->format('Y-m-d').'.');
        }

        $lines = $events->map(function (CalendarEvent $event)
        {
            return '  id '.$event->id.': '.$event->title.' — '
                .CalendarEventDateTimeParser::formatWallClock($event->start)
                .' to '
                .CalendarEventDateTimeParser::formatWallClock($event->end);
        })->implode("\n");

        return $this->truncate("Calendar events:\n".$lines);
    }

    private function updateCalendarEvent(int $teamId, array $input): string
    {
        $eventId = (int) ($input['event_id'] ?? 0);
        if ($eventId < 1)
        {
            return 'event_id is required and must be a positive integer.';
        }

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->find($eventId);

        if (! $event)
        {
            return 'Event with id '.$eventId.' not found. Use list_calendar_events to see available events.';
        }

        $updates = [];

        if (array_key_exists('title', $input) && trim((string) $input['title']) !== '')
        {
            $updates['title'] = trim((string) $input['title']);
        }
        if (array_key_exists('notes', $input))
        {
            $updates['notes'] = $input['notes'] === '' || $input['notes'] === null ? null : (string) $input['notes'];
        }
        if (array_key_exists('url', $input))
        {
            $updates['url'] = $input['url'] === '' || $input['url'] === null ? null : (string) $input['url'];
        }
        if (array_key_exists('label', $input) && trim((string) $input['label']) !== '')
        {
            $updates['label'] = trim((string) $input['label']);
        }
        if (array_key_exists('location', $input))
        {
            $updates['location'] = $input['location'] === '' || $input['location'] === null ? null : trim((string) $input['location']);
        }
        if (array_key_exists('all_day', $input))
        {
            $updates['all_day'] = (bool) $input['all_day'];
        }

        if (array_key_exists('start', $input) && trim((string) $input['start']) !== '')
        {
            try
            {
                $updates['start'] = CalendarEventDateTimeParser::parseForStorage((string) $input['start']);
            } catch (\Throwable)
            {
                return 'Invalid start datetime format.';
            }
        }
        if (array_key_exists('end', $input) && trim((string) $input['end']) !== '')
        {
            try
            {
                $updates['end'] = CalendarEventDateTimeParser::parseForStorage((string) $input['end']);
            } catch (\Throwable)
            {
                return 'Invalid end datetime format.';
            }
        }

        $newStart = $updates['start'] ?? $event->start;
        $newEnd = $updates['end'] ?? $event->end;
        if ($newEnd->lessThanOrEqualTo($newStart))
        {
            return 'End must be after start.';
        }

        if (! empty($updates))
        {
            $event->update($updates);
            PushCalendarEventToGoogleJob::dispatch($event->id, 'updated');
        }

        if (array_key_exists('guest_contact_ids', $input) || array_key_exists('guest_name', $input))
        {
            $guestIds = $this->resolveGuestContactIdsForCalendarEvent(
                $teamId,
                $input,
                (string) ($updates['title'] ?? $event->title),
            );
            $this->syncCalendarEventGuests($event, $teamId, $guestIds);
        }

        $event->refresh();

        $guestSummary = $event->guests()->exists()
            ? ' Guests: '.$this->formatCalendarGuestNames($event->load('guests')).'.'
            : '';

        return $this->truncate(
            'Event updated: '.$event->title.' (id: '.$event->id.') — '
            .CalendarEventDateTimeParser::formatWallClock($event->start)
            .' to '
            .CalendarEventDateTimeParser::formatWallClock($event->end).'.'
            .$guestSummary,
        );
    }

    private function createTicket(int $teamId, User $user, array $input): string
    {
        $team = \App\Models\Team::withoutGlobalScopes()->find($teamId);
        if (! $team || ! $team->hasModule('tickets'))
        {
            return 'Tickets module is not enabled for this team.';
        }

        if (! Gate::forUser($user)->allows('create', Ticket::class))
        {
            return 'You do not have permission to create tickets.';
        }

        $subject = trim((string) ($input['subject'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        if ($subject === '' || $description === '')
        {
            return 'subject and description are required to create a ticket.';
        }

        $priority = isset($input['priority']) && in_array($input['priority'], ['low', 'medium', 'high', 'urgent'], true)
            ? $input['priority']
            : 'medium';

        $ticket = Ticket::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => 'open',
        ]);

        return $this->truncate("Ticket created: {$ticket->subject} (id: {$ticket->id}). Priority: {$priority}. The user can view it and add replies from the Tickets section.");
    }

    private function listOpportunityStages(): string
    {
        $stages = OpportunityStage::query()->orderBy('sort_order')->get(['slug', 'name']);
        if ($stages->isEmpty())
        {
            return 'No opportunity pipeline stages are configured. Run OpportunityStageSeeder or add stages in administration.';
        }

        $lines = $stages->map(fn (OpportunityStage $s) => "  - {$s->slug}: {$s->name}")->implode("\n");

        return $this->truncate("Pipeline stages (use stage_slug in create_opportunity):\n".$lines);
    }

    private function createOpportunity(int $teamId, User $user, array $input): string
    {
        if (! Gate::forUser($user)->allows('create', Opportunity::class))
        {
            return 'You do not have permission to create opportunities.';
        }

        $team = Team::withoutGlobalScopes()->find($teamId);
        if (! $team || ! $team->hasModule('opportunities'))
        {
            return 'The opportunities module is not enabled for this team.';
        }

        $contactId = (int) ($input['contact_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($contactId < 1 || $name === '')
        {
            return 'contact_id and name are required to create an opportunity.';
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($contactId);
        if (! $contact)
        {
            return "Contact id {$contactId} was not found in this team.";
        }

        if (! Gate::forUser($user)->allows('view', $contact))
        {
            return 'You do not have permission to use that contact.';
        }

        $slug = trim((string) ($input['stage_slug'] ?? 'qualification'));
        $stage = OpportunityStage::query()->where('slug', $slug)->first()
            ?? OpportunityStage::query()->orderBy('sort_order')->first();
        if (! $stage)
        {
            return 'No pipeline stages are configured. Run the OpportunityStage seeder or add stages in administration.';
        }

        $openedAt = isset($input['opened_at'])
            ? Carbon::parse((string) $input['opened_at'])->startOfDay()
            : now()->startOfDay();

        $responsibleId = $user->id;
        if ($user->hasRole('admin') && ! empty($input['responsible_email']))
        {
            $email = trim((string) $input['responsible_email']);
            $assignee = User::query()
                ->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
                ->where('email', $email)
                ->first();
            if ($assignee)
            {
                $responsibleId = $assignee->id;
            }
        }

        $description = isset($input['description']) ? trim((string) $input['description']) : null;
        if ($description === '')
        {
            $description = null;
        }

        $offeringSummary = isset($input['offering_summary']) ? trim((string) $input['offering_summary']) : null;
        if ($offeringSummary === '')
        {
            $offeringSummary = null;
        }

        $estimatedAmount = isset($input['estimated_amount']) ? (float) $input['estimated_amount'] : null;

        $opportunity = Opportunity::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'contact_id' => $contact->id,
            'responsible_id' => $responsibleId,
            'opportunity_stage_id' => $stage->id,
            'name' => $name,
            'opened_at' => $openedAt,
            'description' => $description,
            'estimated_amount' => $estimatedAmount,
            'offering_type' => null,
            'offering_id' => null,
            'offering_summary' => $offeringSummary,
        ]);

        $url = route('opportunity.show', $opportunity->id);

        return $this->truncate("Opportunity created: «{$opportunity->name}» (id: {$opportunity->id}). Stage: {$stage->name}. Contact: {$contact->name} (id: {$contact->id}). View: {$url}");
    }

    private function addTicketResponse(int $teamId, User $user, array $input): string
    {
        $team = \App\Models\Team::withoutGlobalScopes()->find($teamId);
        if (! $team || ! $team->hasModule('tickets'))
        {
            return 'Tickets module is not enabled for this team.';
        }

        $ticketId = (int) ($input['ticket_id'] ?? 0);
        if ($ticketId < 1)
        {
            return 'ticket_id is required.';
        }

        $ticket = Ticket::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($ticketId);

        if (! $ticket)
        {
            return "Ticket with id {$ticketId} not found.";
        }

        if (! Gate::forUser($user)->allows('update', $ticket))
        {
            return 'You do not have permission to reply to this ticket.';
        }

        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '')
        {
            return 'message is required to add a reply.';
        }

        $isInternalNote = (bool) ($input['is_internal_note'] ?? false);
        if ($isInternalNote && ! $user->hasRole('admin'))
        {
            return 'Only admins can add internal notes. Use is_internal_note false for a normal reply visible to the client.';
        }

        TicketResponse::withoutGlobalScopes()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $message,
            'is_internal_note' => $isInternalNote,
        ]);

        $statusNote = '';
        if (! $isInternalNote && in_array($ticket->status, ['open', 'waiting_client'], true))
        {
            $ticket->update(['status' => 'in_progress']);
            $statusNote = ' Ticket status set to in progress.';
        } elseif (! $isInternalNote && $ticket->status === 'in_progress')
        {
            $ticket->update(['status' => 'waiting_client']);
            $statusNote = ' Ticket status set to waiting client.';
        }

        $type = $isInternalNote ? 'Internal note' : 'Reply';

        return $this->truncate("{$type} added to ticket #{$ticket->id} ({$ticket->subject}).{$statusNote}");
    }

    private function listTemplates(int $teamId): string
    {
        $templates = Template::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get(['id', 'name', 'status_id']);

        if ($templates->isEmpty())
        {
            return 'No templates yet. Use create_template to create one.';
        }

        $lines = $templates->map(fn ($t) => sprintf('  - %s (id: %d, status: %s)', $t->name, $t->id, (int) $t->status_id === 1 ? 'active' : 'suspended'))->implode("\n");

        return "Templates:\n".$lines;
    }

    private function createTemplate(int $teamId, int $userId, array $input): string
    {
        $user = User::withoutGlobalScopes()->find($userId);
        $canCreate = $user && ($user->hasRole('admin') || $user->hasRole('root') || $user->can('template.create'));
        if (! $canCreate)
        {
            return 'You do not have permission to create templates.';
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '')
        {
            return 'Template name is required.';
        }

        $aiPrompt = isset($input['ai_prompt']) ? trim((string) $input['ai_prompt']) : null;

        $template = Template::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => $name,
            'status_id' => 1,
            'gjs_data' => null,
        ]);

        $viewUrl = url()->route('template.show-public', $template->getHashedId());
        $editorUrl = url()->route('template.editor', $template->getHashedId());

        $out = "Template created: {$template->name} (id: {$template->id}). View (public link): {$viewUrl} — Editor: {$editorUrl}";
        if ($aiPrompt !== null && $aiPrompt !== '')
        {
            GenerateTemplateHtmlJob::dispatch($aiPrompt, $teamId, '', $template->id);
            $out .= '. AI generation is running in the background; the template will be updated with the generated HTML shortly. You can already open the editor to see or edit it.';
        }

        return $this->truncate($out);
    }

    private function updateTemplateStatus(int $teamId, User $user, array $input): string
    {
        $templateId = (int) ($input['template_id'] ?? 0);
        $status = trim((string) ($input['status'] ?? ''));

        if ($templateId < 1)
        {
            return 'template_id is required.';
        }
        if (! in_array($status, ['active', 'suspended'], true))
        {
            return 'status must be "active" or "suspended".';
        }

        $template = Template::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($templateId);

        if (! $template)
        {
            return "Template with id {$templateId} not found.";
        }

        if (! $user->can('template.edit'))
        {
            return 'You do not have permission to update this template.';
        }

        $template->update(['status_id' => $status === 'active' ? 1 : 0]);

        return $this->truncate("Template \"{$template->name}\" (id: {$template->id}) is now ".$status.'.');
    }

    private function updateTemplate(int $teamId, User $user, array $input): string
    {
        $templateId = (int) ($input['template_id'] ?? 0);
        if ($templateId < 1)
        {
            return 'template_id is required.';
        }

        $template = Template::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($templateId);

        if (! $template)
        {
            return "Template with id {$templateId} not found.";
        }

        if (! $user->can('template.edit'))
        {
            return 'You do not have permission to update this template.';
        }

        $name = isset($input['name']) ? trim((string) $input['name']) : null;
        if ($name === null || $name === '')
        {
            return 'No changes provided. Pass "name" to rename the template.';
        }

        $template->update(['name' => $name]);

        return $this->truncate("Template (id: {$template->id}) renamed to: {$name}. View: ".url()->route('template.show-public', $template->getHashedId()));
    }

    private function listMessages(int $teamId): string
    {
        $messages = Message::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->with(['template:id,name', 'contactCategories:id,name', 'type:id,name'])
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'name', 'type_id', 'template_id', 'status_id']);

        if ($messages->isEmpty())
        {
            return 'No campaign messages yet. Use create_message to create one (you need a template from list_templates first).';
        }

        $lines = $messages->map(function ($m)
        {
            $type = $m->type ? $m->type->name : '?';
            $tpl = $m->template ? $m->template->name : '—';
            $cat = $m->contactCategoriesLabel();
            $sending = (int) $m->status_id === 1 ? 'sending: enabled' : 'sending: paused';

            return sprintf('  - %s (id: %d, channel: %s, template: %s, categories: %s, %s)', $m->name, $m->id, $type, $tpl, $cat, $sending);
        })->implode("\n");

        return "Campaign messages (News):\n".$lines;
    }

    private function createMessage(int $teamId, User $user, array $input): string
    {
        $canCreate = $user->hasRole('admin') || $user->hasRole('root') || $user->can('message.create');
        if (! $canCreate)
        {
            return 'You do not have permission to create campaign messages.';
        }

        $name = trim((string) ($input['name'] ?? ''));
        if (mb_strlen($name) < 3)
        {
            return 'Campaign name is required (at least 3 characters).';
        }

        $templateId = (int) ($input['template_id'] ?? 0);
        if ($templateId < 1)
        {
            return 'template_id is required (use list_templates to get an ID).';
        }

        $template = Template::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($templateId);
        if (! $template)
        {
            return "Template with id {$templateId} not found in this team.";
        }

        $channel = strtolower(trim((string) ($input['channel'] ?? '')));
        if (! in_array($channel, ['email', 'whatsapp'], true))
        {
            return 'channel must be "email" or "whatsapp".';
        }
        $typeId = $channel === 'whatsapp' ? 2 : 1;

        $text = trim((string) ($input['text'] ?? ''));
        if (mb_strlen($text) < 3)
        {
            return 'Alternative text is required (at least 3 characters).';
        }
        if (mb_strlen($text) > 255)
        {
            $text = mb_substr($text, 0, 255);
        }

        $categoryResolution = $this->resolveContactCategoryIdsFromInput($teamId, $input);
        if ($categoryResolution['error'] !== null)
        {
            return $categoryResolution['error'];
        }

        $contactStatusId = null;
        $contactStatusName = isset($input['contact_status_name']) ? trim((string) $input['contact_status_name']) : null;
        if ($contactStatusName !== null && $contactStatusName !== '')
        {
            $contactStatus = ContactStatus::where('name', $contactStatusName)->first();
            if (! $contactStatus)
            {
                $contactStatus = ContactStatus::whereRaw('LOWER(name) = ?', [strtolower($contactStatusName)])->first();
            }
            if ($contactStatus)
            {
                $contactStatusId = $contactStatus->id;
            }
        }

        if ($contactStatusName !== null && $contactStatusName !== '' && $contactStatusId === null)
        {
            return "CRM contact status \"{$contactStatusName}\" not found. Use list_contact_statuses for exact names (Lead, En seguimiento, Conversión, Perdido, Cliente, Finalizado, etc.).";
        }

        $active = ! empty($input['active']);
        $statusId = $active ? 1 : 0;

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => $name,
            'type_id' => $typeId,
            'contact_status_id' => $contactStatusId,
            'template_id' => $templateId,
            'text' => $text,
            'status_id' => $statusId,
            'show_unsubscribe' => 1,
            'enable_open_tracking' => 1,
            'enable_click_tracking' => 1,
            'min_hours_between_emails' => 48,
        ]);

        $message->syncMessageCategories($categoryResolution['ids']);

        $editUrl = url()->route('message.edit', $message->id);
        $showUrl = url()->route('message.show', $message->id);

        $out = "Campaign message created: {$message->name} (id: {$message->id}). Channel: {$channel}. Template: {$template->name}.";
        if ($categoryResolution['ids'] !== [])
        {
            $message->load('contactCategories');
            $out .= ' Audience: categories "'.$message->contactCategoriesLabel().'".';
        }
        if ($contactStatusId)
        {
            $out .= " Audience filter (CRM contact status): {$contactStatusName}.";
        }
        $out .= ' '.($active
            ? 'Campaign sending is ON (deliveries allowed per schedule/rules).'
            : 'Campaign sending is OFF (no deliveries yet); enable sending in Messages/News when ready.');
        $out .= " Edit: {$editUrl} — View/send: {$showUrl}";

        $suffix = "\n".AssistantCreatedMessageRedirect::SENTINEL_LINE_PREFIX.$message->id;
        $suffixLen = mb_strlen($suffix);
        $maxMain = max(0, self::MAX_TOOL_RESULT_LENGTH - $suffixLen);
        if (mb_strlen($out) > $maxMain)
        {
            $out = mb_substr($out, 0, max(0, $maxMain - 20))."\n...(truncated)";
        }

        return $out.$suffix;
    }

    private function updateMessageStatus(int $teamId, User $user, array $input): string
    {
        $messageId = (int) ($input['message_id'] ?? 0);
        if ($messageId < 1)
        {
            return 'message_id is required (use list_messages to get an ID).';
        }

        $status = strtolower(trim((string) ($input['status'] ?? '')));
        if (! in_array($status, ['active', 'paused'], true))
        {
            return 'status must be "active" or "paused".';
        }

        $message = Message::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($messageId);

        if (! $message)
        {
            return "Campaign message with id {$messageId} not found.";
        }

        $canEdit = $user->hasRole('admin') || $user->hasRole('root') || $user->can('message.edit');
        if (! $canEdit)
        {
            return 'You do not have permission to change this campaign status.';
        }

        $message->update(['status_id' => $status === 'active' ? 1 : 0]);

        $label = $status === 'active' ? 'sending enabled' : 'sending paused (deliveries stopped)';

        return $this->truncate("Campaign \"{$message->name}\" (id: {$message->id}): {$label}.");
    }

    private function updateMessage(int $teamId, User $user, array $input): string
    {
        $messageId = (int) ($input['message_id'] ?? 0);
        if ($messageId < 1)
        {
            return 'message_id is required (use list_messages to get the ID of the campaign to update).';
        }

        $message = Message::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->find($messageId);

        if (! $message)
        {
            return "Campaign message with id {$messageId} not found.";
        }

        $canEdit = $user->hasRole('admin') || $user->hasRole('root') || $user->can('message.edit');
        if (! $canEdit)
        {
            return 'You do not have permission to update this campaign.';
        }

        $hasDeliveries = $message->deliveries()->exists();
        $updates = [];
        $syncedCategoryIds = null;

        if ($this->wantsContactCategorySync($input))
        {
            if ($hasDeliveries)
            {
                return 'Cannot change audience (category) because this campaign already has deliveries. You can only change campaign sending (enabled vs paused).';
            }
            $categoryResolution = $this->resolveContactCategoryIdsFromInput($teamId, $input);
            if ($categoryResolution['error'] !== null)
            {
                return $categoryResolution['error'];
            }
            $syncedCategoryIds = $categoryResolution['ids'];
        }

        $contactStatusName = isset($input['contact_status_name']) ? trim((string) $input['contact_status_name']) : null;
        if ($contactStatusName !== null && $contactStatusName !== '')
        {
            if ($hasDeliveries)
            {
                return 'Cannot change the CRM contact-status audience filter because this campaign already has deliveries. You can only change campaign sending (enabled vs paused).';
            }
            $contactStatus = ContactStatus::where('name', $contactStatusName)->first()
                ?? ContactStatus::whereRaw('LOWER(name) = ?', [strtolower($contactStatusName)])->first();
            if (! $contactStatus)
            {
                return "CRM contact status \"{$contactStatusName}\" not found. Use list_contact_statuses for exact names.";
            }
            $updates['contact_status_id'] = $contactStatus->id;
        }

        $status = isset($input['status']) ? strtolower(trim((string) $input['status'])) : null;
        if ($status !== null && $status !== '')
        {
            if (! in_array($status, ['active', 'paused'], true))
            {
                return 'status must be "active" (sending on) or "paused" (sending off).';
            }
            $updates['status_id'] = $status === 'active' ? 1 : 0;
        }

        if ($updates === [] && $syncedCategoryIds === null)
        {
            return 'No changes provided. Pass at least one of: category_name, category_names, contact_status_name, status (campaign sending: active or paused).';
        }

        if ($updates !== [])
        {
            $message->update($updates);
        }

        if ($syncedCategoryIds !== null)
        {
            $message->syncMessageCategories($syncedCategoryIds);
        }

        $parts = ['Campaign "'.$message->name.'" (id: '.$message->id.') updated.'];
        if ($syncedCategoryIds !== null)
        {
            $message->load('contactCategories');
            $parts[] = 'Audience: categories "'.$message->contactCategoriesLabel().'".';
        }
        if (isset($updates['contact_status_id']))
        {
            $parts[] = "Audience filter (CRM contact status): {$contactStatusName}.";
        }
        if (isset($updates['status_id']))
        {
            $parts[] = 'Campaign sending: '.(($updates['status_id'] ?? 0) === 1 ? 'enabled.' : 'paused.');
        }
        $parts[] = 'Edit: '.url()->route('message.edit', $message->id).' — View/send: '.url()->route('message.show', $message->id);

        return $this->truncate(implode(' ', $parts));
    }

    private function wantsContactCategorySync(array $input): bool
    {
        return array_key_exists('category_name', $input) || array_key_exists('category_names', $input);
    }

    /**
     * @return array{ids: list<int>, error: ?string}
     */
    private function resolveContactCategoryIdsFromInput(int $teamId, array $input): array
    {
        $names = [];
        if (isset($input['category_names']) && is_array($input['category_names']))
        {
            foreach ($input['category_names'] as $name)
            {
                $trimmed = trim((string) $name);
                if ($trimmed !== '')
                {
                    $names[] = $trimmed;
                }
            }
        }

        $singleName = isset($input['category_name']) ? trim((string) $input['category_name']) : '';
        if ($singleName !== '')
        {
            $names[] = $singleName;
        }

        if ($names === [])
        {
            return ['ids' => [], 'error' => null];
        }

        $ids = [];
        foreach (array_unique($names) as $categoryName)
        {
            $categoryId = $this->resolveContactCategoryByName($teamId, $categoryName);
            if ($categoryId === null)
            {
                return [
                    'ids' => [],
                    'error' => "Contact category \"{$categoryName}\" not found. Use list_contact_categories to see available categories.",
                ];
            }
            $ids[] = $categoryId;
        }

        return ['ids' => array_values(array_unique($ids)), 'error' => null];
    }

    private function resolveContactCategoryByName(int $teamId, string $categoryName): ?int
    {
        $module = Module::where('key', 'contacts')->first();
        if (! $module)
        {
            return null;
        }

        $category = Category::withoutGlobalScopes()
            ->where('module_id', $module->id)
            ->where('team_id', $teamId)
            ->where('name', $categoryName)
            ->first();

        return $category?->id;
    }

    /**
     * @return array{birthday?: string, data?: object}
     */
    private function buildContactOptionalAttributes(array $input): array
    {
        $out = [];

        if (array_key_exists('birthday', $input))
        {
            $birthdayRaw = trim((string) $input['birthday']);
            if ($birthdayRaw !== '')
            {
                try
                {
                    $out['birthday'] = Carbon::parse($birthdayRaw)->format('Y-m-d');
                } catch (\Throwable)
                {
                    // Ignored; caller may validate elsewhere if needed.
                }
            }
        }

        if (array_key_exists('notes', $input))
        {
            $notes = trim((string) $input['notes']);
            if ($notes !== '')
            {
                $out['data'] = (object) ['notes' => $notes];
            }
        }

        return $out;
    }

    private function applyContactOptionalFields(Contact $contact, array $input): void
    {
        $optional = $this->buildContactOptionalAttributes($input);
        if ($optional === [])
        {
            return;
        }

        $updates = [];
        if (isset($optional['birthday']))
        {
            $updates['birthday'] = $optional['birthday'];
        }

        if (isset($optional['data']))
        {
            $data = (array) ($contact->data ?? []);
            $incoming = (array) $optional['data'];
            if (isset($incoming['notes']))
            {
                $data['notes'] = $incoming['notes'];
            }
            $updates['data'] = (object) $data;
        }

        if ($updates !== [])
        {
            $contact->update($updates);
        }
    }

    private function attachContactToCategoryName(Contact $contact, int $teamId, ?string $categoryName): ?int
    {
        if ($categoryName === null || trim($categoryName) === '')
        {
            return null;
        }

        $categoryId = $this->resolveOrCreateContactCategory($teamId, $categoryName);
        if ($categoryId)
        {
            $contact->categories()->syncWithoutDetaching([$categoryId]);
        }

        return $categoryId;
    }

    /**
     * @return \Illuminate\Support\Collection<int, CalendarEvent>
     */
    private function calendarEventsOverlapping(int $teamId, \Carbon\Carbon $start, \Carbon\Carbon $end): \Illuminate\Support\Collection
    {
        return CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($start, $end)
            {
                $q->whereBetween('start', [$start, $end])
                    ->orWhereBetween('end', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end)
                    {
                        $inner->where('start', '<=', $start)->where('end', '>=', $end);
                    });
            })
            ->orderBy('start')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CalendarEvent>  $busy
     */
    private function formatBusyCalendarLines(\Illuminate\Support\Collection $busy): string
    {
        return $busy->map(function (CalendarEvent $event)
        {
            return '- '.$event->title.' ('
                .CalendarEventDateTimeParser::formatWallClock($event->start)
                .' → '
                .CalendarEventDateTimeParser::formatWallClock($event->end).')';
        })->implode("\n");
    }

    private function registerRecentContactId(int $contactId): void
    {
        if ($contactId < 1)
        {
            return;
        }

        if (! in_array($contactId, $this->recentContactIdsInRequest, true))
        {
            $this->recentContactIdsInRequest[] = $contactId;
        }
    }

    /**
     * @return list<int>
     */
    private function normalizeGuestContactIds(array $input): array
    {
        $ids = [];

        if (isset($input['guest_contact_ids']) && is_array($input['guest_contact_ids']))
        {
            $ids = array_merge($ids, $input['guest_contact_ids']);
        }

        if (isset($input['contact_id']) && (int) $input['contact_id'] > 0)
        {
            $ids[] = (int) $input['contact_id'];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function resolveGuestContactIdsForCalendarEvent(int $teamId, array $input, string $title): array
    {
        $ids = $this->normalizeGuestContactIds($input);

        if (isset($input['guest_name']) && is_string($input['guest_name']))
        {
            $ids = array_merge($ids, $this->findContactIdsByGuestName($teamId, $input['guest_name']));
        }

        if (isset($input['guest_names']) && is_array($input['guest_names']))
        {
            foreach ($input['guest_names'] as $guestName)
            {
                if (is_string($guestName))
                {
                    $ids = array_merge($ids, $this->findContactIdsByGuestName($teamId, $guestName));
                }
            }
        }

        $guestFromTitle = $this->extractGuestNameFromEventTitle($title);
        if ($guestFromTitle !== null)
        {
            $ids = array_merge($ids, $this->findContactIdsByGuestName($teamId, $guestFromTitle));
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        if ($ids !== [])
        {
            return $ids;
        }

        if (count($this->recentContactIdsInRequest) === 1)
        {
            return [$this->recentContactIdsInRequest[0]];
        }

        if ($this->recentContactIdsInRequest !== [])
        {
            $ids = $this->matchRecentContactsToEventTitle($teamId, $title);
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
    }

    private function calendarEventExpectsGuest(array $input, string $title): bool
    {
        if (isset($input['guest_name']) && trim((string) $input['guest_name']) !== '')
        {
            return true;
        }

        if ($this->extractGuestNameFromEventTitle($title) !== null)
        {
            return true;
        }

        return $this->recentContactIdsInRequest !== [];
    }

    private function extractGuestNameFromEventTitle(string $title): ?string
    {
        $title = trim($title);
        if ($title === '')
        {
            return null;
        }

        if (preg_match('/\b(?:con|with)\s+(.+)$/iu', $title, $matches))
        {
            $name = trim($matches[1]);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function findContactIdsByGuestName(int $teamId, string $guestName): array
    {
        return app(TeamContactMatcher::class)->findIdsByName($teamId, $guestName);
    }

    /**
     * @return list<int>
     */
    private function matchRecentContactsToEventTitle(int $teamId, string $title): array
    {
        $titleLower = mb_strtolower(trim($title));
        if ($titleLower === '')
        {
            return [];
        }

        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereIn('id', $this->recentContactIdsInRequest)
            ->get(['id', 'name', 'surname']);

        $matched = $contacts->filter(function (Contact $contact) use ($titleLower)
        {
            $full = mb_strtolower(trim($contact->name.' '.($contact->surname ?? '')));
            $first = mb_strtolower(trim((string) $contact->name));

            return $full !== '' && (str_contains($titleLower, $full) || ($first !== '' && str_contains($titleLower, $first)));
        })->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return count($matched) === 1 ? $matched : [];
    }

    /**
     * @param  list<int>  $contactIds
     */
    private function syncCalendarEventGuests(CalendarEvent $event, int $teamId, array $contactIds): void
    {
        $validIds = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereIn('id', $contactIds)
            ->pluck('id')
            ->all();

        $event->guests()->sync($validIds);
    }

    private function formatCalendarGuestNames(CalendarEvent $event): string
    {
        if (! $event->relationLoaded('guests'))
        {
            $event->load('guests:id,name,surname');
        }

        return $event->guests
            ->map(fn (Contact $contact): string => trim($contact->name.' '.($contact->surname ?? '')))
            ->filter(fn (string $name): bool => $name !== '')
            ->implode(', ');
    }

    private function resolveOrCreateContactCategory(int $teamId, string $categoryName): ?int
    {
        $module = Module::where('key', 'contacts')->first();
        if (! $module)
        {
            return null;
        }

        $normalizedName = mb_strtolower(trim($categoryName));

        $category = Category::withoutGlobalScopes()
            ->where('module_id', $module->id)
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->first();

        if ($category)
        {
            return $category->id;
        }

        $category = Category::withoutGlobalScopes()->create([
            'name' => trim($categoryName),
            'module_id' => $module->id,
            'team_id' => $teamId,
            'status' => true,
        ]);

        return $category->id;
    }

    private function teamHasProductsModule(int $teamId): bool
    {
        $team = \App\Models\Team::withoutGlobalScopes()->find($teamId);

        return $team !== null && $team->hasModule('products');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Product>
     */
    private function whatsAppSellableProductsQuery(int $teamId): \Illuminate\Database\Eloquent\Builder
    {
        return Product::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->active()
            ->whatsAppEnabled();
    }

    private function resolveWhatsAppProduct(int $teamId, array $input): ?Product
    {
        $id = isset($input['product_id']) ? (int) $input['product_id'] : 0;
        if ($id > 0)
        {
            return $this->whatsAppSellableProductsQuery($teamId)->where('id', $id)->first();
        }

        $code = isset($input['product_code']) ? trim((string) $input['product_code']) : '';
        if ($code !== '')
        {
            return $this->whatsAppSellableProductsQuery($teamId)
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
                ->first();
        }

        $name = isset($input['product_name']) ? trim((string) $input['product_name']) : '';
        if ($name !== '')
        {
            return $this->whatsAppSellableProductsQuery($teamId)
                ->where('name', 'LIKE', '%'.$name.'%')
                ->first();
        }

        return null;
    }

    private function listProductCatalog(int $teamId, array $input): string
    {
        if (! $this->teamHasProductsModule($teamId))
        {
            return 'The products module is not enabled for this team.';
        }

        $module = Module::where('key', 'products')->first();
        if (! $module)
        {
            return 'Products module is not installed.';
        }

        $query = $this->whatsAppSellableProductsQuery($teamId)
            ->with(['category:id,name', 'currency:id,symbol', 'store:id,name']);

        $categoryFilter = isset($input['category_name']) ? trim((string) $input['category_name']) : '';
        if ($categoryFilter !== '')
        {
            $query->whereHas('category', function ($q) use ($categoryFilter): void
            {
                $q->where('name', 'LIKE', '%'.$categoryFilter.'%');
            });
        }

        $products = $query->orderBy('category_id')->orderBy('name')->limit(50)->get();

        if ($products->isEmpty())
        {
            return 'No WhatsApp-enabled products found'.($categoryFilter !== '' ? ' for that category filter.' : '.').' Enable products for WhatsApp in the catalog or adjust the filter.';
        }

        $lines = [];
        foreach ($products->groupBy(fn (Product $p) => $p->category?->name ?? 'Sin categoría') as $categoryName => $group)
        {
            $lines[] = 'Category: '.$categoryName;
            foreach ($group as $product)
            {
                $symbol = $product->currency?->symbol ?? '$';
                $price = number_format($product->currentSellingPrice(), 2);
                $codePart = $product->code ? ' code '.$product->code.',' : '';
                $storePart = $product->store ? ' store '.($product->store->name).',' : '';
                $lines[] = sprintf(
                    '  id %d:%s%s %s — %s%s',
                    $product->id,
                    $codePart,
                    $storePart,
                    $product->name,
                    $symbol,
                    $price,
                );
            }
            $lines[] = '';
        }

        $lines[] = 'To buy from WhatsApp: use add_to_whatsapp_cart with product_id or product_code, or tell the customer they can write comprar plus the product name or code, or agregar plus quantity and name (e.g. agregar 2 panes). Then carrito to review, quitar with quantity and name or quitar todo plus name to remove, finalizar to close the order (pagar/cerrar pedido/checkout also work in the bot; suggest only finalizar to the customer).';

        return $this->truncate(implode("\n", $lines));
    }

    private function searchProducts(int $teamId, array $input): string
    {
        if (! $this->teamHasProductsModule($teamId))
        {
            return 'The products module is not enabled for this team.';
        }

        $raw = trim((string) ($input['query'] ?? ''));
        if ($raw === '')
        {
            return 'query is required (product name or code).';
        }

        $products = $this->whatsAppSellableProductsQuery($teamId)
            ->with(['category:id,name', 'currency:id,symbol'])
            ->where(function ($q) use ($raw): void
            {
                $q->where('name', 'LIKE', '%'.$raw.'%')
                    ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($raw)])
                    ->orWhere('code', 'LIKE', '%'.$raw.'%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        if ($products->isEmpty())
        {
            return 'No matching WhatsApp-enabled products for: '.$raw.'. Try list_product_catalog or a shorter name.';
        }

        $lines = $products->map(function (Product $product)
        {
            $symbol = $product->currency?->symbol ?? '$';
            $price = number_format($product->currentSellingPrice(), 2);
            $code = $product->code ? $product->code : '—';

            return sprintf(
                'id %d | code %s | %s | %s%s | category: %s',
                $product->id,
                $code,
                $product->name,
                $symbol,
                $price,
                $product->category->name ?? '—',
            );
        })->implode("\n");

        return $this->truncate("Matches:\n".$lines);
    }

    private function addToWhatsAppCart(int $teamId, array $input): string
    {
        if (! $this->teamHasProductsModule($teamId))
        {
            return 'The products module is not enabled for this team.';
        }

        if ($this->contextCustomerPhone === null || $this->contextCustomerPhone === '')
        {
            return 'Cannot attach to a WhatsApp cart: no customer phone in this session. Tell the customer to write from WhatsApp, or use natural phrases: comprar plus name or code, agregar quantity and name, carrito, quitar quantity and name or quitar todo name, finalizar. If you are in the web assistant with a recipient phone, open the assistant with that recipient selected.';
        }

        $product = $this->resolveWhatsAppProduct($teamId, $input);
        if (! $product)
        {
            return 'Product not found or not available on WhatsApp. Use search_products or pass product_id / product_code / product_name (one required).';
        }

        $product->loadMissing(['category', 'currency']);

        $quantity = isset($input['quantity']) ? max(1, (int) $input['quantity']) : 1;

        Cart::session($this->contextCustomerPhone);
        $cartItems = Cart::getContent();
        $existingItem = $cartItems->where('id', $product->id)->first();

        if ($existingItem)
        {
            Cart::update($product->id, [
                'quantity' => [
                    'relative' => false,
                    'value' => (int) $existingItem->quantity + $quantity,
                ],
            ]);
            $newQty = (int) $existingItem->quantity + $quantity;
        } else
        {
            Cart::add([
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->currentSellingPrice(),
                'quantity' => $quantity,
                'attributes' => [
                    'team_id' => $teamId,
                    'store_id' => $product->store_id,
                    'currency_id' => $product->currency_id,
                    'description' => $product->description,
                    'category_name' => $product->category->name ?? '',
                ],
            ]);
            $newQty = $quantity;
        }

        $symbol = $product->currency?->symbol ?? '$';
        $total = Cart::getTotal();

        $msg = "Added to WhatsApp cart for this customer: {$product->name} (id {$product->id}) x{$newQty} at {$symbol}".number_format($product->currentSellingPrice(), 2).'. ';
        $msg .= 'Cart total: '.$symbol.number_format($total, 2).'. ';
        $msg .= 'Tell them they can write *carrito* to review, *quitar* with quantity and product to remove units, or *finalizar* to finish the order.';

        return $this->truncate($msg);
    }

    private function truncate(string $s): string
    {
        if (mb_strlen($s) <= self::MAX_TOOL_RESULT_LENGTH)
        {
            return $s;
        }

        return mb_substr($s, 0, self::MAX_TOOL_RESULT_LENGTH - 20)."\n...(truncated)";
    }
}
