<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Module;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Defines and executes tools available to the chat assistant (create contact, task, send WhatsApp, etc.).
 * All operations are scoped to the authenticated user's current team (or to the request context when e.g. writing via WhatsApp).
 */
class AssistantToolsService
{
    public const MAX_TOOL_RESULT_LENGTH = 4000;

    protected ?int $contextUserId = null;

    protected ?int $contextTeamId = null;

    public function __construct(
        protected ?WhatsAppGateway $whatsAppGateway = null,
    ) {}

    /**
     * Set user and team context for tool execution when not in an HTTP auth context (e.g. WhatsApp webhook).
     */
    public function setRequestContext(?int $userId, ?int $teamId): void
    {
        $this->contextUserId = $userId;
        $this->contextTeamId = $teamId;
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
            if ($user)
            {
                auth()->login($user);
            }
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
                'create_contact' => $this->createContact($teamId, $user->id, $input),
                'assign_contact_to_category' => $this->assignContactToCategory($teamId, $input),
                'update_contact' => $this->updateContact($teamId, $input),
                'get_account_report' => $this->getAccountReport($teamId, $input),
                'send_whatsapp_message' => $this->sendWhatsAppMessage($user, $input),
                'create_task' => $this->createTask($teamId, $user->id, $input),
                'list_team_users' => $this->listTeamUsers($teamId),
                'get_my_profile' => $this->getMyProfile($user, $teamId),
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

    private function createContact(int $teamId, int $creatorId, array $input): string
    {
        if (! Gate::allows('create', Contact::class))
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
            'creator_id' => $creatorId,
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

        $gateway = $this->whatsAppGateway ?? app(WhatsAppGateway::class);
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

    private function truncate(string $s): string
    {
        if (mb_strlen($s) <= self::MAX_TOOL_RESULT_LENGTH)
        {
            return $s;
        }

        return mb_substr($s, 0, self::MAX_TOOL_RESULT_LENGTH - 20)."\n...(truncated)";
    }
}
