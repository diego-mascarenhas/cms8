<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Jobs\GenerateTemplateHtmlJob;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\Module;
use App\Models\Product;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\Template;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

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

    public function __construct(
        protected ?WhatsAppGateway $whatsAppGateway = null,
    ) {}

    /**
     * Reset tool execution context (avoid leaking phone/team between requests; service may be singleton).
     */
    public function clearRequestContext(): void
    {
        $this->contextUserId = null;
        $this->contextTeamId = null;
        $this->contextCustomerPhone = null;
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
            ? preg_replace('/[^0-9]/', '', $customerPhoneDigits)
            : null;
    }

    /**
     * Resolve the gateway for assistant tool sends. Local driver must use the team's Baileys base URL and team_id
     * so outbound messages go through the correct socket (never the global container binding without team_id).
     */
    private function resolveWhatsAppGatewayForToolSend(): WhatsAppGateway
    {
        if ($this->whatsAppGateway !== null)
        {
            return $this->whatsAppGateway;
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
                'name' => 'create_contact',
                'description' => 'Create a new contact. Provide at least name; optionally email, phone, and category name (creates the category if it does not exist).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Full name of the contact'],
                        'email' => ['type' => 'string', 'description' => 'Email address (optional)'],
                        'phone' => ['type' => 'string', 'description' => 'Phone number (optional)'],
                        'category_name' => ['type' => 'string', 'description' => 'Category name to assign; created if missing (optional)'],
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
                'description' => 'Update an existing contact. Provide contact_id and any of: phone, email, name. Use this to add or change the phone number, email, or name of a contact.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Contact ID to update'],
                        'phone' => ['type' => 'string', 'description' => 'Phone number (with country code, digits only). Optional.'],
                        'email' => ['type' => 'string', 'description' => 'Email address. Optional.'],
                        'name' => ['type' => 'string', 'description' => 'Full name. Optional.'],
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
                'description' => 'Create an event in the team calendar. REQUIRED: pass start and end in Y-m-d H:i or ISO 8601. When the user says "today", "hoy", or "ahora", you MUST use the actual current date (YYYY-MM-DD) for that day — e.g. if today is 2026-03-16 and they say "hoy a las 15", use start 2026-03-16 15:00:00. Never use a different year or date unless the user explicitly states it.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Event title'],
                        'start' => ['type' => 'string', 'description' => 'Start datetime: Y-m-d H:i:s or ISO 8601. For "today"/"hoy" use the real current date (e.g. 2026-03-16).'],
                        'end' => ['type' => 'string', 'description' => 'End datetime: Y-m-d H:i:s or ISO 8601. Same date as start when user says "today"/"hoy" unless they specify duration.'],
                        'notes' => ['type' => 'string', 'description' => 'Optional notes'],
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
                'description' => 'Update an existing calendar event. Use list_calendar_events first to get the event id when the user says "modifica el evento X" or "cambia la reunión de hoy". Pass event_id and only the fields to change (title, start, end, notes, url, label, all_day).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => ['type' => 'integer', 'description' => 'ID of the event to update (from list_calendar_events).'],
                        'title' => ['type' => 'string', 'description' => 'New title (optional).'],
                        'start' => ['type' => 'string', 'description' => 'New start datetime Y-m-d H:i or ISO 8601 (optional).'],
                        'end' => ['type' => 'string', 'description' => 'New end datetime (optional).'],
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
                'description' => 'Create a NEW campaign message (News) only. Use ONLY when the user explicitly asks to create a new campaign ("crear mensaje", "crear campaña", "crear News"). Do NOT use for changing an existing campaign — use update_message or update_message_status instead. Provide name, template_id (from list_templates), channel (email or whatsapp), text (alternative text, required). Optionally: category_name, contact_status_name, active.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Campaign/News name'],
                        'template_id' => ['type' => 'integer', 'description' => 'Template ID (from list_templates)'],
                        'channel' => [
                            'type' => 'string',
                            'description' => 'Channel: email or whatsapp',
                            'enum' => ['email', 'whatsapp'],
                        ],
                        'text' => ['type' => 'string', 'description' => 'Alternative text (for WhatsApp or email fallback); required'],
                        'category_name' => ['type' => 'string', 'description' => 'Contact category name as target audience (optional; use list_contact_categories to see names)'],
                        'contact_status_name' => ['type' => 'string', 'description' => 'Contact status name, e.g. "En seguimiento" (optional)'],
                        'active' => ['type' => 'boolean', 'description' => 'If true, campaign is created active (default false)'],
                    ],
                    'required' => ['name', 'template_id', 'channel', 'text'],
                ],
            ],
            [
                'name' => 'update_message_status',
                'description' => 'Activate or pause a campaign message. Use when the user says "detener la campaña X", "pausar mensaje", "activar la campaña Y", "parar el mensaje". Pass message_id (from list_messages) and status: active or paused. Paused campaigns stop sending; active campaigns can send.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'integer', 'description' => 'Campaign message ID (from list_messages)'],
                        'status' => [
                            'type' => 'string',
                            'description' => 'active or paused',
                            'enum' => ['active', 'paused'],
                        ],
                    ],
                    'required' => ['message_id', 'status'],
                ],
            ],
            [
                'name' => 'update_message',
                'description' => 'Update an EXISTING campaign message (category, contact status, active/paused). Use when the user asks to change the current/last campaign: "enviar a categoría Staff y activarlo", "cambiar la campaña a categoría X", "activar la campaña", "poner la campaña en categoría Y". Do NOT use create_message for these — use list_messages to get message_id, then update_message. Pass message_id (required); optionally category_name, contact_status_name, status (active or paused).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'integer', 'description' => 'Campaign message ID to update (from list_messages)'],
                        'category_name' => ['type' => 'string', 'description' => 'Contact category name as new audience (optional)'],
                        'contact_status_name' => ['type' => 'string', 'description' => 'Contact status name, e.g. "En seguimiento" (optional)'],
                        'status' => [
                            'type' => 'string',
                            'description' => 'active or paused (optional)',
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

        try
        {
            return match ($name)
            {
                'list_contact_categories' => $this->listContactCategories($teamId),
                'get_contact_categories' => $this->getContactCategories($teamId, $input),
                'create_contact' => $this->createContact($teamId, $user, $input),
                'assign_contact_to_category' => $this->assignContactToCategory($teamId, $input),
                'update_contact' => $this->updateContact($teamId, $input),
                'get_account_report' => $this->getAccountReport($teamId, $input),
                'send_whatsapp_message' => $this->sendWhatsAppMessage($user, $input),
                'create_task' => $this->createTask($teamId, $user->id, $input),
                'list_team_users' => $this->listTeamUsers($teamId),
                'get_my_profile' => $this->getMyProfile($user, $teamId),
                'check_calendar_availability' => $this->checkCalendarAvailability($teamId, $input),
                'create_calendar_event' => $this->createCalendarEvent($teamId, $input),
                'list_calendar_events' => $this->listCalendarEvents($teamId, $input),
                'update_calendar_event' => $this->updateCalendarEvent($teamId, $input),
                'create_ticket' => $this->createTicket($teamId, $user->id, $input),
                'add_ticket_response' => $this->addTicketResponse($teamId, $user->id, $input),
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
        } catch (\Throwable $e)
        {
            return 'Error: '.$e->getMessage();
        }
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

    private function createContact(int $teamId, User $user, array $input): string
    {
        if (! Gate::forUser($user)->allows('create', Contact::class))
        {
            return 'You do not have permission to create contacts.';
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '')
        {
            return 'Name is required to create a contact.';
        }

        $email = isset($input['email']) ? trim((string) $input['email']) : null;
        $phone = isset($input['phone']) ? preg_replace('/[^0-9]/', '', (string) $input['phone']) : null;
        $phone = $phone !== '' ? (int) $phone : null;
        $categoryName = isset($input['category_name']) ? trim((string) $input['category_name']) : null;

        if (! $email && ! $phone)
        {
            $email = null;
            $phone = null;
        }

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'name' => $name,
            'surname' => null,
            'email' => $email,
            'phone' => $phone,
            'status_id' => 1,
        ]);

        $categoryId = null;
        if ($categoryName !== null && $categoryName !== '')
        {
            $categoryId = $this->resolveOrCreateContactCategory($teamId, $categoryName);
            if ($categoryId)
            {
                $contact->categories()->attach($categoryId);
            }
        }

        $out = "Contact created: {$contact->name} (id: {$contact->id}).";
        if ($categoryId)
        {
            $out .= " Assigned to category: {$categoryName}.";
        }

        return $this->truncate($out);
    }

    private function assignContactToCategory(int $teamId, array $input): string
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

        if (! Gate::allows('update', $contact))
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

    private function getContactCategories(int $teamId, array $input): string
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

        if (! Gate::allows('view', $contact))
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

    private function updateContact(int $teamId, array $input): string
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

        if (! Gate::allows('update', $contact))
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
            $updates['name'] = trim((string) $input['name']);
        }

        if (empty($updates))
        {
            return 'Provide at least one field to update: phone, email, or name.';
        }

        $contact->update($updates);
        $updated = array_keys($updates);

        return $this->truncate("Contact {$contact->name} (id: {$contact->id}) updated: ".implode(', ', $updated).'.');
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
            if ($phone !== $this->contextCustomerPhone)
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

        $gateway->sendMessage($phone, $message, null, $user->id);

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

    private function checkCalendarAvailability(int $teamId, array $input): string
    {
        $startRaw = (string) ($input['start'] ?? '');
        if ($startRaw === '')
        {
            return 'start is required.';
        }

        try
        {
            $start = \Carbon\Carbon::parse($startRaw);
        } catch (\Throwable)
        {
            return 'Invalid start datetime format.';
        }

        $endRaw = (string) ($input['end'] ?? '');
        if ($endRaw !== '')
        {
            try
            {
                $end = \Carbon\Carbon::parse($endRaw);
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

        $busy = CalendarEvent::withoutGlobalScopes()
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

        if ($busy->isEmpty())
        {
            return $this->truncate('The calendar is free between '.$start->toDateTimeString().' and '.$end->toDateTimeString().'.');
        }

        $lines = $busy->map(function (CalendarEvent $event)
        {
            return '- '.$event->title.' ('.$event->start?->format('Y-m-d H:i').' → '.$event->end?->format('Y-m-d H:i').')';
        })->implode("\n");

        return $this->truncate("There are events in that range:\n".$lines);
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
            $start = \Carbon\Carbon::parse($startRaw);
            $end = \Carbon\Carbon::parse($endRaw);
        } catch (\Throwable)
        {
            return 'Invalid start or end datetime format.';
        }

        if ($end->lessThanOrEqualTo($start))
        {
            return 'End must be after start.';
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

        return $this->truncate(
            'Calendar event created: '.$event->title.' (id: '.$event->id.') from '.$event->start?->format('Y-m-d H:i').' to '.$event->end?->format('Y-m-d H:i').'.',
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
            return '  id '.$event->id.': '.$event->title.' — '.$event->start?->format('Y-m-d H:i').' to '.$event->end?->format('Y-m-d H:i');
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
                $updates['start'] = \Carbon\Carbon::parse($input['start']);
            } catch (\Throwable)
            {
                return 'Invalid start datetime format.';
            }
        }
        if (array_key_exists('end', $input) && trim((string) $input['end']) !== '')
        {
            try
            {
                $updates['end'] = \Carbon\Carbon::parse($input['end']);
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
        }

        return $this->truncate(
            'Event updated: '.$event->title.' (id: '.$event->id.') — '.$event->start?->format('Y-m-d H:i').' to '.$event->end?->format('Y-m-d H:i').'.',
        );
    }

    private function createTicket(int $teamId, int $userId, array $input): string
    {
        $team = \App\Models\Team::withoutGlobalScopes()->find($teamId);
        if (! $team || ! $team->hasModule('tickets'))
        {
            return 'Tickets module is not enabled for this team.';
        }

        if (! Gate::allows('create', Ticket::class))
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
            'user_id' => $userId,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => 'open',
        ]);

        return $this->truncate("Ticket created: {$ticket->subject} (id: {$ticket->id}). Priority: {$priority}. The user can view it and add replies from the Tickets section.");
    }

    private function addTicketResponse(int $teamId, int $userId, array $input): string
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

        if (! Gate::allows('update', $ticket))
        {
            return 'You do not have permission to reply to this ticket.';
        }

        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '')
        {
            return 'message is required to add a reply.';
        }

        $isInternalNote = (bool) ($input['is_internal_note'] ?? false);
        $user = User::withoutGlobalScopes()->find($userId);
        if ($isInternalNote && (! $user || ! $user->hasRole('admin')))
        {
            return 'Only admins can add internal notes. Use is_internal_note false for a normal reply visible to the client.';
        }

        TicketResponse::withoutGlobalScopes()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
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
            ->with(['template:id,name', 'category:id,name', 'type:id,name'])
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'name', 'type_id', 'template_id', 'category_id', 'status_id']);

        if ($messages->isEmpty())
        {
            return 'No campaign messages yet. Use create_message to create one (you need a template from list_templates first).';
        }

        $lines = $messages->map(function ($m)
        {
            $type = $m->type ? $m->type->name : '?';
            $tpl = $m->template ? $m->template->name : '—';
            $cat = $m->category ? $m->category->name : '—';
            $status = (int) $m->status_id === 1 ? 'active' : 'inactive';

            return sprintf('  - %s (id: %d, channel: %s, template: %s, category: %s, %s)', $m->name, $m->id, $type, $tpl, $cat, $status);
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

        $categoryId = null;
        $categoryName = isset($input['category_name']) ? trim((string) $input['category_name']) : null;
        if ($categoryName !== null && $categoryName !== '')
        {
            $categoryId = $this->resolveContactCategoryByName($teamId, $categoryName);
            if ($categoryId === null)
            {
                return "Contact category \"{$categoryName}\" not found. Use list_contact_categories to see available categories.";
            }
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

        $active = ! empty($input['active']);
        $statusId = $active ? 1 : 0;

        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => $name,
            'type_id' => $typeId,
            'category_id' => $categoryId,
            'contact_status_id' => $contactStatusId,
            'template_id' => $templateId,
            'text' => $text,
            'status_id' => $statusId,
            'show_unsubscribe' => 1,
            'enable_open_tracking' => 1,
            'enable_click_tracking' => 1,
            'min_hours_between_emails' => 48,
        ]);

        $editUrl = url()->route('message.edit', $message->id);
        $showUrl = url()->route('message.show', $message->id);

        $out = "Campaign message created: {$message->name} (id: {$message->id}). Channel: {$channel}. Template: {$template->name}.";
        if ($categoryId)
        {
            $out .= " Audience: category \"{$categoryName}\".";
        }
        if ($contactStatusId)
        {
            $out .= " Contact status: {$contactStatusName}.";
        }
        $out .= ' '.($active ? 'Campaign is active.' : 'Campaign is inactive (activate from the platform to send).');
        $out .= " Edit: {$editUrl} — View/send: {$showUrl}";

        return $this->truncate($out);
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

        $label = $status === 'active' ? 'active' : 'paused (stopped)';

        return $this->truncate("Campaign \"{$message->name}\" (id: {$message->id}) is now {$label}.");
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

        $categoryName = isset($input['category_name']) ? trim((string) $input['category_name']) : null;
        if ($categoryName !== null && $categoryName !== '')
        {
            if ($hasDeliveries)
            {
                return 'Cannot change audience (category) because this campaign already has deliveries. You can only change status (active/paused).';
            }
            $categoryId = $this->resolveContactCategoryByName($teamId, $categoryName);
            if ($categoryId === null)
            {
                return "Contact category \"{$categoryName}\" not found. Use list_contact_categories to see available categories.";
            }
            $updates['category_id'] = $categoryId;
        }

        $contactStatusName = isset($input['contact_status_name']) ? trim((string) $input['contact_status_name']) : null;
        if ($contactStatusName !== null && $contactStatusName !== '')
        {
            if ($hasDeliveries)
            {
                return 'Cannot change contact status filter because this campaign already has deliveries. You can only change status (active/paused).';
            }
            $contactStatus = ContactStatus::where('name', $contactStatusName)->first()
                ?? ContactStatus::whereRaw('LOWER(name) = ?', [strtolower($contactStatusName)])->first();
            if ($contactStatus)
            {
                $updates['contact_status_id'] = $contactStatus->id;
            }
        }

        $status = isset($input['status']) ? strtolower(trim((string) $input['status'])) : null;
        if ($status !== null && $status !== '')
        {
            if (! in_array($status, ['active', 'paused'], true))
            {
                return 'status must be "active" or "paused".';
            }
            $updates['status_id'] = $status === 'active' ? 1 : 0;
        }

        if ($updates === [])
        {
            return 'No changes provided. Pass at least one of: category_name, contact_status_name, status.';
        }

        $message->update($updates);

        $parts = ['Campaign "'.$message->name.'" (id: '.$message->id.') updated.'];
        if (isset($updates['category_id']))
        {
            $parts[] = "Audience: category \"{$categoryName}\".";
        }
        if (isset($updates['contact_status_id']))
        {
            $parts[] = "Contact status: {$contactStatusName}.";
        }
        if (isset($updates['status_id']))
        {
            $parts[] = 'Status: '.(($updates['status_id'] ?? 0) === 1 ? 'active' : 'paused').'.';
        }
        $parts[] = 'Edit: '.url()->route('message.edit', $message->id).' — View/send: '.url()->route('message.show', $message->id);

        return $this->truncate(implode(' ', $parts));
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

    private function resolveOrCreateContactCategory(int $teamId, string $categoryName): ?int
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

        if ($category)
        {
            return $category->id;
        }

        $category = Category::withoutGlobalScopes()->create([
            'name' => $categoryName,
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

        $lines[] = 'To buy from WhatsApp: use add_to_whatsapp_cart with product_id or product_code, or tell the customer they can write: comprar [name or code]. Then: carrito, checkout.';

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
            return 'Cannot attach to a WhatsApp cart: no customer phone in this session. Tell the customer to write from WhatsApp, or use the commands: comprar [name or code], carrito, checkout. If you are in the web assistant with a recipient phone, open the assistant with that recipient selected.';
        }

        $product = $this->resolveWhatsAppProduct($teamId, $input);
        if (! $product)
        {
            return 'Product not found or not available on WhatsApp. Use search_products or pass product_id / product_code / product_name (one required).';
        }

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
        $msg .= 'Tell them they can write *carrito* to review or *checkout* to finish.';

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
