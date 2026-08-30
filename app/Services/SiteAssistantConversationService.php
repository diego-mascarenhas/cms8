<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationFlowSession;
use App\Models\Contact;
use App\Models\SiteAssistantMessage;
use App\Models\Team;
use App\Models\User;
use App\Support\ChatMessageAvatar;
use Illuminate\Support\Collection;

class SiteAssistantConversationService
{
    public const META_INBOUND_PROMPT = 'inbound_prompt_key';

    public function __construct(
        protected AutomationFlowEngine $flowEngine,
    ) {}

    public function recordTurn(
        Automation $automation,
        string $sessionKey,
        string $visitorMessage,
        string $assistantReply,
    ): void {
        $session = $this->flowEngine->sessionFor(
            $automation,
            Automation::CHANNEL_API,
            $sessionKey,
        );
        $contactId = $this->contactIdFromSession($session);

        $this->store($automation, $session, $sessionKey, SiteAssistantMessage::ROLE_VISITOR, $visitorMessage, $contactId);

        $reply = trim($assistantReply);
        if ($reply !== '')
        {
            $this->store($automation, $session, $sessionKey, SiteAssistantMessage::ROLE_ASSISTANT, $reply, $contactId);
        }

        $session->last_message_at = now();
        $session->save();
    }

    public function bindContactToSessionMessages(int $teamId, string $sessionKey, Contact $contact): void
    {
        SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('session_key', $sessionKey)
            ->whereNull('contact_id')
            ->update(['contact_id' => $contact->id]);
    }

    /**
     * @param  list<int>|null  $allowedContactIds
     * @return array{conversations: list<array<string, mixed>>, total: int}
     */
    public function listForTeam(Team $team, int $limit, ?array $allowedContactIds = null): array
    {
        $limit = max(1, min(100, $limit));
        $keysQuery = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNull('contact_id')
            ->select('session_key')
            ->selectRaw('MAX(id) as last_id')
            ->groupBy('session_key')
            ->orderByDesc('last_id');

        if ($allowedContactIds !== null)
        {
            if ($allowedContactIds === [])
            {
                return ['conversations' => [], 'total' => 0];
            }

            $keysQuery->whereIn('contact_id', $allowedContactIds);
        }

        $total = (clone $keysQuery)->get()->count();
        $page = $keysQuery->limit($limit)->get();
        $lastIds = $page->pluck('last_id')->all();
        $sessionKeys = $page->pluck('session_key')->all();

        $lastMessages = SiteAssistantMessage::withoutGlobalScopes()
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('session_key');

        $contactIds = $lastMessages->pluck('contact_id')->filter()->unique()->values()->all();
        $contacts = $contactIds === []
            ? collect()
            : Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereIn('id', $contactIds)
                ->get()
                ->keyBy('id');

        $conversations = [];
        foreach ($sessionKeys as $sessionKey)
        {
            $message = $lastMessages->get($sessionKey);
            if (! $message)
            {
                continue;
            }

            $contact = $message->contact_id ? $contacts->get((int) $message->contact_id) : null;
            $conversations[] = $this->formatConversation($sessionKey, $message, $contact);
        }

        return [
            'conversations' => $conversations,
            'total' => $total,
        ];
    }

    /**
     * @param  list<int>|null  $allowedContactIds
     * @return array<string, mixed>|null
     */
    public function threadForTeam(Team $team, string $sessionKey, ?array $allowedContactIds = null): ?array
    {
        $messages = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('session_key', $sessionKey)
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty())
        {
            return null;
        }

        $contactId = (int) ($messages->last()?->contact_id ?? 0);
        if ($allowedContactIds !== null && ($contactId < 1 || ! in_array($contactId, $allowedContactIds, true)))
        {
            return null;
        }

        if ($contactId > 0)
        {
            $messages = SiteAssistantMessage::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('contact_id', $contactId)
                ->orderBy('id')
                ->get();
        }

        $contact = $contactId > 0
            ? Contact::withoutGlobalScopes()->where('team_id', $team->id)->find($contactId)
            : null;

        return [
            'session_key' => $sessionKey,
            'visitor' => $this->visitorLabel($contact),
            'contact_id' => $contact?->id,
            'thread_assistant' => $this->threadAssistantMeta($team, $sessionKey, $contact),
            'messages' => $this->presentInboxMessages($messages),
        ];
    }

    public function inboundPromptKeyFor(Automation $automation, string $sessionKey, ?int $contactId = null): ?string
    {
        $session = $this->flowEngine->existingSession($automation, Automation::CHANNEL_API, $sessionKey);
        $fromSession = trim((string) data_get($session?->meta, self::META_INBOUND_PROMPT, ''));
        if ($fromSession !== '')
        {
            return $fromSession;
        }

        $id = $contactId ?? (int) data_get($session?->meta, 'contact_id', 0);
        if ($id < 1)
        {
            return null;
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $automation->team_id)
            ->find($id);

        return $contact?->inboundChatAssistantPromptKey();
    }

    public function pinInboundPrompt(
        Team $team,
        string $sessionKey,
        ?string $routingKey,
        bool $enabled = true,
        ?array $allowedContactIds = null,
    ): bool {
        $thread = $this->threadForTeam($team, $sessionKey, $allowedContactIds);
        if (! $thread)
        {
            return false;
        }

        $anchor = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('session_key', $sessionKey)
            ->orderByDesc('id')
            ->first();

        if (! $anchor)
        {
            return false;
        }

        $automation = Automation::withoutGlobalScopes()->find($anchor->automation_id);
        if (! $automation)
        {
            return false;
        }

        $session = $this->flowEngine->sessionFor($automation, Automation::CHANNEL_API, $sessionKey);
        $meta = is_array($session->meta) ? $session->meta : [];
        $key = $routingKey !== null ? trim($routingKey) : '';
        if (! $enabled)
        {
            $meta[self::META_INBOUND_PROMPT] = TeamSiteAssistantPromptService::OFF_KEY;
        } elseif ($key === '')
        {
            unset($meta[self::META_INBOUND_PROMPT]);
        } else
        {
            $meta[self::META_INBOUND_PROMPT] = $key;
        }
        $session->meta = $meta;
        $session->last_message_at = now();
        $session->save();

        $contactId = (int) ($thread['contact_id'] ?? $anchor->contact_id ?? 0);
        if ($contactId > 0)
        {
            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->find($contactId);
            if ($contact)
            {
                $this->applyContactInboundPreference($contact, $enabled, $enabled && $key !== '' ? $key : null);
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function threadAssistantMeta(Team $team, string $sessionKey, ?Contact $contact): array
    {
        $siteAssistant = app(TeamSiteAssistantPromptService::class);
        $inboundPolicy = app(TeamInboundAssistantPolicy::class);
        $promptKey = null;
        $anchor = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('session_key', $sessionKey)
            ->orderByDesc('id')
            ->first();
        if ($anchor)
        {
            $automation = Automation::withoutGlobalScopes()->find($anchor->automation_id);
            if ($automation)
            {
                $promptKey = $this->inboundPromptKeyFor($automation, $sessionKey, $contact?->id);
            }
        }

        $prompts = [];
        foreach ($siteAssistant->promptOptions($team) as $option)
        {
            $prompts[] = [
                'key' => $option['key'],
                'label' => $option['section_label'],
                'section_label' => $option['section_label'],
                'audience' => $option['audience'],
                'audience_rank' => $option['audience_rank'],
            ];
        }

        $explicitOff = ($promptKey !== null && TeamSiteAssistantPromptService::isReservedOffKey($promptKey))
            || ($contact !== null && ! $contact->allowsInboundChatAssistant());
        $usablePrompt = $explicitOff ? null : $promptKey;
        $plan = $inboundPolicy->presentWhatsAppAssistantState(
            $team,
            ! $explicitOff && $siteAssistant->allowsPublicEmbedReply($team, $contact?->id, $usablePrompt),
            true,
        );

        return array_merge($plan, [
            'contact_id' => $contact?->id,
            'assistant_contact_enabled' => ! $explicitOff,
            'prompt_key' => $usablePrompt,
            'default_prompt_key' => $siteAssistant->selectedRoutingKey($team),
            'prompts' => $prompts,
        ]);
    }

    private function applyContactInboundPreference(Contact $contact, bool $enabled, ?string $promptKey): void
    {
        $payload = json_encode($contact->data ?? new \stdClass);
        $data = json_decode($payload ?: '{}', true);
        if (! is_array($data))
        {
            $data = [];
        }
        $data['chat_assistant_ai_enabled'] = $enabled;
        $key = $promptKey !== null ? trim($promptKey) : '';
        if ($key !== '')
        {
            $data['chat_assistant_prompt_key'] = $key;
        } else
        {
            unset($data['chat_assistant_prompt_key']);
        }
        $contact->data = (object) $data;
        $contact->save();
    }

    public function replyAsStaff(
        Team $team,
        string $sessionKey,
        string $body,
        ?array $allowedContactIds = null,
        ?User $actor = null,
    ): ?SiteAssistantMessage {
        $body = trim($body);
        if ($body === '' || ! $this->threadForTeam($team, $sessionKey, $allowedContactIds))
        {
            return null;
        }

        $anchor = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('session_key', $sessionKey)
            ->orderByDesc('id')
            ->first();

        if (! $anchor)
        {
            return null;
        }

        $created = SiteAssistantMessage::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'automation_id' => $anchor->automation_id,
            'session_id' => $anchor->session_id,
            'session_key' => $sessionKey,
            'contact_id' => $anchor->contact_id,
            'user_id' => $actor?->id,
            'role' => SiteAssistantMessage::ROLE_STAFF,
            'body' => $body,
        ]);

        if ($anchor->session_id)
        {
            AutomationFlowSession::withoutGlobalScopes()
                ->where('id', $anchor->session_id)
                ->update(['last_message_at' => now()]);
        }

        return $created;
    }

    /**
     * @return list<array{id: int, role: string, body: string, created_at: string|null}>
     */
    public function publicMessagesForSession(Automation $automation, string $sessionKey, int $afterId = 0): array
    {
        $sessionKey = trim($sessionKey);
        if ($sessionKey === '')
        {
            return [];
        }

        return SiteAssistantMessage::withoutGlobalScopes()
            ->where('automation_id', $automation->id)
            ->where('session_key', $sessionKey)
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->get()
            ->map(fn (SiteAssistantMessage $message): array => [
                'id' => (int) $message->id,
                'role' => (string) $message->role,
                'body' => (string) $message->body,
                'created_at' => optional($message->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Latest web turn per identified contact, for merging into the main inbox.
     *
     * @param  list<int>|null  $allowedContactIds
     * @return array<int, array{contact: Contact, last_at: int, last_message: string, last_message_time: string, session_key: string}>
     */
    public function lastActivityByContactId(Team $team, ?array $allowedContactIds = null): array
    {
        $query = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNotNull('contact_id')
            ->select('contact_id')
            ->selectRaw('MAX(id) as last_id')
            ->groupBy('contact_id');

        if ($allowedContactIds !== null)
        {
            if ($allowedContactIds === [])
            {
                return [];
            }

            $query->whereIn('contact_id', $allowedContactIds);
        }

        $rows = $query->get();
        $lastIds = $rows->pluck('last_id')->all();
        if ($lastIds === [])
        {
            return [];
        }

        $lastMessages = SiteAssistantMessage::withoutGlobalScopes()
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('contact_id');

        $contacts = Contact::withoutGlobalScopes()
            ->with('categories:id')
            ->where('team_id', $team->id)
            ->whereIn('id', $lastMessages->keys()->all())
            ->get()
            ->keyBy('id');

        $activity = [];
        foreach ($lastMessages as $contactId => $message)
        {
            $contact = $contacts->get((int) $contactId);
            if (! $contact)
            {
                continue;
            }

            $activity[(int) $contactId] = [
                'contact' => $contact,
                'last_at' => $message->created_at?->getTimestamp() ?? 0,
                'last_message' => (string) $message->body,
                'last_message_time' => optional($message->created_at)?->diffForHumans() ?? '',
                'session_key' => (string) $message->session_key,
            ];
        }

        return $activity;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentThreadMessagesForContact(Team $team, int $contactId): array
    {
        if ($contactId < 1)
        {
            return [];
        }

        $messages = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('contact_id', $contactId)
            ->orderBy('id')
            ->get();
        $users = $this->usersForMessages($messages);

        return $messages
            ->map(function (SiteAssistantMessage $message) use ($users): array
            {
                $presented = $this->presentInboxMessage($message, $users);

                return [
                    'id' => 'web-'.$message->id,
                    'direction' => $message->role === SiteAssistantMessage::ROLE_VISITOR ? 'inbound' : 'outbound',
                    'body' => $message->body,
                    'status' => $message->role === SiteAssistantMessage::ROLE_VISITOR ? 'received' : 'sent',
                    'created_at' => $presented['created_at'],
                    'user_id' => $presented['user_id'],
                    'media' => [],
                    'from_assistant' => $presented['from_assistant'],
                    'channel' => 'web',
                    'sender_avatar' => $presented['sender_avatar'],
                    'usage' => null,
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, SiteAssistantMessage>  $messages
     * @return list<array<string, mixed>>
     */
    private function presentInboxMessages(Collection $messages): array
    {
        $users = $this->usersForMessages($messages);

        return $messages
            ->map(fn (SiteAssistantMessage $message): array => $this->presentInboxMessage($message, $users))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SiteAssistantMessage>  $messages
     * @return Collection<int, User>
     */
    private function usersForMessages(Collection $messages): Collection
    {
        $userIds = $messages->pluck('user_id')->filter()->unique()->values();
        if ($userIds->isEmpty())
        {
            return collect();
        }

        return User::withoutGlobalScopes()
            ->whereIn('id', $userIds->all())
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{id: int, role: string, body: string, created_at: string|null, user_id: int|null, from_assistant: bool, sender_avatar: array<string, mixed>|null}
     */
    private function presentInboxMessage(SiteAssistantMessage $message, Collection $users): array
    {
        $fromAssistant = $message->role === SiteAssistantMessage::ROLE_ASSISTANT;
        $user = $message->user_id ? $users->get((int) $message->user_id) : null;
        $senderAvatar = null;
        if ($fromAssistant)
        {
            $senderAvatar = ChatMessageAvatar::forAssistant();
        } elseif ($message->role === SiteAssistantMessage::ROLE_STAFF)
        {
            $senderAvatar = ChatMessageAvatar::forUser($user);
        }

        return [
            'id' => (int) $message->id,
            'role' => (string) $message->role,
            'body' => (string) $message->body,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'user_id' => $message->user_id ? (int) $message->user_id : null,
            'from_assistant' => $fromAssistant,
            'sender_avatar' => $senderAvatar,
        ];
    }

    public function canViewAllTeamConversations(?User $user): bool
    {
        if (! $user)
        {
            return true;
        }

        if ($user->hasAnyRole(['admin', 'root']))
        {
            return true;
        }

        $team = $user->currentTeam;
        if ($team && $user->ownsTeam($team))
        {
            return true;
        }

        if ($user->hasAnyRole(['collaborator', 'developer', 'editor', 'technical', 'employee']))
        {
            return true;
        }

        if ($team)
        {
            $membershipRole = optional($user->teams->firstWhere('id', $team->id))->pivot?->role;
            if (in_array($membershipRole, ['admin', 'root'], true))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>|null
     */
    public function allowedContactIdsFor(?User $user): ?array
    {
        if ($this->canViewAllTeamConversations($user))
        {
            return null;
        }

        $team = $user?->currentTeam;
        if (! $user || ! $team)
        {
            return [];
        }

        return Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function store(
        Automation $automation,
        AutomationFlowSession $session,
        string $sessionKey,
        string $role,
        string $body,
        ?int $contactId,
    ): SiteAssistantMessage {
        return SiteAssistantMessage::withoutGlobalScopes()->create([
            'team_id' => $automation->team_id,
            'automation_id' => $automation->id,
            'session_id' => $session->id,
            'session_key' => $sessionKey,
            'contact_id' => $contactId,
            'role' => $role,
            'body' => $body,
        ]);
    }

    private function contactIdFromSession(AutomationFlowSession $session): ?int
    {
        $contactId = (int) data_get($session->meta, 'contact_id', 0);

        return $contactId > 0 ? $contactId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatConversation(string $sessionKey, SiteAssistantMessage $message, ?Contact $contact): array
    {
        $visitor = $this->visitorLabel($contact);

        return [
            'session_key' => $sessionKey,
            'name' => $visitor['name'],
            'email' => $visitor['email'],
            'contact_id' => $contact?->id,
            'identified' => $visitor['identified'],
            'last_message' => $message->body,
            'last_message_time' => optional($message->created_at)?->diffForHumans() ?? '',
            'last_message_at' => optional($message->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array{identified: bool, name: string, email: string|null}
     */
    private function visitorLabel(?Contact $contact): array
    {
        if (! $contact)
        {
            return [
                'identified' => false,
                'name' => __('Visitante'),
                'email' => null,
            ];
        }

        $name = trim((string) $contact->name.' '.(string) ($contact->surname ?? ''));

        return [
            'identified' => true,
            'name' => $name !== '' ? $name : __('Visitante'),
            'email' => $contact->email ? strtolower(trim((string) $contact->email)) : null,
        ];
    }
}
