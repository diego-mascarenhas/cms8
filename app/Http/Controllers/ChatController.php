<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppGateway;
use App\Exceptions\WhatsAppSessionWindowClosedException;
use App\Helpers\PhoneHelper;
use App\Helpers\TextHelper;
use App\Helpers\WhatsAppOutboundText;
use App\Http\Requests\StartWhatsAppChatContactRequest;
use App\Http\Requests\StoreWhatsAppContactCategoryRequest;
use App\Http\Requests\UpdateWhatsAppChatArchiveRequest;
use App\Http\Requests\UpdateWhatsAppChatReadRequest;
use App\Http\Requests\UpdateWhatsAppContactAssistantRequest;
use App\Http\Requests\UpdateWhatsAppContactCategoriesRequest;
use App\Http\Requests\UpdateWhatsAppInboxContactRequest;
use App\Jobs\SendScheduledMessageJob;
use App\Models\AgentConversationMessage;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\ScheduledMessage;
use App\Models\Team;
use App\Models\User;
use App\Services\AdminProactiveOutreachSlashDispatcher;
use App\Services\AgentConversationContextService;
use App\Services\Assistant\AssistantInboundContactCreationService;
use App\Services\Assistant\AssistantInboundTaskStatusService;
use App\Services\AssistantPromptCatalog;
use App\Services\ChatAssistantReplyService;
use App\Services\DocumentIngestionService;
use App\Services\InboxQuickReplyService;
use App\Services\InboxReplyTargetService;
use App\Services\PerformanceInsightSlashDispatcher;
use App\Services\SiteAssistantConversationService;
use App\Services\TeamApiUsageStatsService;
use App\Services\TeamInboundAssistantPolicy;
use App\Services\TeamSiteAssistantPromptService;
use App\Services\TeamWhatsAppChatPresentation;
use App\Services\TeamWhatsAppConnectionSync;
use App\Services\UserResolverService;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppChatArchiveService;
use App\Services\WhatsApp\WhatsAppContactSheetImportService;
use App\Services\WhatsApp\WhatsAppCustomerServiceWindow;
use App\Services\WhatsApp\WhatsAppInboundContactRegistrationService;
use App\Services\WhatsApp\WhatsAppInboxContactStarter;
use App\Services\WhatsApp\WhatsAppInvoiceSheetImportService;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppProfilePhotoStore;
use App\Services\WhatsApp\WhatsAppTaskSheetImportService;
use App\Services\WhatsApp\WhatsAppThreadCategoryService;
use App\Support\AssistantCreatedMessageRedirect;
use App\Support\AssistantTaskStatusUpdate;
use App\Support\ChatMessageAvatar;
use App\Support\WhatsAppDriver;
use App\Support\WhatsAppSendExceptionPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Transcription;

class ChatController extends Controller
{
    /**
     * Normalize phone for deduplication: strip WhatsApp JID suffix (e.g. :11).
     */
    private function normalizePhoneForList(string $phone): string
    {
        $stripped = preg_replace('/:\d+$/', '', trim($phone));

        return $stripped !== '' ? $stripped : $phone;
    }

    /**
     * Apply conversation filter by phone (normalized + JID suffix variant).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Conversation>  $query
     */
    private function applyConversationPhoneFilter($query, string $phone): void
    {
        $norm = $this->normalizePhoneForList($phone);
        $query->where(function ($q) use ($norm)
        {
            $q->where('from', $norm)->orWhere('to', $norm)
                ->orWhere('from', 'like', $norm.':%')
                ->orWhere('to', 'like', $norm.':%');
        });
    }

    /**
     * Scope Conversation query to the current user's team (by WhatsApp Assistant number).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Conversation>
     */
    private function conversationQueryForTeam()
    {
        $team = auth()->check() ? auth()->user()->currentTeam : null;
        $teamNumber = $team ? preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom()) : null;

        $query = Conversation::where('channel', 'whatsapp');

        if ($teamNumber !== null && $teamNumber !== '')
        {
            $query->where(function ($q) use ($teamNumber)
            {
                $q->where('from', $teamNumber)
                    ->orWhere('to', $teamNumber)
                    ->orWhere('from', 'like', $teamNumber.':%')
                    ->orWhere('to', 'like', $teamNumber.':%');
            });
        }

        return $query;
    }

    /**
     * Whether the user may see every WhatsApp thread for the current team (null restriction in {@see allowedExternalPhonesForChat()}).
     */
    private function canViewAllTeamWhatsAppChats(): bool
    {
        if (! auth()->check())
        {
            return true;
        }

        $user = auth()->user();

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
     * For restricted users, return external phone numbers they may see (own phone + contacts they are responsible for). Null means no restriction.
     *
     * @return array<int, string>|null
     */
    private function allowedExternalPhonesForChat(): ?array
    {
        if ($this->canViewAllTeamWhatsAppChats())
        {
            return null;
        }

        $user = auth()->user();
        $team = $user->currentTeam;
        if (! $team)
        {
            return [];
        }

        $phones = [];
        $userPhone = $user->phone ? preg_replace('/[^0-9]/', '', (string) $user->phone) : null;
        if ($userPhone !== null && $userPhone !== '')
        {
            $phones[] = $userPhone;
        }

        $contactPhones = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $user->id)
            ->get()
            ->flatMap(function (Contact $c)
            {
                $p = $c->phone ? preg_replace('/[^0-9]/', '', (string) $c->phone) : null;

                return $p ? [$p] : [];
            })
            ->unique()
            ->values()
            ->all();

        return array_values(array_unique(array_merge($phones, $contactPhones)));
    }

    /**
     * When driver is local, return a gateway for the current team's Node instance (one per team = no disconnects).
     */
    private function currentTeamUsesLocalWhatsApp(): bool
    {
        return WhatsAppDriver::isLocal(auth()->user()?->currentTeam);
    }

    private function getLocalGatewayForCurrentTeam(): ?WhatsAppGateway
    {
        if (! $this->currentTeamUsesLocalWhatsApp())
        {
            return null;
        }
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return null;
        }
        $baseUrl = $team->getWhatsAppServiceBaseUrl();
        if ($baseUrl === '')
        {
            return null;
        }

        return new LocalWhatsAppGateway($baseUrl, config('whatsapp.local.webhook_secret'), $team->id);
    }

    /**
     * Filters WhatsApp sidebar rows using {@see ContactStatus} id via `crm_status`. Chats with no CRM contact ({@see findContactForTeamByChatPhone} null) match the Lead status filter. Legacy `crm_status=none` is treated as Lead.
     *
     * @return Collection<int, object>
     */
    private function getWhatsAppContacts(?Request $request = null): Collection
    {
        return $this->hydrateWhatsAppContacts($this->whatsAppConversationIndex($request));
    }

    /**
     * Ordered conversation index for the current team: who we talk to, when they last wrote and how
     * much is unread, resolved with a handful of aggregates instead of a query per conversation.
     *
     * The expensive per-conversation work lives in {@see hydrateWhatsAppContacts()}, so callers can
     * slice this index first and only pay for the page they are about to show.
     *
     * @return list<array{digits: string, phone: string, last_at: int, unread: int, crm: ?Contact}>
     */
    private function whatsAppConversationIndex(?Request $request = null): array
    {
        $team = auth()->check() ? auth()->user()->currentTeam : null;
        if (! $team || ! $team->getWhatsAppFrom())
        {
            return [];
        }

        // Every conversation asks the team for the same handful of settings.
        $team->loadMissing('settings');

        $query = $this->conversationQueryForTeam();
        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        $allowedPhones = $this->allowedExternalPhonesForChat();
        $inboundPolicy = app(TeamInboundAssistantPolicy::class);

        $lastAt = [];
        $unread = [];
        $phones = [];

        $collect = function (Collection $rows) use (&$lastAt, &$phones): void
        {
            foreach ($rows as $row)
            {
                $digits = preg_replace('/[^0-9]/', '', $this->normalizePhoneForList((string) $row->phone));
                if ($digits === '')
                {
                    continue;
                }
                $timestamp = strtotime((string) $row->last_at) ?: 0;
                if (! isset($lastAt[$digits]) || $timestamp > $lastAt[$digits])
                {
                    $lastAt[$digits] = $timestamp;
                }
                $phones[$digits] ??= $this->normalizePhoneForList((string) $row->phone);
            }
        };

        $collect($this->whatsAppConversationAggregate($query, 'inbound', 'from'));
        $collect($this->whatsAppConversationAggregate($query, 'outbound', 'to'));

        foreach ($this->whatsAppConversationAggregate($query, 'inbound', 'from', unreadOnly: true) as $row)
        {
            $digits = preg_replace('/[^0-9]/', '', $this->normalizePhoneForList((string) $row->phone));
            if ($digits !== '')
            {
                $unread[$digits] = ($unread[$digits] ?? 0) + (int) $row->unread;
            }
        }

        // Digit-only keys come back from array_keys() as ints, and the inbox sends them out as phones.
        $digitsList = array_values(array_filter(
            array_map(strval(...), array_keys($lastAt)),
            function (string $digits) use ($allowedPhones, $teamNumber, $inboundPolicy, $team, $phones): bool
            {
                if ($digits === $teamNumber || $phones[$digits] === $teamNumber)
                {
                    return false;
                }
                if ($allowedPhones !== null && ! in_array($digits, $allowedPhones, true))
                {
                    return false;
                }

                return ! $inboundPolicy->isBlacklistedWhatsAppPhone($team, $phones[$digits]);
            },
        ));

        $crmByDigits = $this->crmContactsByChatDigits((int) $team->id, $digitsList);

        $effectiveRequest = $request ?? request();
        $filter = $effectiveRequest instanceof Request
            ? $this->resolveWhatsAppListCrmStatusFilter($effectiveRequest)
            : ['mode' => 'all'];
        $leadStatusId = $filter['mode'] === 'all' ? null : $this->resolveLeadContactStatusId();
        $categoryId = $effectiveRequest instanceof Request ? $effectiveRequest->integer('category_id') : 0;

        $index = [];
        foreach ($digitsList as $digits)
        {
            $crm = $crmByDigits[$digits] ?? null;
            $row = (object) ['crm_has_contact' => $crm !== null, 'crm_status_id' => $crm?->status_id];
            if ($filter['mode'] !== 'all' && ! $this->contactRowMatchesCrmStatusFilter($row, (int) ($filter['status_id'] ?? 0), $leadStatusId))
            {
                continue;
            }
            if ($categoryId > 0 && ! $this->contactRowMatchesCategoryFilter($crm, $categoryId))
            {
                continue;
            }

            $index[] = [
                'digits' => $digits,
                'phone' => $phones[$digits],
                'last_at' => $lastAt[$digits],
                'unread' => $unread[$digits] ?? 0,
                'crm' => $crm,
            ];
        }

        usort($index, fn (array $a, array $b): int => $b['last_at'] <=> $a['last_at']);

        return $index;
    }

    /**
     * One grouped row per counterpart phone: its latest message time, or its unread tally.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Conversation>  $query
     * @return Collection<int, object{phone: string, last_at: ?string, unread: int}>
     */
    private function whatsAppConversationAggregate($query, string $direction, string $column, bool $unreadOnly = false): Collection
    {
        $scoped = (clone $query)->where('direction', $direction);
        if ($unreadOnly)
        {
            $scoped->where('status', 'received');
        }

        $base = $scoped->toBase();
        $wrapped = $base->getGrammar()->wrap($column);

        return $base
            ->selectRaw("{$wrapped} as phone, MAX(created_at) as last_at, COUNT(*) as unread")
            ->groupBy($column)
            ->get()
            ->filter(fn ($row) => (string) $row->phone !== '')
            ->values();
    }

    /**
     * Resolve every conversation's CRM contact in one query instead of one lookup per phone.
     *
     * @param  list<string>  $digitsList
     * @return array<string, Contact>
     */
    private function crmContactsByChatDigits(int $teamId, array $digitsList): array
    {
        if ($digitsList === [])
        {
            return [];
        }

        $candidatesByDigits = [];
        $allCandidates = [];
        foreach ($digitsList as $digits)
        {
            $candidates = $this->chatPhoneCandidates($digits);
            $candidatesByDigits[$digits] = $candidates;
            $allCandidates = array_merge($allCandidates, $candidates);
        }

        $contactsByPhone = [];
        Contact::query()
            ->with(['currentSentiment.sentiment', 'categories:id'])
            ->where('team_id', $teamId)
            ->whereIn('phone', array_values(array_unique($allCandidates)))
            ->orderBy('id')
            ->get()
            ->each(function (Contact $contact) use (&$contactsByPhone): void
            {
                $phone = (string) $contact->phone;
                $contactsByPhone[$phone] ??= $contact;
            });

        $resolved = [];
        foreach ($candidatesByDigits as $digits => $candidates)
        {
            $matches = array_filter(array_map(fn (string $phone): ?Contact => $contactsByPhone[$phone] ?? null, $candidates));
            if ($matches === [])
            {
                continue;
            }

            usort($matches, fn (Contact $a, Contact $b): int => $a->id <=> $b->id);
            $resolved[$digits] = $matches[0];
        }

        return $resolved;
    }

    /**
     * Batched {@see getUserByPhone()} for the inbox: two queries for the whole page instead of up to
     * three per conversation.
     *
     * @param  list<string>  $digitsList
     * @return array<string, User>
     */
    private function usersByChatDigits(array $digitsList): array
    {
        if ($digitsList === [])
        {
            return [];
        }

        $candidatesByDigits = [];
        foreach ($digitsList as $digits)
        {
            // Only Spanish numbers are tried without their country code, to avoid false matches.
            $candidatesByDigits[$digits] = strlen($digits) === 11 && str_starts_with($digits, '34')
                ? [$digits, substr($digits, -9)]
                : [$digits];
        }

        $usersByPhone = [];
        User::query()
            ->whereIn('phone', array_values(array_unique(array_merge(...array_values($candidatesByDigits)))))
            ->get()
            ->each(function (User $user) use (&$usersByPhone): void
            {
                $usersByPhone[(string) $user->phone] ??= $user;
            });

        $resolved = [];
        $unmatched = [];
        foreach ($candidatesByDigits as $digits => $candidates)
        {
            $hit = null;
            foreach ($candidates as $candidate)
            {
                $hit ??= $usersByPhone[$candidate] ?? null;
            }

            if ($hit instanceof User)
            {
                $resolved[(string) $digits] = $hit;
            } else
            {
                $unmatched[] = (string) $digits;
            }
        }

        if ($unmatched === [])
        {
            return $resolved;
        }

        // The phone may hang off a CRM contact's phone source rather than the user row.
        Contact::query()
            ->with(['user', 'sources' => fn ($q) => $q->where('source_id', 2)->whereIn('value', $unmatched)])
            ->whereHas('sources', fn ($q) => $q->where('source_id', 2)->whereIn('value', $unmatched))
            ->get()
            ->each(function (Contact $contact) use (&$resolved): void
            {
                if (! $contact->user)
                {
                    return;
                }

                foreach ($contact->sources as $source)
                {
                    $value = (string) ($source->pivot->value ?? '');
                    if ($value !== '')
                    {
                        $resolved[$value] ??= $contact->user;
                    }
                }
            });

        return $resolved;
    }

    /**
     * Phone spellings a Spanish number may be stored under: as dialled, and with or without the 34.
     *
     * @return list<string>
     */
    private function chatPhoneCandidates(string $digits): array
    {
        $candidates = [$digits];
        if (strlen($digits) === 11 && str_starts_with($digits, '34'))
        {
            $candidates[] = substr($digits, -9);
        }
        if (strlen($digits) === 9)
        {
            $candidates[] = '34'.$digits;
        }

        return $candidates;
    }

    /**
     * Fill in the fields the inbox shows for a slice of {@see whatsAppConversationIndex()}: last
     * message body, display name, assistant state and avatar.
     *
     * @param  list<array{digits: string, phone: string, last_at: int, unread: int, crm: ?Contact}>  $index
     */
    private function hydrateWhatsAppContacts(array $index): Collection
    {
        $team = auth()->check() ? auth()->user()->currentTeam : null;
        if ($team === null || $index === [])
        {
            return collect();
        }

        $team->loadMissing('settings');
        $query = $this->conversationQueryForTeam();
        $inboundPolicy = app(TeamInboundAssistantPolicy::class);
        $usersByDigits = $this->usersByChatDigits(array_column($index, 'digits'));

        $contacts = collect();
        foreach ($index as $entry)
        {
            $lastMessage = (clone $query)
                ->where(function ($q) use ($entry)
                {
                    $this->applyConversationPhoneFilter($q, $entry['phone']);
                })
                ->latest()
                ->first();
            if (! $lastMessage)
            {
                continue;
            }

            $contact = (object) [
                'from' => $entry['digits'],
                'last_message' => $lastMessage->body,
                'last_message_time' => $lastMessage->created_at->diffForHumans(),
                'last_message_at' => $lastMessage->created_at,
                'unread_count' => $entry['unread'],
            ];

            $userData = $usersByDigits[$entry['digits']] ?? null;
            if ($userData)
            {
                $contact->user_name = $userData->name;
                $contact->user_photo = $userData->profile_photo_path;
                $contact->user_id = $userData->id;
            }

            $crmProfile = $entry['crm'];
            if (! $userData && $crmProfile !== null)
            {
                $crmDisplayName = trim($crmProfile->name.' '.(string) ($crmProfile->surname ?? ''));
                if ($crmDisplayName !== '')
                {
                    $contact->user_name = $crmDisplayName;
                }
            }
            $contact->crm_has_contact = $crmProfile !== null;
            $contact->crm_status_id = $crmProfile?->status_id;
            $contact->contact_id = $crmProfile?->id;
            $contact->category_ids = $crmProfile?->relationLoaded('categories')
                ? $crmProfile->categories->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [];
            $mood = $crmProfile?->currentSentiment?->sentiment;
            $contact->sentiment = $mood !== null
                ? [
                    'id' => (int) $mood->id,
                    'name' => (string) $mood->name,
                    'emoji' => (string) $mood->emoji,
                ]
                : null;

            $assistantState = $inboundPolicy->presentWhatsAppAssistantState(
                $team,
                $inboundPolicy->autoReplyPreferencesAllow($team, $userData instanceof User ? $userData : null, (int) $team->id, $entry['digits']),
                $crmProfile !== null,
            );
            $contact->assistant_toggle_available = $assistantState['assistant_toggle_available'];
            $contact->assistant_inbound_enabled = $assistantState['assistant_inbound_enabled'];
            $contact->assistant_contact_enabled = $crmProfile ? $crmProfile->allowsInboundChatAssistant() : true;
            $contact->assistant_plan_active = $assistantState['assistant_plan_active'];
            $contact->assistant_locked_reason = $assistantState['assistant_locked_reason'];

            $contacts->push($contact);
        }

        $this->attachWhatsAppProfilePhotos($contacts);

        return $contacts->values();
    }

    private function attachWhatsAppProfilePhotos(Collection $contacts): void
    {
        $team = auth()->user()?->currentTeam;
        if ($team === null || $contacts->isEmpty())
        {
            return;
        }

        $store = app(WhatsAppProfilePhotoStore::class);
        $teamId = (int) $team->id;
        $missing = $contacts
            ->pluck('from')
            ->filter(fn ($phone) => is_string($phone) && $phone !== '' && ! $store->isFresh($teamId, $phone))
            ->values()
            ->all();

        $gateway = $this->getLocalGatewayForCurrentTeam();
        if ($missing !== [] && $gateway instanceof LocalWhatsAppGateway)
        {
            $store->hydrateFromGateway($gateway, $teamId, $missing);
        }

        foreach ($contacts as $contact)
        {
            $url = $store->publicUrl($teamId, (string) ($contact->from ?? ''));
            if ($url === null)
            {
                continue;
            }

            $contact->whatsapp_photo_url = $url;
            if (empty($contact->user_photo))
            {
                $contact->user_photo = $store->relativePath($teamId, (string) $contact->from);
            }
        }
    }

    private function chatListPhotoUrl(object $contact): ?string
    {
        if (! empty($contact->whatsapp_photo_url) && is_string($contact->whatsapp_photo_url))
        {
            return $contact->whatsapp_photo_url;
        }

        $path = $contact->user_photo ?? null;
        if (! is_string($path) || $path === '')
        {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
        {
            return $path;
        }

        return asset('storage/'.$path);
    }

    /**
     * @return array{mode: string, status_id?: int}
     */
    private function resolveWhatsAppListCrmStatusFilter(Request $request): array
    {
        if (! $request->filled('crm_status'))
        {
            return ['mode' => 'all'];
        }
        $raw = $request->query('crm_status');
        if ($raw === 'none')
        {
            $leadId = $this->resolveLeadContactStatusId();

            return $leadId !== null ? ['mode' => 'status_id', 'status_id' => $leadId] : ['mode' => 'all'];
        }
        $id = (int) $raw;

        return $id > 0 ? ['mode' => 'status_id', 'status_id' => $id] : ['mode' => 'all'];
    }

    private function resolveLeadContactStatusId(): ?int
    {
        $id = ContactStatus::query()->where('name', 'Lead')->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array{mode: string, status_id?: int}  $spec
     */
    private function applyWhatsAppCrmConversationFilter(Collection $contacts, array $spec, ?int $leadStatusId): Collection
    {
        return match ($spec['mode'])
        {
            'status_id' => $contacts->filter(function ($c) use ($spec, $leadStatusId): bool
            {
                if (! isset($spec['status_id']))
                {
                    return false;
                }
                $targetId = (int) $spec['status_id'];

                return $this->contactRowMatchesCrmStatusFilter($c, $targetId, $leadStatusId);
            })->values(),
            default => $contacts,
        };
    }

    /**
     * @param  object  $conversationRow  Row from {@see getWhatsAppContacts} with CRM fields.
     */
    private function contactRowMatchesCrmStatusFilter(object $conversationRow, int $targetStatusId, ?int $leadStatusId): bool
    {
        $hasContact = isset($conversationRow->crm_has_contact)
            ? (bool) $conversationRow->crm_has_contact
            : false;

        if (! $hasContact)
        {
            return $leadStatusId !== null && $targetStatusId === $leadStatusId;
        }

        return (int) ($conversationRow->crm_status_id ?? 0) === $targetStatusId;
    }

    private function contactRowMatchesCategoryFilter(?Contact $contact, int $categoryId): bool
    {
        if ($contact === null || $categoryId <= 0)
        {
            return false;
        }

        $ids = $contact->relationLoaded('categories')
            ? $contact->categories->pluck('id')
            : $contact->categories()->pluck('categories.id');

        return $ids->map(fn ($id): int => (int) $id)->contains($categoryId);
    }

    public function index()
    {
        $viewAssistant = request('view') === 'assistant';
        $assistantUserId = request()->integer('user_id', 0) ?: null;
        $contacts = $this->getWhatsAppContacts(request());

        // If a contact is selected, get their messages (normalize to digits so list dedupe and active state match)
        $selectedPhone = request('phone') ? preg_replace('/[^0-9]/', '', $this->normalizePhoneForList((string) request('phone'))) : null;
        if ($selectedPhone !== null && $selectedPhone === '')
        {
            $selectedPhone = null;
        }
        if ($selectedPhone !== null && auth()->check() && auth()->user()->currentTeam)
        {
            $currentTeam = auth()->user()->currentTeam;
            if (app(TeamInboundAssistantPolicy::class)->isBlacklistedWhatsAppPhone($currentTeam, $selectedPhone))
            {
                abort(403);
            }
        }
        $messages = collect();
        $selectedUser = null;
        $assistantMessages = [];
        $selectedAssistantUser = null;
        $assistantClients = collect();

        if ($viewAssistant && auth()->check())
        {
            $contextService = app(AgentConversationContextService::class);
            if ($assistantUserId && $this->canViewAssistantConversation($assistantUserId))
            {
                $selectedAssistantUser = User::withoutGlobalScopes()->find($assistantUserId);
                if ($selectedAssistantUser)
                {
                    $assistantMessages = $contextService->getMessagesForDisplay($selectedAssistantUser->id, 50);
                }
            }
            if (! $selectedAssistantUser)
            {
                $assistantMessages = $contextService->getMessagesForDisplay(auth()->id(), 50);
            }
            $assistantClients = $this->getAssistantClientsList();
        } elseif ($selectedPhone)
        {
            $allowedPhones = $this->allowedExternalPhonesForChat();
            $normPhone = preg_replace('/[^0-9]/', '', (string) $selectedPhone);
            if ($allowedPhones !== null && ! in_array($normPhone, $allowedPhones, true))
            {
                $messages = collect();
                $selectedUser = null;
            } else
            {
                // Get all messages for this conversation (match normalized phone and JID suffix variant)
                $messages = $this->conversationQueryForTeam()
                    ->where(function ($query) use ($selectedPhone)
                    {
                        $this->applyConversationPhoneFilter($query, $selectedPhone);
                    })
                    ->orderBy('created_at')
                    ->get();

                $messages = $this->mergePendingScheduledMessages($messages, $selectedPhone);

                // Mark inbound messages as read when user views the conversation
                $norm = $this->normalizePhoneForList($selectedPhone);
                $this->conversationQueryForTeam()
                    ->where('direction', 'inbound')
                    ->where('status', 'received')
                    ->where(function ($q) use ($norm)
                    {
                        $q->where('from', $norm)->orWhere('from', 'like', $norm.':%');
                    })
                    ->update(['status' => 'read']);
            }
            $team = auth()->check() ? auth()->user()->currentTeam : null;
            if ($team)
            {
                Cache::forget('inbound_received_count_team_'.$team->id);
            }
            Cache::forget(Conversation::CACHE_KEY_INBOUND_UNREAD);

            if ($allowedPhones === null || in_array($normPhone, $allowedPhones, true))
            {
                $selectedUser = $this->getUserByPhone($selectedPhone);
            }
        }

        $hasContact = false;
        $selectedContact = null;
        if ($selectedUser && $selectedUser->id)
        {
            $selectedContact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
                ->where('user_id', $selectedUser->id)->first();
            $hasContact = $selectedContact !== null;
        }

        if (! $selectedContact && $selectedPhone && auth()->check() && auth()->user()->currentTeam)
        {
            $digits = preg_replace('/[^0-9]/', '', (string) $selectedPhone);
            $selectedContact = $this->findContactForTeamByChatPhone((int) auth()->user()->currentTeam->id, $digits);
            $hasContact = $selectedContact !== null;
        }

        $userIds = $messages->pluck('user_id')->filter()->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($messages as $message)
        {
            if ($message instanceof Conversation)
            {
                $message->body = TextHelper::sanitizeAndLink($message->body);
            }
        }

        $clientRecipientPhone = $selectedAssistantUser ? $this->getWhatsAppPhoneForUser($selectedAssistantUser) : '';
        $assistantClientPhoneDisplay = $clientRecipientPhone !== ''
            ? preg_replace('/[^0-9+]/', '', str_replace('whatsapp:', '', $clientRecipientPhone))
            : ($selectedAssistantUser ? ($selectedAssistantUser->phone ?? $selectedAssistantUser->email ?? '') : '');
        $assistantContactId = $selectedAssistantUser
            ? (Contact::withoutGlobalScopes()->where('user_id', $selectedAssistantUser->id)->first()?->id ?? '')
            : '';
        // Team opt-out: chat_ai_assistance_blocked false / unset => AI may respond in chat (no per-contact).
        $userChatAiToggleDefault = true;
        if (auth()->check() && auth()->user()->currentTeam)
        {
            $blocked = auth()->user()->currentTeam->getSetting('chat_ai_assistance_blocked', false);
            $userChatAiToggleDefault = ! filter_var($blocked, FILTER_VALIDATE_BOOLEAN);
        }

        $presentation = TeamWhatsAppChatPresentation::resolveForTeam(auth()->user()?->currentTeam);
        $whatsappDriver = $presentation['whatsappDriver'];
        $whatsappStatus = $presentation['whatsappStatus'];
        $teamWhatsAppNumber = $presentation['teamWhatsAppNumber'];
        $teamWhatsAppNumberFormatted = $presentation['teamWhatsAppNumberFormatted'];
        $teamWhatsAppIsConnected = $presentation['teamWhatsAppIsConnected'];
        $qrImageUrl = $presentation['qrImageUrl'];

        $assistantAutoRespond = auth()->check() && auth()->user()->currentTeam
            ? filter_var(auth()->user()->currentTeam->getSetting('assistant_auto_respond', '1'), FILTER_VALIDATE_BOOLEAN)
            : false;
        $assistantAutoRespondAdminsWhenOff = auth()->check() && auth()->user()->currentTeam
            ? filter_var(auth()->user()->currentTeam->getSetting('assistant_auto_respond_admins_when_off', '0'), FILTER_VALIDATE_BOOLEAN)
            : false;

        // Header contact toggle reflects per-contact preference (editable even when team global is off).
        if ($viewAssistant ?? false)
        {
            $contactChatAiToggleDefault = $userChatAiToggleDefault;
        } elseif ($selectedContact !== null)
        {
            $contactChatAiToggleDefault = $selectedContact->allowsInboundChatAssistant();
        } else
        {
            $contactChatAiToggleDefault = false;
        }

        $currentTeam = auth()->user()?->currentTeam;
        $assistantChatStub = $currentTeam
            && filter_var($currentTeam->getSetting('assistant_chat_stub', false), FILTER_VALIDATE_BOOLEAN);
        $assistantKeywordIntentRouting = $currentTeam
            && filter_var($currentTeam->getSetting('assistant_keyword_intent_routing', false), FILTER_VALIDATE_BOOLEAN);
        $showAssistantConversations = $currentTeam
            ? filter_var($currentTeam->getSetting('chat_show_assistant_conversations', false), FILTER_VALIDATE_BOOLEAN)
            : false;
        $showWhatsAppConversations = $currentTeam
            ? filter_var($currentTeam->getSetting('chat_show_whatsapp_conversations', true), FILTER_VALIDATE_BOOLEAN)
            : true;
        $canManageChatTeamSidebarSettings = auth()->check()
            && $currentTeam
            && auth()->user()->hasAnyRole(['admin', 'root']);

        $siteAssistantSelectedKey = '';
        $siteAssistantPromptOptions = [];
        $siteAssistantCatalog = [];
        if ($currentTeam)
        {
            $siteAssistant = app(TeamSiteAssistantPromptService::class);
            $siteAssistantSelectedKey = $siteAssistant->selectedRoutingKey($currentTeam) ?? '';
            $siteAssistantPromptOptions = $siteAssistant->promptOptions($currentTeam);
            $siteAssistantCatalog = app(AssistantPromptCatalog::class)->groupsFor($currentTeam);
        }

        $assistantFlowPrompts = collect();
        if (auth()->check() && auth()->user()->currentTeam)
        {
            $assistantFlowPrompts = Prompt::forTeam((int) auth()->user()->currentTeam->id)
                ->active()
                ->with('module')
                ->where('section_key', '!=', 'general')
                ->orderBy('order')
                ->get()
                ->map(fn (Prompt $p) => [
                    'routing_key' => $p->module
                        ? $p->module->key.':'.$p->section_key
                        : $p->section_key,
                    'section_label' => $p->section_label,
                ]);
        }

        $contactStatuses = auth()->check()
            ? ContactStatus::query()->orderBy('id')->get(['id', 'name'])
            : collect();

        $leadContactStatusId = auth()->check()
            ? $this->resolveLeadContactStatusId()
            : null;

        $whatsappSession = (! ($viewAssistant ?? false) && filled($selectedPhone))
            ? app(WhatsAppCustomerServiceWindow::class)->describe((string) $selectedPhone)
            : ['open' => true, 'last_inbound_at' => null];

        $chatMessageAvatars = ChatMessageAvatar::contextForChat(
            viewAssistant: (bool) $viewAssistant,
            authUser: auth()->user(),
            assistantConversationUser: $selectedAssistantUser,
            contactUser: $selectedUser,
            selectedContact: $selectedContact,
            selectedPhone: $selectedPhone,
        );

        return view('chat.index', compact('contacts', 'messages', 'selectedPhone', 'selectedUser', 'hasContact', 'selectedContact', 'users', 'viewAssistant', 'assistantMessages', 'assistantClients', 'selectedAssistantUser', 'clientRecipientPhone', 'assistantClientPhoneDisplay', 'assistantContactId', 'userChatAiToggleDefault', 'contactChatAiToggleDefault', 'whatsappDriver', 'whatsappStatus', 'teamWhatsAppNumber', 'teamWhatsAppNumberFormatted', 'teamWhatsAppIsConnected', 'qrImageUrl', 'assistantAutoRespond', 'assistantAutoRespondAdminsWhenOff', 'assistantChatStub', 'assistantKeywordIntentRouting', 'showAssistantConversations', 'showWhatsAppConversations', 'canManageChatTeamSidebarSettings', 'siteAssistantSelectedKey', 'siteAssistantPromptOptions', 'siteAssistantCatalog', 'assistantFlowPrompts', 'contactStatuses', 'leadContactStatusId', 'chatMessageAvatars', 'whatsappSession'));
    }

    /**
     * Get phone for WhatsApp (recipient field) from a user.
     * Uses Contact (team_id + phone) — when someone writes, we save them in contacts with the team_id of the number they wrote to.
     */
    private function getWhatsAppPhoneForUser(User $user): string
    {
        $phone = null;
        if (auth()->check() && auth()->user()->currentTeam)
        {
            $contact = Contact::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('team_id', auth()->user()->currentTeam->id)
                ->first();
            if ($contact && $contact->phone)
            {
                $phone = preg_replace('/[^0-9]/', '', (string) $contact->phone);
            }
        }
        if (($phone === null || $phone === '') && $user->phone !== null)
        {
            $phone = preg_replace('/[^0-9]/', '', (string) $user->phone);
        }
        if ($phone !== null && $phone !== '' && ! str_starts_with($phone, 'whatsapp:'))
        {
            $phone = 'whatsapp:'.ltrim($phone, '+');
        }

        return $phone ?? '';
    }

    /**
     * Whether the current user can view the assistant conversation for the given user_id (same user, same team, or placeholder client).
     */
    private function canViewAssistantConversation(int $userId): bool
    {
        if ($userId === auth()->id())
        {
            return true;
        }
        $user = User::withoutGlobalScopes()->with('teams')->find($userId);
        if (! $user)
        {
            return false;
        }
        $currentTeam = auth()->user()->currentTeam;
        if ($currentTeam && $user->teams->contains('id', $currentTeam->id))
        {
            return true;
        }
        if ($user->teams->isEmpty())
        {
            return true;
        }

        return false;
    }

    /**
     * Users that have an assistant conversation (for sidebar list). Scoped to current team so each team only sees its own.
     */
    private function getAssistantClientsList()
    {
        $teamId = auth()->check() && auth()->user()->currentTeam ? auth()->user()->currentTeam->id : null;
        $query = \App\Models\AgentConversation::whereHas('messages', fn ($q) => $q->where('agent', AgentConversationContextService::AGENT_NAME));
        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        } else
        {
            $query->whereNull('team_id');
        }
        $userIds = $query->distinct()->pluck('user_id');

        if ($userIds->isEmpty())
        {
            return collect();
        }

        return User::withoutGlobalScopes()
            ->with('teams')
            ->whereIn('id', $userIds)
            ->get()
            ->filter(function (User $u)
            {
                if ($u->id === auth()->id())
                {
                    return true;
                }
                $currentTeam = auth()->user()->currentTeam;
                if ($currentTeam && $u->teams->contains('id', $currentTeam->id))
                {
                    return true;
                }
                if ($u->teams->isEmpty())
                {
                    return true;
                }

                return false;
            })
            ->values();
    }

    /**
     * Get user by phone number
     * This handles extracting the digits from WhatsApp format and finding the user
     */
    private function getUserByPhone($phoneNumber)
    {
        // Clean the phone number (remove whatsapp: prefix, plus sign, and any non-digits)
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Always try to get user directly by phone (full number)
        $user = User::where('phone', $cleanNumber)->first();
        if ($user)
        {
            $user->user_photo = $user->profile_photo_path;

            return $user;
        }

        // Only try without-country-code for Spanish numbers (34 + 9 digits) to avoid false matches
        if (strlen($cleanNumber) === 11 && str_starts_with($cleanNumber, '34'))
        {
            $withoutCountryCode = substr($cleanNumber, -9);
            $user = User::where('phone', $withoutCountryCode)->first();
            if ($user)
            {
                $user->user_photo = $user->profile_photo_path;

                return $user;
            }
        }

        // If no user found directly, try to find through contact relationship
        $contact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
            ->whereHas('sources', function ($query) use ($cleanNumber)
            {
                $query->where('source_id', 2) // Phone source
                    ->where('value', $cleanNumber);
            })->first();

        if ($contact && $contact->user)
        {
            $user = $contact->user;
            $user->user_photo = $user->profile_photo_path;

            return $user;
        }

        return null;
    }

    /**
     * Resolve CRM contact by team + phone digits when there is no User linked to the number.
     * Enables contact_id for the assistant (balance/collections context) for WhatsApp-only clients.
     *
     * @param  string  $digits  Normalized digits only (e.g. 722372858 or 34722372858).
     */
    private function findContactForTeamByChatPhone(int $teamId, string $digits): ?Contact
    {
        if ($digits === '')
        {
            return null;
        }

        return app(UserResolverService::class)->findContactInTeamByPhone($teamId, $digits);
    }

    public function quickReplies(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'items' => app(InboxQuickReplyService::class)->catalog(),
        ]);
    }

    /**
     * @param  array{key: string, argument: ?string}  $quickReply
     */
    private function sendInboxQuickReply(Request $request, WhatsAppGateway $gateway, array $quickReply): \Illuminate\Http\JsonResponse
    {
        $team = auth()->user()?->currentTeam;
        if ($team === null)
        {
            return response()->json(['success' => false, 'error' => __('No team context.')], 403);
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $request->input('to', '')) ?? '';
        $contact = $request->filled('contact_id')
            ? Contact::withoutGlobalScopes()->where('team_id', $team->id)->find((int) $request->input('contact_id'))
            : $this->findContactForTeamByChatPhone((int) $team->id, $digits);

        $resolved = app(InboxQuickReplyService::class)->resolve(
            $team,
            $quickReply['key'],
            $quickReply['argument'],
            $contact,
        );
        if (! ($resolved['ok'] ?? false))
        {
            return response()->json([
                'success' => false,
                'error' => (string) ($resolved['error'] ?? __('No se pudo armar la respuesta.')),
            ], 422);
        }

        if ($resolved['silent'] ?? false)
        {
            return response()->json([
                'success' => true,
                'message' => (string) ($resolved['notice'] ?? 'Listo.'),
                'quick_reply' => $quickReply['key'],
                'messages_sent' => 0,
                'notice' => (string) ($resolved['notice'] ?? ''),
            ]);
        }

        $to = (string) $request->input('to');
        $mediaPath = trim((string) ($resolved['media'] ?? ''));
        $sent = 0;

        foreach ($resolved['messages'] as $index => $bubble)
        {
            if ($index > 0 && ! app()->environment('testing'))
            {
                usleep(450_000);
            }

            if ($index === 0 && $mediaPath !== '' && $gateway->sendMedia($to, $mediaPath, $bubble))
            {
                $this->recordInboxQuickReplyMedia($team, $to, $bubble, $mediaPath, $quickReply['key']);
                $sent++;

                continue;
            }

            $gateway->sendMessage(
                $to,
                $bubble,
                [
                    'source' => 'inbox_quick_reply',
                    'quick_reply' => $quickReply['key'],
                ],
                auth()->id(),
            );
            $sent++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent',
            'quick_reply' => $quickReply['key'],
            'messages_sent' => $sent,
        ]);
    }

    private function recordInboxQuickReplyMedia(Team $team, string $to, string $caption, string $mediaPath, string $slash): void
    {
        $cleanTo = preg_replace('/[^0-9]/', '', $to) ?? '';
        $cleanFrom = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom()) ?? '';
        $url = preg_match('#^https?://#i', $mediaPath) === 1 ? $mediaPath : asset($mediaPath);
        $name = basename(parse_url($mediaPath, PHP_URL_PATH) ?: 'producto.jpg');

        Conversation::create([
            'message_sid' => 'wa_producto_'.uniqid('', true),
            'channel' => 'whatsapp',
            'from' => $cleanFrom,
            'to' => $cleanTo,
            'body' => $caption,
            'status' => 'sent',
            'direction' => 'outbound',
            'media' => [[
                'url' => $url,
                'content_type' => 'image/jpeg',
                'name' => $name !== '' ? $name : 'producto.jpg',
            ]],
            'user_id' => auth()->id(),
            'metadata' => [
                'source' => 'inbox_quick_reply',
                'quick_reply' => $slash,
            ],
        ]);
    }

    public function getMessages(Request $request, $phone)
    {
        $allowedPhones = $this->allowedExternalPhonesForChat();
        $normPhone = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($allowedPhones !== null && ! in_array($normPhone, $allowedPhones, true))
        {
            return response()->json(['messages' => []], 403);
        }

        $messages = $this->conversationQueryForTeam()
            ->where(function ($query) use ($phone)
            {
                $this->applyConversationPhoneFilter($query, (string) $phone);
            })
            ->orderBy('created_at')
            ->get();

        $messages = $this->mergePendingScheduledMessages($messages, (string) $phone);

        $norm = $this->normalizePhoneForList((string) $phone);
        $this->conversationQueryForTeam()
            ->where('direction', 'inbound')
            ->where('status', 'received')
            ->where(function ($q) use ($norm)
            {
                $q->where('from', $norm)->orWhere('from', 'like', $norm.':%');
            })
            ->update(['status' => 'read']);

        $team = auth()->check() ? auth()->user()->currentTeam : null;
        if ($team)
        {
            Cache::forget('inbound_received_count_team_'.$team->id);
        }

        $userIds = $messages->pluck('user_id')->filter()->unique();
        $messageUsers = User::whereIn('id', $userIds)->get()->keyBy('id');
        $authUser = auth()->user();
        $crm = $team && $normPhone !== '' ? $this->findContactForTeamByChatPhone((int) $team->id, $normPhone) : null;
        $usageById = $this->whatsAppThreadUsageByMessageId($messages, $team);

        $mapped = $messages->map(function ($message) use ($messageUsers, $authUser, $usageById)
        {
            if (! empty($message->is_scheduled))
            {
                $fromAssistant = $this->whatsAppMessageIsFromAssistant($message);

                return [
                    'id' => $message->id,
                    'direction' => 'outbound',
                    'channel' => $message->channel ?: 'whatsapp',
                    'body' => $message->body,
                    'status' => 'scheduled',
                    'is_scheduled' => true,
                    'scheduled_at' => $message->scheduled_at?->toIso8601String(),
                    'scheduled_message_id' => $message->scheduled_message_id,
                    'created_at' => $message->scheduled_at?->toIso8601String(),
                    'user_id' => $message->user_id,
                    'media' => [],
                    'transcribed_audio' => false,
                    'from_assistant' => $fromAssistant,
                    'sender_avatar' => $this->whatsAppMessageSenderAvatar($message, $messageUsers, $authUser),
                    'usage' => $usageById[$message->id] ?? null,
                ];
            }

            $payload = $message->toArray();
            $payload['channel'] = $message->channel ?: 'whatsapp';
            $payload['from_assistant'] = $this->whatsAppMessageIsFromAssistant($message);
            $payload['sender_avatar'] = $this->whatsAppMessageSenderAvatar($message, $messageUsers, $authUser);
            $payload['transcribed_audio'] = $message instanceof Conversation
                ? $message->isTranscribedAudio()
                : false;
            $payload['usage'] = $usageById[$message->id] ?? null;

            return $payload;
        })->values()->all();

        if ($team && $crm)
        {
            $mapped = array_merge(
                $mapped,
                app(SiteAssistantConversationService::class)->presentThreadMessagesForContact($team, (int) $crm->id),
            );
            usort($mapped, function (array $left, array $right): int
            {
                return (strtotime((string) ($left['created_at'] ?? '')) ?: 0)
                    <=> (strtotime((string) ($right['created_at'] ?? '')) ?: 0);
            });
        }

        return response()->json([
            'messages' => $mapped,
            'thread_assistant' => $this->whatsAppThreadAssistantMetaForDigits($normPhone, $crm),
            'thread_categories' => app(WhatsAppThreadCategoryService::class)->present($team, $crm),
            'thread_contact' => $this->whatsAppThreadContactMeta($team, $crm, $normPhone),
            'thread_clock' => $this->whatsAppThreadClock($team, $normPhone),
            'whatsapp_session' => app(WhatsAppCustomerServiceWindow::class)->describe($normPhone),
            'reply_target' => $team
                ? app(InboxReplyTargetService::class)->forWhatsAppThread($team, $normPhone, $crm)
                : [
                    'channel' => InboxReplyTargetService::CHANNEL_WHATSAPP,
                    'session_key' => null,
                    'phone' => $normPhone !== '' ? $normPhone : null,
                ],
        ]);
    }

    public function searchWhatsAppContacts(Request $request, WhatsAppInboxContactStarter $starter)
    {
        $user = auth()->user();
        $team = $user?->currentTeam;
        if ($user === null || $team === null)
        {
            return response()->json(['contacts' => []], 401);
        }

        return response()->json([
            'contacts' => $starter->search((int) $team->id, (string) $request->query('q', '')),
        ]);
    }

    public function startWhatsAppContact(StartWhatsAppChatContactRequest $request, WhatsAppInboxContactStarter $starter)
    {
        $user = auth()->user();
        $team = $user?->currentTeam;
        if ($user === null || $team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No team selected.'),
            ], 401);
        }

        $categoryIds = $request->validated('category_ids') ?? [];
        $categoryService = app(WhatsAppThreadCategoryService::class);
        if ($categoryIds !== [] && count($categoryService->assignableIds($team, $categoryIds)) !== count(array_unique(array_map('intval', $categoryIds))))
        {
            return response()->json([
                'success' => false,
                'message' => __('The selected category is invalid.'),
            ], 422);
        }

        $result = $starter->start(
            $user,
            $team,
            (string) $request->validated('name'),
            (string) $request->validated('phone'),
            $request->validated('status_id') !== null ? (int) $request->validated('status_id') : null,
            $categoryIds,
            $request->validated('email'),
        );

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'contact' => $result['contact'],
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Per-contact inbound WhatsApp assistant (same flag as web chat CRM / {@see Contact::allowsInboundChatAssistant}).
     * An empty prompt_key turns the assistant off; a routing key enables it and pins that prompt.
     */
    public function updateWhatsAppContactAssistant(UpdateWhatsAppContactAssistantRequest $request)
    {
        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        $allowedPhones = $this->allowedExternalPhonesForChat();
        $digits = preg_replace('/[^0-9]/', '', $request->string('phone')->toString());
        if ($digits === '')
        {
            return response()->json([
                'success' => false,
                'message' => __('Invalid phone number.'),
            ], 422);
        }
        if ($allowedPhones !== null && ! in_array($digits, $allowedPhones, true))
        {
            return response()->json(['success' => false, 'message' => __('Forbidden')], 403);
        }

        $team = auth()->user()->currentTeam;
        $contact = $this->findContactForTeamByChatPhone((int) $team->id, $digits);
        if (! $contact)
        {
            return response()->json([
                'success' => false,
                'message' => __('No CRM contact is linked to this number. Create or link a contact in Humano to use this option.'),
            ], 422);
        }

        $this->authorize('update', $contact);

        $inboundPolicy = app(TeamInboundAssistantPolicy::class);
        $siteAssistant = app(TeamSiteAssistantPromptService::class);
        $hasPromptKey = $request->exists('prompt_key');
        $promptKey = $hasPromptKey ? trim((string) $request->input('prompt_key', '')) : null;
        $contacts = app(UserResolverService::class)->findContactsInTeamByPhone((int) $team->id, $digits);
        if ($contacts->isEmpty())
        {
            $contacts = collect([$contact]);
        }

        if ($hasPromptKey && $promptKey !== '')
        {
            try
            {
                $promptKey = app(AssistantPromptCatalog::class)->ensureOnTeam($team, $promptKey);
            } catch (InvalidArgumentException $exception)
            {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            $prompt = Prompt::findByRoutingKey($promptKey, (int) $team->id);
            if (! $prompt || ! $prompt->is_active)
            {
                return response()->json([
                    'success' => false,
                    'message' => __('team_settings.site_assistant.invalid_prompt'),
                ], 422);
            }

            $this->applyContactInboundAssistantPreference($contacts, true, $siteAssistant->routingKeyFor($prompt));
        } elseif ($hasPromptKey)
        {
            $this->applyContactInboundAssistantPreference($contacts, $request->boolean('on', false), null);
        } else
        {
            $enabled = $request->boolean('on');
            $this->applyContactInboundAssistantPreference(
                $contacts,
                $enabled,
                $enabled ? $contact->inboundChatAssistantPromptKey() : null,
            );
        }

        $contact->refresh();

        $inboundUser = $contact->user_id ? User::query()->find($contact->user_id) : null;

        return response()->json(array_merge([
            'success' => true,
            'assistant_contact_enabled' => $contact->allowsInboundChatAssistant(),
        ], $inboundPolicy->presentWhatsAppAssistantState(
            $team,
            $inboundPolicy->autoReplyPreferencesAllow($team, $inboundUser, (int) $team->id, $digits),
            true,
        ), $this->whatsAppThreadPromptMeta($team, $contact)));
    }

    public function updateWhatsAppContactCategories(UpdateWhatsAppContactCategoriesRequest $request)
    {
        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        $allowedPhones = $this->allowedExternalPhonesForChat();
        $digits = preg_replace('/[^0-9]/', '', $request->string('phone')->toString());
        if ($digits === '')
        {
            return response()->json([
                'success' => false,
                'message' => __('Invalid phone number.'),
            ], 422);
        }
        if ($allowedPhones !== null && ! in_array($digits, $allowedPhones, true))
        {
            return response()->json(['success' => false, 'message' => __('Forbidden')], 403);
        }

        $team = auth()->user()->currentTeam;
        $contact = $this->findContactForTeamByChatPhone((int) $team->id, $digits);
        if (! $contact)
        {
            return response()->json([
                'success' => false,
                'message' => __('No CRM contact is linked to this number. Create or link a contact in Humano to use this option.'),
            ], 422);
        }

        $this->authorize('update', $contact);

        $service = app(WhatsAppThreadCategoryService::class);
        $categoryIds = $request->validated('category_ids');
        $valid = $service->assignableIds($team, $categoryIds);
        if (count($valid) !== count(array_unique(array_map('intval', $categoryIds))))
        {
            return response()->json([
                'success' => false,
                'message' => __('The selected category is invalid.'),
            ], 422);
        }

        return response()->json(array_merge(
            ['success' => true],
            $service->assign($team, $contact, $valid),
        ));
    }

    public function storeWhatsAppContactCategory(StoreWhatsAppContactCategoryRequest $request)
    {
        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        $team = auth()->user()->currentTeam;
        $service = app(WhatsAppThreadCategoryService::class);

        try
        {
            $category = $service->findOrCreate($team, $request->validated('name'));
        } catch (InvalidArgumentException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => __($exception->getMessage()),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'category' => $category,
            'available' => $service->catalog($team)['categories'],
        ]);
    }

    public function updateWhatsAppInboxContact(UpdateWhatsAppInboxContactRequest $request, WhatsAppInboxContactStarter $starter)
    {
        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        $allowedPhones = $this->allowedExternalPhonesForChat();
        $digits = preg_replace('/[^0-9]/', '', $request->string('phone')->toString());
        if ($digits === '')
        {
            return response()->json([
                'success' => false,
                'message' => __('Invalid phone number.'),
            ], 422);
        }
        if ($allowedPhones !== null && ! in_array($digits, $allowedPhones, true))
        {
            return response()->json(['success' => false, 'message' => __('Forbidden')], 403);
        }

        $team = auth()->user()->currentTeam;
        $contact = $this->findContactForTeamByChatPhone((int) $team->id, $digits);
        if (! $contact)
        {
            return response()->json([
                'success' => false,
                'message' => __('No CRM contact is linked to this number. Create or link a contact in Humano to use this option.'),
            ], 422);
        }

        $categoryIds = $request->validated('category_ids') ?? [];
        $categoryService = app(WhatsAppThreadCategoryService::class);
        if (count($categoryService->assignableIds($team, $categoryIds)) !== count(array_unique(array_map('intval', $categoryIds))))
        {
            return response()->json([
                'success' => false,
                'message' => __('The selected category is invalid.'),
            ], 422);
        }

        $validated = $request->validated();
        $result = $starter->update(
            auth()->user(),
            $team,
            $contact,
            (string) $validated['name'],
            (int) $validated['status_id'],
            $categoryIds,
            $validated['email'] ?? null,
            array_key_exists('email', $validated),
        );

        return response()->json(array_merge(['success' => true], $result, [
            'thread_contact' => $this->whatsAppThreadContactMeta($team, $contact->fresh(), $digits),
        ]));
    }

    /**
     * @return array{contact_id: int|null, name: string, phone: string, email: string|null, status_id: int|null, statuses: list<array{id: int, name: string}>}
     */
    private function whatsAppThreadContactMeta(?Team $team, ?Contact $contact, string $digits): array
    {
        $catalog = app(WhatsAppThreadCategoryService::class)->catalog($team);
        $name = '';
        if ($contact !== null)
        {
            $name = trim($contact->name.' '.(string) ($contact->surname ?? ''));
        }

        $email = trim((string) ($contact?->email ?? ''));
        if ($email !== '' && str_ends_with(strtolower($email), '@chat.placeholder'))
        {
            $email = '';
        }

        return [
            'contact_id' => $contact !== null ? (int) $contact->id : null,
            'name' => $name,
            'phone' => $digits,
            'email' => $email !== '' ? $email : null,
            'status_id' => $contact?->status_id !== null ? (int) $contact->status_id : null,
            'statuses' => $catalog['statuses'],
        ];
    }

    /**
     * @return array{contact_id: int|null, assistant_contact_enabled: bool, assistant_inbound_enabled: bool, assistant_toggle_available: bool, assistant_plan_active: bool, assistant_locked_reason: string|null, prompt_key: string|null, default_prompt_key: string|null, prompts: list<array{key: string, label: string, section_label: string}>}
     */
    private function whatsAppThreadAssistantMetaForDigits(string $digits, ?Contact $crm = null): array
    {
        $team = auth()->user()?->currentTeam;
        $inboundPolicy = app(TeamInboundAssistantPolicy::class);
        if (! $team || $digits === '')
        {
            return array_merge([
                'contact_id' => null,
                'assistant_contact_enabled' => true,
                'assistant_inbound_enabled' => false,
                'assistant_toggle_available' => false,
                'assistant_plan_active' => false,
                'assistant_locked_reason' => 'plan',
            ], $this->whatsAppThreadPromptMeta(null, null));
        }

        $crm ??= $this->findContactForTeamByChatPhone((int) $team->id, $digits);
        $inboundEnabled = $inboundPolicy->autoReplyPreferencesAllow(
            $team,
            $crm?->user_id ? User::query()->find($crm->user_id) : null,
            (int) $team->id,
            $digits,
        );

        return array_merge([
            'contact_id' => $crm ? (int) $crm->id : null,
            'assistant_contact_enabled' => $crm ? $crm->allowsInboundChatAssistant() : true,
        ], $inboundPolicy->presentWhatsAppAssistantState($team, $inboundEnabled, $crm !== null), $this->whatsAppThreadPromptMeta($team, $crm));
    }

    /**
     * @return array{prompt_key: string|null, default_prompt_key: string|null, prompts: list<array{key: string, label: string, section_label: string, audience: 'customer'|'team', audience_rank: int}>}
     */
    private function whatsAppThreadPromptMeta(?Team $team, ?Contact $contact): array
    {
        $siteAssistant = app(TeamSiteAssistantPromptService::class);
        $prompts = [];
        if ($team)
        {
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
        }

        return [
            'prompt_key' => $contact?->inboundChatAssistantPromptKey(),
            'default_prompt_key' => $team ? $siteAssistant->selectedRoutingKey($team) : null,
            'prompts' => $prompts,
        ];
    }

    /**
     * @param  Contact|iterable<int, Contact>  $contacts
     */
    private function applyContactInboundAssistantPreference(Contact|iterable $contacts, bool $on, ?string $promptKey): void
    {
        $items = $contacts instanceof Contact ? [$contacts] : $contacts;
        foreach ($items as $contact)
        {
            $payload = json_encode($contact->data ?? new \stdClass);
            $data = json_decode($payload ?: '{}', true);
            if (! is_array($data))
            {
                $data = [];
            }
            $data['chat_assistant_ai_enabled'] = $on;
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
    }

    /**
     * Get WhatsApp conversation list as JSON for sidebar polling (live update without page refresh).
     */
    /**
     * Conversation list for the SPA inbox. Accepts `limit`/`offset` so the client can show the most
     * recent threads straight away and pull the tail as it scrolls; without them it returns
     * everything, which is what the older clients expect.
     */
    public function getChatList(Request $request, WhatsAppChatArchiveService $archives)
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:200',
            'offset' => 'sometimes|integer|min:0',
            'archived' => 'sometimes|boolean',
            'category_id' => 'sometimes|integer|min:1',
        ]);

        $index = $this->whatsAppConversationIndex($request);
        $team = auth()->user()?->currentTeam;
        $webByContact = [];
        if ($team)
        {
            $siteAssistant = app(SiteAssistantConversationService::class);
            $webByContact = $siteAssistant->lastActivityByContactId(
                $team,
                $siteAssistant->allowedContactIdsFor($request->user()),
            );
            $index = $this->mergeIdentifiedWebIntoInboxIndex($index, $webByContact, $request);
        }
        $archivedPhones = $team ? $archives->archivedPhoneSet((int) $team->id) : [];
        $archivedCount = 0;
        $archivedUnread = 0;
        foreach ($index as $row)
        {
            if (! isset($archivedPhones[$row['digits']]))
            {
                continue;
            }
            $archivedCount++;
            $archivedUnread += (int) $row['unread'];
        }

        $wantArchived = $request->boolean('archived');
        $index = array_values(array_filter(
            $index,
            fn (array $row): bool => $wantArchived === isset($archivedPhones[$row['digits']]),
        ));

        $total = count($index);
        $unreadTotal = array_sum(array_column($index, 'unread'));

        $limit = $request->filled('limit') ? $request->integer('limit') : null;
        $offset = $request->integer('offset');
        $page = $limit === null ? array_slice($index, $offset) : array_slice($index, $offset, $limit);

        $whatsAppPage = array_values(array_filter(
            $page,
            fn (array $row): bool => ($row['channel'] ?? 'whatsapp') === 'whatsapp',
        ));
        $hydrated = $this->hydrateWhatsAppContacts($whatsAppPage)->keyBy(fn ($contact) => (string) $contact->from);
        $list = [];
        foreach ($page as $row)
        {
            if (($row['channel'] ?? 'whatsapp') !== 'whatsapp')
            {
                $list[] = $this->presentIdentifiedWebInboxRow($row, $archivedPhones);

                continue;
            }

            $c = $hydrated->get((string) $row['digits']);
            if (! $c)
            {
                continue;
            }

            $item = [
                'from' => $c->from,
                'last_message' => $c->last_message ?? '',
                'last_message_time' => $c->last_message_time ?? '',
                'unread_count' => (int) ($c->unread_count ?? 0),
                'is_archived' => isset($archivedPhones[(string) $c->from]),
            ];
            if (! empty($c->user_name))
            {
                $item['user_name'] = $c->user_name;
            }
            $photoUrl = $this->chatListPhotoUrl($c);
            if ($photoUrl !== null)
            {
                $item['user_photo'] = $photoUrl;
            }
            if (isset($c->contact_id) && $c->contact_id !== null)
            {
                $item['contact_id'] = (int) $c->contact_id;
            }
            $item['category_ids'] = array_values(array_map('intval', $c->category_ids ?? []));
            $item['assistant_toggle_available'] = (bool) ($c->assistant_toggle_available ?? false);
            $item['assistant_inbound_enabled'] = (bool) ($c->assistant_inbound_enabled ?? true);
            $item['assistant_contact_enabled'] = (bool) ($c->assistant_contact_enabled ?? true);
            $item['assistant_plan_active'] = (bool) ($c->assistant_plan_active ?? true);
            $item['assistant_locked_reason'] = $c->assistant_locked_reason ?? null;
            if (! empty($c->sentiment) && is_array($c->sentiment))
            {
                $item['sentiment'] = $c->sentiment;
            }
            $contactId = (int) ($c->contact_id ?? 0);
            if ($contactId > 0 && isset($webByContact[$contactId]))
            {
                $web = $webByContact[$contactId];
                $whatsAppAt = isset($c->last_message_at) ? $c->last_message_at->getTimestamp() : 0;
                if ($web['last_at'] >= $whatsAppAt)
                {
                    $item['last_message'] = $web['last_message'];
                    $item['last_message_time'] = $web['last_message_time'];
                    $item['last_channel'] = $web['channel'] ?? InboxReplyTargetService::CHANNEL_WEB;
                } else
                {
                    $item['last_channel'] = InboxReplyTargetService::CHANNEL_WHATSAPP;
                }
                $item['has_web'] = true;
            }

            $list[] = $item;
        }

        $nextOffset = $offset + count($page);

        $catalog = app(WhatsAppThreadCategoryService::class)->catalog(auth()->user()?->currentTeam);

        return response()->json([
            'contacts' => $list,
            'total' => $total,
            'unread_total' => $unreadTotal,
            'archived_count' => $archivedCount,
            'archived_unread' => $archivedUnread,
            'has_more' => $nextOffset < $total,
            'next_offset' => $nextOffset,
            'contact_catalog' => $catalog,
        ]);
    }

    /**
     * @param  list<array{digits: string, phone: string, last_at: int, unread: int, crm: ?Contact}>  $index
     * @param  array<int, array{contact: Contact, last_at: int, last_message: string, last_message_time: string, session_key: string, channel: string}>  $webByContact
     * @return list<array<string, mixed>>
     */
    private function mergeIdentifiedWebIntoInboxIndex(array $index, array $webByContact, Request $request): array
    {
        $remaining = $webByContact;
        foreach ($index as $i => $row)
        {
            $contactId = $row['crm']?->id;
            if (! $contactId || ! isset($remaining[$contactId]))
            {
                continue;
            }

            if ($remaining[$contactId]['last_at'] > $row['last_at'])
            {
                $index[$i]['last_at'] = $remaining[$contactId]['last_at'];
            }
            unset($remaining[$contactId]);
        }

        $filter = $this->resolveWhatsAppListCrmStatusFilter($request);
        $leadStatusId = $filter['mode'] === 'all' ? null : $this->resolveLeadContactStatusId();
        $categoryId = $request->integer('category_id');

        foreach ($remaining as $web)
        {
            $contact = $web['contact'];
            $statusRow = (object) [
                'crm_has_contact' => true,
                'crm_status_id' => $contact->status_id,
            ];
            if ($filter['mode'] !== 'all' && ! $this->contactRowMatchesCrmStatusFilter($statusRow, (int) ($filter['status_id'] ?? 0), $leadStatusId))
            {
                continue;
            }
            if ($categoryId > 0 && ! $this->contactRowMatchesCategoryFilter($contact, $categoryId))
            {
                continue;
            }

            $phone = preg_replace('/[^0-9]/', '', (string) ($contact->phone ?? ''));
            $index[] = [
                'digits' => $phone !== '' ? $phone : 'web-'.$contact->id,
                'phone' => $phone,
                'last_at' => $web['last_at'],
                'unread' => 0,
                'crm' => $contact,
                'channel' => $phone !== '' ? 'web-phone' : 'web',
                'web' => $web,
            ];
        }

        usort($index, fn (array $left, array $right): int => $right['last_at'] <=> $left['last_at']);

        return $index;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $archivedPhones
     * @return array<string, mixed>
     */
    private function presentIdentifiedWebInboxRow(array $row, array $archivedPhones): array
    {
        $web = $row['web'];
        $contact = $web['contact'];
        $name = trim((string) $contact->name.' '.(string) ($contact->surname ?? ''));
        $phone = (string) ($row['phone'] ?? '');
        $item = [
            'from' => $phone !== '' ? (string) ($row['digits'] ?: $phone) : '',
            'last_message' => $web['last_message'],
            'last_message_time' => $web['last_message_time'],
            'unread_count' => 0,
            'is_archived' => $phone !== '' && isset($archivedPhones[$phone]),
            'contact_id' => (int) $contact->id,
            'has_web' => true,
            'assistant_toggle_available' => false,
            'assistant_inbound_enabled' => false,
            'assistant_contact_enabled' => true,
            'assistant_plan_active' => true,
            'assistant_locked_reason' => null,
            'category_ids' => $contact->relationLoaded('categories')
                ? $contact->categories->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [],
        ];
        if ($name !== '')
        {
            $item['user_name'] = $name;
        }
        if ($phone === '')
        {
            $item['channel'] = 'web';
            $item['session_key'] = $web['session_key'];
        }
        $item['last_channel'] = $web['channel'] ?? InboxReplyTargetService::CHANNEL_WEB;

        return $item;
    }

    public function updateWhatsAppChatArchive(UpdateWhatsAppChatArchiveRequest $request, WhatsAppChatArchiveService $archives): \Illuminate\Http\JsonResponse
    {
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return response()->json(['success' => false, 'message' => __('No team found')], 422);
        }

        $phone = WhatsAppInboxContactStarter::normalizeInboxPhone((string) $request->input('phone'));
        if ($phone === '')
        {
            return response()->json(['success' => false, 'message' => __('Invalid phone number.')], 422);
        }

        $archived = $request->boolean('archived');
        if ($archived)
        {
            $archives->archive((int) $team->id, $phone);
        } else
        {
            $archives->unarchive((int) $team->id, $phone);
        }

        return response()->json([
            'success' => true,
            'phone' => $phone,
            'is_archived' => $archived,
        ]);
    }

    public function updateWhatsAppChatRead(UpdateWhatsAppChatReadRequest $request): \Illuminate\Http\JsonResponse
    {
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return response()->json(['success' => false, 'message' => __('No team found')], 422);
        }

        $phone = WhatsAppInboxContactStarter::normalizeInboxPhone((string) $request->input('phone'));
        if ($phone === '')
        {
            return response()->json(['success' => false, 'message' => __('Invalid phone number.')], 422);
        }

        $unread = $request->boolean('unread');
        $query = $this->conversationQueryForTeam()
            ->where('direction', 'inbound')
            ->where(function ($q) use ($phone)
            {
                $this->applyConversationPhoneFilter($q, $phone);
            });

        if ($unread)
        {
            $latestRead = (clone $query)
                ->where('status', 'read')
                ->latest()
                ->first();
            if ($latestRead)
            {
                $latestRead->status = 'received';
                $latestRead->save();
            }
        } else
        {
            (clone $query)->where('status', 'received')->update(['status' => 'read']);
        }

        Cache::forget('inbound_received_count_team_'.$team->id);
        Cache::forget(Conversation::CACHE_KEY_INBOUND_UNREAD);

        $unreadCount = (clone $query)->where('status', 'received')->count();

        return response()->json([
            'success' => true,
            'phone' => $phone,
            'unread' => $unreadCount > 0,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Get assistant conversation history as JSON (for polling / live update). Optional user_id for client view.
     */
    public function assistantHistory(AgentConversationContextService $contextService)
    {
        if (! auth()->check())
        {
            return response()->json(['messages' => []], 401);
        }

        $userId = request()->integer('user_id', 0) ?: null;
        if ($userId && ! $this->canViewAssistantConversation($userId))
        {
            return response()->json(['messages' => []], 403);
        }
        $targetUserId = $userId ?? auth()->id();
        $targetUser = User::withoutGlobalScopes()->find($targetUserId);
        $messages = $contextService->getMessagesForDisplay($targetUserId, 50);
        $avatars = ChatMessageAvatar::contextForChat(
            viewAssistant: true,
            authUser: auth()->user(),
            assistantConversationUser: $targetUserId !== auth()->id() ? $targetUser : null,
            contactUser: null,
            selectedContact: null,
            selectedPhone: null,
        );

        return response()->json([
            'messages' => array_map(fn ($m) => [
                'role' => $m['role'],
                'content' => $m['content'],
                'created_at' => $m['created_at']->toIso8601String(),
            ], $messages),
            'avatars' => $avatars,
        ]);
    }

    /**
     * Archive the active assistant thread and start a blank one (history kept in DB, hidden from UI/context).
     */
    public function resetAssistantContext(AgentConversationContextService $contextService)
    {
        if (! auth()->check())
        {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $userId = request()->integer('user_id', 0) ?: null;
        if ($userId && ! $this->canViewAssistantConversation($userId))
        {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $targetUserId = $userId ?? auth()->id();
        $teamId = auth()->user()->currentTeam?->id;

        $contextService->startFreshAssistantContext($targetUserId, $teamId);

        return response()->json([
            'success' => true,
            'messages' => [],
        ]);
    }

    /**
     * Save per-contact AI assistance preference in {@see Contact::$data} when contact_id is sent;
     * otherwise persist team-level opt-out in team settings (group chat, key chat_ai_assistance_blocked).
     */
    public function updateAiTogglePreference(Request $request)
    {
        $request->validate([
            'on' => 'required|boolean',
            'contact_id' => 'sometimes|nullable|integer',
        ]);

        if (! auth()->check())
        {
            return response()->json(['success' => false], 401);
        }

        $contactId = $request->integer('contact_id') ?: null;
        if ($contactId)
        {
            $contact = Contact::query()->whereKey($contactId)->first();
            if (! $contact)
            {
                return response()->json(['success' => false], 404);
            }

            $this->authorize('update', $contact);

            $enabled = $request->boolean('on');
            $this->applyContactInboundAssistantPreference(
                $contact,
                $enabled,
                $enabled ? $contact->inboundChatAssistantPromptKey() : null,
            );

            return response()->json(['success' => true]);
        }

        if (! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        auth()->user()->currentTeam->setSetting('chat_ai_assistance_blocked', ! $request->boolean('on'), [
            'group' => 'chat',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Save a team “Chat / asistente” setting from the chat sidebar. Requires team admin (or root).
     */
    public function updateChatTeamSettingsSidebar(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string', 'in:assistant_auto_respond,assistant_auto_respond_admins_when_off,notify_new_contact_email,assistant_chat_stub,assistant_keyword_intent_routing,chat_ai_assistance_blocked,chat_show_assistant_conversations,chat_show_whatsapp_conversations'],
            'on' => ['required', 'boolean'],
        ]);

        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        if (! $this->userCanManageChatTeamSidebarSettings(auth()->user()))
        {
            return response()->json(['success' => false], 403);
        }

        $this->applyChatTeamSidebarSetting(
            $request->string('key')->toString(),
            $request->boolean('on'),
        );

        return response()->json(['success' => true]);
    }

    /**
     * Same team prompt picker as the Next inbox (Sin asistente / Router / catalog / team copy).
     */
    public function updateChatTeamSiteAssistantPrompt(Request $request)
    {
        $validated = $request->validate([
            'prompt_key' => ['nullable', 'string', 'max:255'],
        ]);

        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        if (! $this->userCanManageChatTeamSidebarSettings(auth()->user()))
        {
            return response()->json(['success' => false], 403);
        }

        $team = auth()->user()->currentTeam;
        $raw = trim((string) ($validated['prompt_key'] ?? ''));
        $siteAssistant = app(TeamSiteAssistantPromptService::class);
        $catalog = app(AssistantPromptCatalog::class);

        try
        {
            if (str_starts_with($raw, 'catalog:'))
            {
                $key = $catalog->apply($team, substr($raw, strlen('catalog:')));
                $siteAssistant->select($team->fresh(), $key);
            } else
            {
                $siteAssistant->select($team, $raw);
            }
        } catch (InvalidArgumentException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $team->refresh();

        return response()->json([
            'success' => true,
            'selected_key' => $siteAssistant->selectedRoutingKey($team) ?? '',
        ]);
    }

    /**
     * @deprecated Use updateChatTeamSettingsSidebar with key assistant_auto_respond
     */
    public function updateAssistantAutoRespond(Request $request)
    {
        $request->validate(['on' => 'required|boolean']);
        $request->merge(['key' => 'assistant_auto_respond']);

        return $this->updateChatTeamSettingsSidebar($request);
    }

    /**
     * @deprecated Use updateChatTeamSettingsSidebar with key notify_new_contact_email
     */
    public function updateNotificationPreference(Request $request)
    {
        $request->validate(['on' => 'required|boolean']);
        $request->merge(['key' => 'notify_new_contact_email']);

        return $this->updateChatTeamSettingsSidebar($request);
    }

    private function userCanManageChatTeamSidebarSettings(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'root']);
    }

    private function applyChatTeamSidebarSetting(string $key, bool $on): void
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return;
        }

        if (in_array($key, ['assistant_auto_respond', 'notify_new_contact_email'], true))
        {
            $team->setSetting($key, $on ? '1' : '0');

            return;
        }

        if (in_array($key, ['assistant_auto_respond_admins_when_off', 'assistant_chat_stub', 'assistant_keyword_intent_routing', 'chat_ai_assistance_blocked', 'chat_show_assistant_conversations', 'chat_show_whatsapp_conversations'], true))
        {
            $team->setSetting($key, $on, [
                'group' => 'chat',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);
        }
    }

    /**
     * Chat assistant: process message with context from agent_conversations.
     * When recipient (phone) or contact_id is provided, context is that user's conversation; otherwise the auth user's.
     * Accepts optional audio file (multipart): transcribes via Laravel AI and uses as message.
     * When respond_with_audio is true, returns TTS audio (base64) using ElevenLabs.
     */
    public function assistant(Request $request, UserResolverService $userResolver, AgentConversationContextService $contextService, ChatAssistantReplyService $replyService)
    {
        $hasAudio = $request->hasFile('audio');
        $hasAttachments = $request->hasFile('attachments');
        $request->validate([
            'message' => ['required_without_all:audio,attachments', 'nullable', 'string', 'max:16000'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,m4a,webm,ogg,mp4,mpeg', 'max:25600'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:25600', 'mimes:jpg,jpeg,png,webp,gif,pdf,csv,txt,doc,docx,xls,xlsx'],
            'respond_with_audio' => 'nullable|boolean',
            'recipient' => 'nullable|string|max:50',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'template_hashed_id' => 'nullable|string|max:512',
            'flow_routing_key' => 'nullable|string|max:512',
            'preview_only' => 'nullable|boolean',
        ], [
            'audio.mimes' => __('Documento no permitido.'),
            'audio.max' => __('Archivo demasiado pesado.'),
            'attachments.max' => __('Podés adjuntar hasta 10 archivos.'),
            'attachments.*.file' => __('No se pudo leer el archivo.'),
            'attachments.*.mimes' => __('Documento no permitido.'),
            'attachments.*.max' => __('Archivo demasiado pesado.'),
        ]);

        $message = trim((string) $request->input('message', ''));
        if ($hasAudio)
        {
            try
            {
                $transcript = (string) Transcription::fromUpload($request->file('audio'))->generate(provider: Lab::OpenAI);
                $message = $message !== '' ? $message."\n\n[Audio]: ".trim($transcript) : trim($transcript);
            } catch (\Throwable $e)
            {
                Log::warning('Chat assistant transcription failed', ['error' => $e->getMessage()]);

                return response()->json([
                    'success' => false,
                    'message' => __('No se pudo transcribir el audio. Comprueba que OPENAI_API_KEY esté configurada.'),
                ], 422);
            }
        }
        if ($message === '')
        {
            if (! $hasAttachments)
            {
                return response()->json(['success' => false, 'message' => __('El mensaje no puede estar vacío.')], 422);
            }
        }

        if ($hasAttachments)
        {
            $teamId = auth()->user()?->currentTeam?->id;
            $recipientDigits = $request->filled('recipient')
                ? preg_replace('/[^0-9]/', '', (string) $request->input('recipient'))
                : '';
            $sourceName = $recipientDigits !== '' ? 'WhatsApp' : 'Chat';
            $ingestionResult = $this->ingestUploadedDocumentsForAssistant(
                $request,
                (int) ($teamId ?? 0),
                $sourceName,
                $recipientDigits !== '' ? $recipientDigits : null,
            );
            $assistantSummary = $this->buildDocumentIngestionAssistantResponse($ingestionResult['ingestions']);
            session()->forget('assistant_pending_document_action');

            $contextUser = null;
            if ($request->filled('recipient'))
            {
                $contextUser = $userResolver->resolveUserForConversation($request->input('recipient'), $request->input('contact_id'));
            }
            if ($contextUser === null && $request->filled('contact_id'))
            {
                $contextUser = $userResolver->resolveUserForConversation(null, (int) $request->input('contact_id'));
            }
            if ($contextUser === null)
            {
                $contextUser = auth()->user();
            }

            if ($contextUser && $teamId !== null)
            {
                $attachmentNames = collect($request->file('attachments', []))
                    ->filter()
                    ->map(fn ($file) => method_exists($file, 'getClientOriginalName') ? (string) $file->getClientOriginalName() : '')
                    ->filter()
                    ->values()
                    ->all();
                $userMessageForContext = trim($message);
                if ($userMessageForContext === '')
                {
                    $userMessageForContext = '📎 Documento adjunto'.($attachmentNames !== [] ? ': '.implode(', ', $attachmentNames) : '');
                }

                $contextService->persistMessages(
                    $contextUser->id,
                    $userMessageForContext,
                    $assistantSummary,
                    null,
                    [],
                    [],
                    [],
                    [],
                    (int) $teamId,
                    false,
                    null,
                );
            }

            return response()->json([
                'success' => true,
                'response' => $assistantSummary,
                'action_performed' => 'document_ingestion',
                'document_ingestion' => true,
                'documents_registered' => $ingestionResult['count'],
            ]);
        }

        if ($request->filled('template_hashed_id'))
        {
            $template = \App\Models\Template::findByHash($request->input('template_hashed_id'));
            if ($template && auth()->check() && auth()->user()->currentTeam && (int) $template->team_id === (int) auth()->user()->currentTeam->id)
            {
                $message = sprintf(
                    'Estoy editando la plantilla «%s» (id: %d). Mi solicitud: %s',
                    $template->name,
                    $template->id,
                    $message,
                );
            }
        }

        $contextUser = null;

        if ($request->filled('recipient'))
        {
            $contextUser = $userResolver->resolveUserForConversation($request->input('recipient'), $request->input('contact_id'));
        }

        if ($contextUser === null && $request->filled('contact_id'))
        {
            $contextUser = $userResolver->resolveUserForConversation(null, (int) $request->input('contact_id'));
        }

        if ($contextUser === null && (! $request->filled('recipient') || $request->boolean('preview_only')))
        {
            if (! auth()->check())
            {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }
            $contextUser = auth()->user();
        }

        if ($contextUser === null)
        {
            return response()->json(['success' => false, 'message' => 'Could not resolve user for conversation'], 400);
        }

        $teamId = auth()->user()?->currentTeam?->id;
        $pendingDecisionResponse = $this->tryHandlePendingDocumentDecision(
            $message,
            $contextUser,
            $contextService,
            $teamId,
            $hasAudio,
        );
        if ($pendingDecisionResponse !== null)
        {
            return response()->json($pendingDecisionResponse);
        }

        $history = $contextService->getHistoryForPrompt($contextUser->id, AgentConversationContextService::DEFAULT_HISTORY_LIMIT);

        if ($teamId !== null && auth()->check() && ! $request->boolean('preview_only'))
        {
            $adminAutoRespondCommand = $this->tryHandleAdminTeamAssistantAutoRespondChatCommand(
                $message,
                auth()->user(),
                (int) $teamId,
                $contextUser,
                $contextService,
                $hasAudio,
            );
            if ($adminAutoRespondCommand !== null)
            {
                if (($adminAutoRespondCommand['success'] ?? false) === false && ($adminAutoRespondCommand['_http_status'] ?? 200) === 403)
                {
                    $status = 403;
                    unset($adminAutoRespondCommand['_http_status']);

                    return response()->json($adminAutoRespondCommand, $status);
                }
                unset($adminAutoRespondCommand['_http_status']);

                return response()->json($adminAutoRespondCommand);
            }

            $slashOutreach = app(AdminProactiveOutreachSlashDispatcher::class)->tryWebAssistantMessage(
                $message,
                auth()->user(),
                (int) $teamId,
                $contextUser,
                $hasAudio,
            );
            if ($slashOutreach !== null)
            {
                $httpStatus = (int) ($slashOutreach['_http_status'] ?? 200);
                unset($slashOutreach['_http_status']);

                return response()->json($slashOutreach, $httpStatus >= 400 ? $httpStatus : 200);
            }

            $slashInsight = app(PerformanceInsightSlashDispatcher::class)->tryWebAssistantMessage(
                $message,
                auth()->user(),
                (int) $teamId,
                $contextUser,
                $hasAudio,
            );
            if ($slashInsight !== null)
            {
                $httpStatus = (int) ($slashInsight['_http_status'] ?? 200);
                unset($slashInsight['_http_status']);

                return response()->json($slashInsight, $httpStatus >= 400 ? $httpStatus : 200);
            }

            $uid = (int) $teamId;
            $sheetReply = app(WhatsAppInvoiceSheetImportService::class)->tryHandle($message, auth()->user(), $uid)
                ?? app(WhatsAppContactSheetImportService::class)->tryHandle($message, auth()->user(), $uid)
                ?? app(WhatsAppTaskSheetImportService::class)->tryHandle($message, auth()->user(), $uid);
            if ($sheetReply !== null)
            {
                $contextService->persistMessages(
                    $contextUser->id,
                    $message,
                    $sheetReply,
                    null,
                    [],
                    [],
                    [],
                    [],
                    $teamId,
                    false,
                    null,
                );

                $payload = [
                    'success' => true,
                    'response' => $sheetReply,
                    'action_performed' => 'sheet_import',
                ];
                if ($hasAudio)
                {
                    $payload['transcript'] = $message;
                }

                return response()->json($payload);
            }
        }

        // Enable tools whenever the user has a team so the assistant can access contacts, tasks, etc. (even from a contact chat)
        $withTools = $teamId !== null;
        $customerPhone = $request->filled('recipient')
            ? preg_replace('/[^0-9]/', '', (string) $request->input('recipient'))
            : null;
        if (($customerPhone === null || $customerPhone === '') && $contextUser)
        {
            $fromUser = preg_replace('/[^0-9]/', '', $this->getWhatsAppPhoneForUser($contextUser));
            $customerPhone = $fromUser !== '' ? $fromUser : null;
        }
        $forcedFlowRoutingKey = $request->filled('flow_routing_key') ? trim((string) $request->input('flow_routing_key')) : '';
        $flowAppendix = null;
        $flowSession = null;
        $flowStep = null;
        $flowAutomation = null;
        if ($teamId !== null)
        {
            $flowContext = app(\App\Services\AssistantAutomationRunner::class)->resolveFlowContext(
                (int) $teamId,
                \App\Models\Automation::CHANNEL_CHAT,
                $message,
                'user:'.(string) $contextUser->id,
                $request->filled('automation_slug') ? trim((string) $request->input('automation_slug')) : null,
                $request->filled('automation_id') ? (int) $request->input('automation_id') : null,
                $forcedFlowRoutingKey !== '' ? $forcedFlowRoutingKey : null,
            );

            if (! empty($flowContext['completed']))
            {
                $completedAutomation = $flowContext['automation'] ?? null;
                $completionMessage = trim((string) ($flowContext['completion_message'] ?? ''));
                if ($completionMessage === '')
                {
                    if ($completedAutomation instanceof \App\Models\Automation)
                    {
                        app(\App\Services\AutomationFunnelCompletionNotifier::class)->notifyIfEligible(
                            $completedAutomation,
                            $flowContext['session'] ?? null,
                            $flowContext['step'] ?? null,
                            true,
                        );
                    }

                    $completionMessage = __('Gracias. Hemos completado este flujo. Si necesitás algo más, escribime de nuevo.');
                }

                return response()->json([
                    'success' => true,
                    'response' => $completionMessage,
                    'flow_completed' => true,
                ]);
            }

            $forcedFlowRoutingKey = (string) ($flowContext['prompt_key'] ?? '');
            $flowAppendix = $flowContext['appendix'] ?? null;
            $flowSession = $flowContext['session'] ?? null;
            $flowStep = $flowContext['step'] ?? null;
            $flowAutomation = $flowContext['automation'] ?? null;
        }
        $replyResponse = $replyService->getReply(
            $message,
            $history,
            $teamId,
            $withTools,
            $contextUser->id,
            $customerPhone !== '' ? $customerPhone : null,
            $forcedFlowRoutingKey !== '' ? $forcedFlowRoutingKey : null,
            $request->filled('contact_id') ? (int) $request->input('contact_id') : null,
            $request->boolean('preview_only'),
            null,
            false,
            $flowAppendix,
        );

        if ($flowSession !== null)
        {
            app(\App\Services\AssistantAutomationRunner::class)->markFlowAwaitingReply($flowSession);
            if ($flowAutomation instanceof \App\Models\Automation)
            {
                app(\App\Services\AutomationFunnelCompletionNotifier::class)->notifyIfEligible(
                    $flowAutomation,
                    $flowSession->fresh(),
                    $flowStep instanceof \App\Models\AutomationStep ? $flowStep : null,
                    false,
                );
            }
        }

        $toolResults = is_array($replyResponse['tool_results'] ?? null) ? $replyResponse['tool_results'] : [];
        $serverTaskApply = $teamId !== null && ! $request->boolean('preview_only')
            ? app(AssistantInboundTaskStatusService::class)->tryApplyFromUserMessage(
                $contextUser,
                (int) $teamId,
                $message,
                $history,
                $toolResults,
            )
            : null;

        if ($serverTaskApply !== null)
        {
            $toolResults[] = $serverTaskApply['tool_result'];
            $replyResponse['tool_results'] = $toolResults;
        }

        $serverContactApply = $teamId !== null && ! $request->boolean('preview_only')
            ? app(AssistantInboundContactCreationService::class)->tryApplyFromUserMessage(
                $contextUser,
                (int) $teamId,
                $message,
                $toolResults,
            )
            : null;

        if ($serverContactApply !== null)
        {
            $toolResults[] = $serverContactApply['tool_result'];
            $replyResponse['tool_results'] = $toolResults;
        }

        if (! $replyResponse['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $replyResponse['message'] ?? 'Assistant failed',
            ], 500);
        }

        $previewOnly = $request->boolean('preview_only');
        $assistantText = $replyResponse['text'] ?? '';
        if ($serverTaskApply !== null)
        {
            $task = \App\Models\Task::withoutGlobalScopes()->find($serverTaskApply['update']['task_id']);
            $status = \App\Models\TaskStatus::query()->find($serverTaskApply['update']['status_id']);
            $title = $task?->title ?? __('Task');
            $label = $status?->translated_name ?? $serverTaskApply['update']['status_name'];
            $assistantText = '✅ Listo. La tarea "'.$title.'" quedó en '.$label.'.';
        } elseif ($serverContactApply !== null)
        {
            $assistantText = $serverContactApply['whatsapp_reply'];
        } else
        {
            $assistantText = app(AssistantInboundContactCreationService::class)->applyContactOnlyReplyIfApplicable(
                $message,
                $toolResults,
                $assistantText,
            );
        }
        if ($previewOnly)
        {
            $assistantText = $this->sanitizePreviewAssistantText($assistantText);
        }

        if (! $previewOnly)
        {
            $contextService->persistMessages(
                $contextUser->id,
                $message,
                $assistantText,
                $replyResponse['routed_to'] ?? null,
                $replyResponse['usage'] ?? [],
                $replyResponse['meta'] ?? [],
                $replyResponse['tool_calls'] ?? [],
                $toolResults,
                $teamId,
                (bool) ($replyResponse['assistant_flow_routing_key_specified'] ?? false),
                $replyResponse['assistant_flow_routing_key'] ?? null,
            );
            $contextService->persistCommittedFlowOnContact(
                $request->filled('contact_id') ? (int) $request->input('contact_id') : null,
                $replyResponse,
            );
        }

        $payload = [
            'success' => true,
            'response' => $assistantText,
            'action_performed' => null,
        ];
        if (! $previewOnly && auth()->check())
        {
            $createdMessageId = AssistantCreatedMessageRedirect::extractCreatedMessageIdFromToolResults($toolResults);
            if ($createdMessageId !== null)
            {
                $redirectUrl = AssistantCreatedMessageRedirect::resolveMessageEditUrlForUser(
                    auth()->user(),
                    $createdMessageId,
                );
                if ($redirectUrl !== null)
                {
                    $payload['redirect_url'] = $redirectUrl;
                }
            }

            $taskStatusUpdate = $serverTaskApply !== null
                ? $serverTaskApply['update']
                : AssistantTaskStatusUpdate::extractFromToolResults($toolResults);
            if ($taskStatusUpdate !== null)
            {
                $payload['task_status_update'] = $taskStatusUpdate;
            }
        }
        if ($hasAudio)
        {
            $payload['transcript'] = $message;
        }
        if ($request->boolean('respond_with_audio') && $assistantText !== '' && config('ai.providers.eleven.key'))
        {
            $maxCharsForTts = 1000;
            $textForTts = strlen($assistantText) > $maxCharsForTts ? substr($assistantText, 0, $maxCharsForTts).'…' : $assistantText;
            try
            {
                $audioResponse = Audio::of($textForTts)->generate(provider: Lab::ElevenLabs);
                $payload['audio_base64'] = $audioResponse->audio;
                $payload['audio_mime'] = $audioResponse->mimeType() ?? 'audio/mpeg';
            } catch (\Throwable $e)
            {
                Log::warning('Chat assistant TTS failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json($payload);
    }

    private function ingestUploadedDocumentsForAssistant(
        Request $request,
        int $teamId,
        string $sourceName,
        ?string $recipientDigits = null,
    ): array {
        $uploadedFiles = $request->file('attachments', []);
        if (! is_array($uploadedFiles) || $uploadedFiles === [])
        {
            return ['count' => 0, 'ingestions' => []];
        }

        $media = [];
        foreach ($uploadedFiles as $index => $uploadedFile)
        {
            if ($uploadedFile === null)
            {
                continue;
            }

            $storedPath = $uploadedFile->store('temp/chat-attachments', 'public');
            $media[] = [
                'url' => Storage::url($storedPath),
                'content_type' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
                'name' => $uploadedFile->getClientOriginalName() ?: ('attachment-'.$index),
                'size' => $uploadedFile->getSize(),
            ];
        }

        if ($media === [])
        {
            return ['count' => 0, 'ingestions' => []];
        }

        $conversation = Conversation::create([
            'message_sid' => 'chat_upload_'.uniqid('', true),
            'channel' => $recipientDigits !== null && $recipientDigits !== '' ? 'whatsapp' : 'chat',
            'from' => (string) (auth()->id() ?? '0'),
            'to' => $recipientDigits !== null && $recipientDigits !== '' ? $recipientDigits : 'assistant',
            'body' => '[Documento adjunto]',
            'status' => 'received',
            'direction' => 'inbound',
            'media' => $media,
            'metadata' => ['from_chat_footer' => true],
        ]);

        $ingestions = app(DocumentIngestionService::class)->ingestFromConversationMedia(
            $conversation,
            $sourceName,
            $conversation->message_sid,
            $teamId > 0 ? $teamId : null,
        );

        return [
            'count' => count($ingestions),
            'ingestions' => $ingestions,
        ];
    }

    private function buildDocumentIngestionAssistantResponse(array $ingestions): string
    {
        if ($ingestions === [])
        {
            return 'Recibi tu documento. Lo estoy procesando y podes seguir el estado en Ver documentos.';
        }

        $lines = [
            'Recibi tu documento y ya lo procesé. Esto detecté:',
            '',
        ];

        foreach ($ingestions as $index => $ingestion)
        {
            if (! is_object($ingestion))
            {
                continue;
            }

            $extracted = is_array($ingestion->extracted_data ?? null) ? $ingestion->extracted_data : [];
            $name = trim((string) ($extracted['name'] ?? ''));
            $title = trim((string) ($extracted['title'] ?? ''));
            $company = trim((string) ($extracted['company'] ?? ''));
            $website = trim((string) ($extracted['website'] ?? ''));
            $email = isset($extracted['emails'][0]) ? (string) $extracted['emails'][0] : '';
            $phone = isset($extracted['phones'][0]) ? (string) $extracted['phones'][0] : '';
            $typeLabel = $this->translateDocumentTypeLabel((string) ($ingestion->document_type ?? 'unknown'));

            $lines[] = ($index + 1).') Documento';
            $lines[] = '   - Tipo: '.$typeLabel;
            if ($name !== '')
            {
                $lines[] = '   - Nombre: '.$name;
            }
            if ($title !== '')
            {
                $lines[] = '   - Cargo: '.$title;
            }
            if ($company !== '')
            {
                $lines[] = '   - Empresa: '.$company;
            }
            if ($website !== '')
            {
                $lines[] = '   - Web: '.$website;
            }
            if ($email !== '')
            {
                $lines[] = '   - Email: '.$email;
            }
            if ($phone !== '')
            {
                $lines[] = '   - Teléfono: '.$phone;
            }
            $createdRecordLink = $this->resolveCreatedRecordMarkdownLink($ingestion);
            if ($createdRecordLink !== null)
            {
                $lines[] = '   - Registro creado: '.$createdRecordLink;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function translateDocumentTypeLabel(string $documentType): string
    {
        return match ($documentType)
        {
            'business_card' => 'Tarjeta personal',
            'invoice' => 'Factura',
            'payment_proof' => 'Comprobante de pago',
            default => 'Sin clasificar',
        };
    }

    private function resolveCreatedRecordMarkdownLink(object $ingestion): ?string
    {
        $entityType = (string) ($ingestion->entity_type ?? '');
        $entityId = (int) ($ingestion->entity_id ?? 0);
        if ($entityType === '' || $entityId <= 0)
        {
            return null;
        }

        if ($entityType === Contact::class)
        {
            $url = route('contact.show', $entityId);

            return '[Contacto #'.$entityId.']('.$url.')';
        }

        if ($entityType === \App\Models\Invoice::class)
        {
            $url = route('invoice.show', $entityId);

            return '[Factura #'.$entityId.']('.$url.')';
        }

        if ($entityType === \App\Models\Payment::class)
        {
            $url = route('payments.show', $entityId);

            return '[Pago #'.$entityId.']('.$url.')';
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $ingestions
     */
    private function storePendingDocumentDecisionContext(array $ingestions, int $teamId): void
    {
        $ids = collect($ingestions)
            ->filter(fn ($item) => is_object($item) && isset($item->id))
            ->map(fn ($item) => (int) $item->id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($ids === [] || $teamId <= 0)
        {
            return;
        }

        session([
            'assistant_pending_document_action' => [
                'team_id' => $teamId,
                'ingestion_ids' => $ids,
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function tryHandlePendingDocumentDecision(
        string $message,
        User $contextUser,
        AgentConversationContextService $contextService,
        ?int $teamId,
        bool $hasAudio,
    ): ?array {
        session()->forget('assistant_pending_document_action');

        return null;
    }

    private function normalizeIntentText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function extractCategoryNameFromMessage(string $message): ?string
    {
        if (preg_match('/categor[ií]a\s+["“]?([^"\n\r]+?)["”]?(?:\s*$|[\.!,])/iu', $message, $matches) === 1)
        {
            $name = trim((string) ($matches[1] ?? ''));

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private function resolveTeamContactCategoryByName(int $teamId, string $categoryName): ?Category
    {
        $contactsModuleId = Module::query()->where('key', 'contacts')->value('id');
        $query = Category::query()
            ->where('team_id', $teamId);

        if ($contactsModuleId !== null)
        {
            $query->where('module_id', (int) $contactsModuleId);
        }

        $normalized = mb_strtolower(trim($categoryName));
        $exact = (clone $query)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
        if ($exact !== null)
        {
            return $exact;
        }

        return (clone $query)
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])
            ->orderBy('name')
            ->first();
    }

    /**
     * Keep preview modal focused on the exact text to send.
     * Remove assistant meta wrappers/instructions that should never be shown to operators.
     */
    private function sanitizePreviewAssistantText(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $clean = [];
        $normalize = static function (string $value): string
        {
            $value = mb_strtolower(trim($value));
            $value = str_replace(['*', '`', '_', '"', "'"], '', $value);
            $value = strtr($value, [
                'á' => 'a',
                'à' => 'a',
                'ä' => 'a',
                'â' => 'a',
                'é' => 'e',
                'è' => 'e',
                'ë' => 'e',
                'ê' => 'e',
                'í' => 'i',
                'ì' => 'i',
                'ï' => 'i',
                'î' => 'i',
                'ó' => 'o',
                'ò' => 'o',
                'ö' => 'o',
                'ô' => 'o',
                'ú' => 'u',
                'ù' => 'u',
                'ü' => 'u',
                'û' => 'u',
                'ñ' => 'n',
            ]);
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

            return $value;
        };

        foreach ($lines as $line)
        {
            $trimmed = trim($line);
            if ($trimmed === '' && $clean === [])
            {
                continue;
            }

            if ($trimmed === '---' || strcasecmp($trimmed, 'y esto ---') === 0)
            {
                continue;
            }

            $lower = $normalize($trimmed);
            if (
                str_contains($lower, 'aquí está el **primer mensaje** para enviar al cliente') ||
                str_contains($lower, 'aqui esta el **primer mensaje** para enviar al cliente') ||
                str_contains($lower, 'aqui esta el primer mensaje para enviar al cliente') ||
                str_contains($lower, 'aqui esta el primer mensaje para enviar a') ||
                str_contains($lower, 'primer mensaje** para enviarle a') ||
                str_contains($lower, 'primer mensaje para enviarle a') ||
                str_contains($lower, 'enviá ese mensaje y cuando el cliente responda') ||
                str_contains($lower, 'envia ese mensaje y cuando el cliente responda') ||
                str_contains($lower, 'copiá ese texto y enviáselo') ||
                str_contains($lower, 'copia ese texto y enviaselo') ||
                str_contains($lower, 'cuando responda, continuamos con el **paso') ||
                str_contains($lower, 'cuando responda, continuamos con el paso')
            ) {
                continue;
            }

            $clean[] = $line;
        }

        $greetingStart = null;
        foreach ($clean as $idx => $line)
        {
            $normalizedLine = $normalize($line);
            if (
                str_starts_with($normalizedLine, 'hola ') ||
                str_starts_with($normalizedLine, 'hola,') ||
                str_starts_with($normalizedLine, 'buen dia') ||
                str_starts_with($normalizedLine, 'buenas') ||
                str_starts_with($normalizedLine, 'estimado') ||
                str_starts_with($normalizedLine, 'estimada')
            ) {
                $greetingStart = $idx;
                break;
            }
        }

        if ($greetingStart !== null)
        {
            $clean = array_slice($clean, $greetingStart);
        }

        $sanitized = trim(implode("\n", $clean));

        // Safety: never return empty preview when model actually replied.
        $final = $sanitized !== '' ? $sanitized : trim($text);

        return WhatsAppOutboundText::sanitize($final);
    }

    public function sendMessage(Request $request, WhatsAppGateway $gateway, ChatAssistantReplyService $replyService, UserResolverService $userResolver, AgentConversationContextService $contextService)
    {
        if ($this->currentTeamUsesLocalWhatsApp())
        {
            $teamGateway = $this->getLocalGatewayForCurrentTeam();
            if ($teamGateway !== null)
            {
                $gateway = $teamGateway;
                $connectionStatus = $teamGateway->getConnectionStatus();
                $team = auth()->user()?->currentTeam;
                if ($team && is_array($connectionStatus))
                {
                    TeamWhatsAppConnectionSync::syncLinkedNumberFromGatewayStatus($team, $connectionStatus);
                }
                $status = is_array($connectionStatus) ? (string) ($connectionStatus['status'] ?? '') : '';
                if (! in_array($status, ['connected', 'open'], true))
                {
                    return response()->json([
                        'success' => false,
                        'error' => __('whatsapp.send.error.not_connected'),
                    ], 503);
                }
            }
        }

        if ($rejectedUpload = $this->rejectedPhpUploadResponse($request))
        {
            return $rejectedUpload;
        }

        $hasAudio = $request->hasFile('audio');
        $hasAttachments = $request->hasFile('attachments');
        $request->validate([
            'to' => 'required|string',
            'message' => ['required_without_all:audio,attachments', 'nullable', 'string'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,m4a,webm,ogg,mp4,mpeg', 'max:25600'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:25600', 'mimes:jpg,jpeg,png,webp,gif,pdf,csv,txt,doc,docx,xls,xlsx'],
            'use_ai' => 'boolean',
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'prompt_key' => 'nullable|string|max:255',
        ], [
            'audio.mimes' => __('Documento no permitido.'),
            'audio.max' => __('Archivo demasiado pesado.'),
            'attachments.max' => __('Podés adjuntar hasta 10 archivos.'),
            'attachments.*.file' => __('No se pudo leer el archivo.'),
            'attachments.*.max' => __('Archivo demasiado pesado.'),
            'attachments.*.mimes' => __('Documento no permitido.'),
        ]);

        $message = trim((string) $request->input('message', ''));
        if ($hasAudio && $message === '')
        {
            $message = __('[Mensaje de voz]');
        }

        try
        {
            // Send as media (audio) when audio file is provided
            if ($hasAudio)
            {
                $file = $request->file('audio');
                $path = $file->store('temp/chat', 'public');
                $publicRelativePath = 'storage/'.$path;
                try
                {
                    $sent = $gateway->sendMedia($request->to, $publicRelativePath, $message !== '' ? $message : null);
                    if (! $sent)
                    {
                        return response()->json(['success' => false, 'error' => __('No se pudo enviar el audio.')], 500);
                    }
                } finally
                {
                    Storage::disk('public')->delete($path);
                }
                $contextUser = $userResolver->resolveUserForConversation($request->to, $request->input('contact_id'));
                if ($contextUser !== null && $message !== '')
                {
                    $contextService->persistAgentReply($contextUser->id, $message);
                }

                return response()->json(['success' => true, 'message' => __('Mensaje de voz enviado.')]);
            }

            if ($hasAttachments)
            {
                return $this->sendWhatsAppAttachments($request, $gateway, $userResolver, $contextService, $message);
            }

            $quickReply = app(InboxQuickReplyService::class)->parse($message);
            if ($quickReply !== null)
            {
                return $this->sendInboxQuickReply($request, $gateway, $quickReply);
            }

            // Check if AI assistance was requested
            if ($request->input('use_ai', false))
            {
                // Get chat history for context
                $history = $this->getChatHistory($request->to, 10);
                $teamId = auth()->user()?->currentTeam?->id;
                $withTools = $teamId !== null;
                $toDigits = preg_replace('/[^0-9]/', '', (string) $request->input('to'));
                $replyResponse = $replyService->getReply(
                    $message,
                    $history,
                    $teamId,
                    $withTools,
                    auth()->id(),
                    $toDigits !== '' ? $toDigits : null,
                    null,
                    $request->filled('contact_id') ? (int) $request->input('contact_id') : null,
                    false,
                );

                // If assistant responded successfully, use its response
                if ($replyResponse['success'])
                {
                    $aiMessage = $replyResponse['text'];

                    // Send the AI message
                    $gateway->sendMessage($request->to, $aiMessage);

                    return response()->json([
                        'success' => true,
                        'message' => 'AI assistant message sent',
                        'ai_used' => true,
                        'ai_response' => $aiMessage,
                    ]);
                }

                // If assistant failed, continue with original message
                Log::warning('Chat assistant failed, sending original message: '.($replyResponse['message'] ?? 'Unknown error'));
            }

            // Send original message (agent's reply when toggle is OFF)
            $gateway->sendMessage($request->to, $message, null, auth()->id());

            // Persist agent's reply into conversation context so the AI has it for future turns
            $contextUser = $userResolver->resolveUserForConversation($request->to, $request->input('contact_id'));
            if ($contextUser !== null)
            {
                $contextService->persistAgentReply($contextUser->id, $message);
            }

            return response()->json(['success' => true, 'message' => 'Message sent']);
        } catch (WhatsAppSessionWindowClosedException $e)
        {
            return response()->json([
                'success' => false,
                'error' => WhatsAppSendExceptionPresenter::messageForUser($e),
            ], 422);
        } catch (\Exception $e)
        {
            if (str_contains($e->getMessage(), '63016'))
            {
                return response()->json([
                    'success' => false,
                    'error' => __('whatsapp.send.error.session_window_closed'),
                ], 422);
            }

            Log::warning('Chat sendMessage failed', [
                'user_id' => auth()->id(),
                'team_id' => auth()->user()?->current_team_id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => WhatsAppSendExceptionPresenter::messageForUser($e),
            ], 500);
        }
    }

    /**
     * Send uploaded chat files to WhatsApp and keep them on the thread.
     */
    private function sendWhatsAppAttachments(
        Request $request,
        WhatsAppGateway $gateway,
        UserResolverService $userResolver,
        AgentConversationContextService $contextService,
        string $message,
    ): \Illuminate\Http\JsonResponse {
        $uploadedFiles = $request->file('attachments', []);
        if (! is_array($uploadedFiles))
        {
            $uploadedFiles = [];
        }

        $cleanTo = preg_replace('/[^0-9]/', '', (string) $request->input('to', ''));
        $team = auth()->user()?->currentTeam;
        $cleanFrom = $team ? preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom()) : '';
        $media = [];

        foreach (array_values($uploadedFiles) as $index => $uploadedFile)
        {
            if ($uploadedFile === null)
            {
                continue;
            }

            $storedPath = $uploadedFile->store('chat/attachments', 'public');
            $publicRelativePath = 'storage/'.$storedPath;
            $caption = $index === 0 && $message !== '' ? $message : null;
            $sent = $gateway->sendMedia((string) $request->input('to'), $publicRelativePath, $caption);
            if (! $sent)
            {
                Log::error('Chat WhatsApp attachment send failed', [
                    'user_id' => auth()->id(),
                    'team_id' => $team?->id,
                    'to' => $cleanTo,
                    'path' => $storedPath,
                ]);

                return response()->json(['success' => false, 'error' => __('No se pudo enviar el archivo.')], 500);
            }

            $media[] = [
                'url' => asset('storage/'.$storedPath),
                'content_type' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
                'name' => $uploadedFile->getClientOriginalName() ?: ('attachment-'.$index),
                'size' => $uploadedFile->getSize(),
            ];
        }

        if ($media === [])
        {
            return response()->json(['success' => false, 'error' => __('No se pudo enviar el archivo.')], 422);
        }

        $conversation = Conversation::create([
            'message_sid' => 'wa_attach_'.uniqid('', true),
            'channel' => 'whatsapp',
            'from' => $cleanFrom !== '' ? $cleanFrom : (string) (auth()->id() ?? '0'),
            'to' => $cleanTo,
            'body' => $message,
            'status' => 'sent',
            'direction' => 'outbound',
            'media' => $media,
            'user_id' => auth()->id(),
            'metadata' => ['source' => 'chat_attachments'],
        ]);

        $contextUser = $userResolver->resolveUserForConversation($request->input('to'), $request->input('contact_id'));
        if ($contextUser !== null && $message !== '')
        {
            $contextService->persistAgentReply($contextUser->id, $message);
        }

        return response()->json(['success' => true, 'message' => __('Archivo enviado.')]);
    }

    private function rejectedPhpUploadResponse(Request $request): ?\Illuminate\Http\JsonResponse
    {
        foreach (['attachments', 'audio'] as $field)
        {
            $files = $request->file($field);
            $files = is_array($files) ? $files : ($files !== null ? [$files] : []);

            foreach ($files as $file)
            {
                if (! $file instanceof UploadedFile || $file->isValid())
                {
                    continue;
                }

                if (! in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))
                {
                    continue;
                }

                Log::error('Chat WhatsApp upload rejected by PHP', [
                    'field' => $field,
                    'name' => $file->getClientOriginalName(),
                    'error' => $file->getError(),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => __('Archivo demasiado pesado.'),
                ], 413);
            }
        }

        return null;
    }

    /**
     * Get recent chat history to provide context for the AI
     *
     * @param  string  $phone  The phone number
     * @param  int  $limit  Number of messages to retrieve
     * @return array Conversation history
     */
    private function getChatHistory($phone, $limit = 10)
    {
        return Conversation::where('channel', 'whatsapp')
            ->where(function ($query) use ($phone)
            {
                $query->where('from', $phone)
                    ->orWhere('to', $phone);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->sortBy('created_at')
            ->values()
            ->toArray();
    }

    public function shouldHandleWhatsAppRegistration(string $phone, ?Team $team = null): bool
    {
        return app(WhatsAppInboundContactRegistrationService::class)
            ->shouldHandleRegistration($phone, $team);
    }

    public function hasWhatsAppRegistrationInProgress(string $phone): bool
    {
        return app(WhatsAppInboundContactRegistrationService::class)
            ->hasRegistrationInProgress($phone);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function processRegistration($phone, $message, ?WhatsAppGateway $gateway = null, ?Team $team = null)
    {
        $sender = $gateway ?? app(WhatsAppMessageService::class);

        return app(WhatsAppInboundContactRegistrationService::class)
            ->processRegistration($phone, (string) $message, $sender, $team);
    }

    /**
     * Send a message using WhatsApp templates
     * Used for first contact or when outside the 24-hour window
     */
    public function sendWithTemplate(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string',
            'template' => 'string|nullable',
        ]);

        $twilioService = app(WhatsAppMessageService::class);

        try
        {
            // Determine which template to use
            $defaultTemplate = config('services.twilio.default_template', 'customer_support');
            $templateName = $request->template ?? $defaultTemplate;

            // Adapt the message as a template parameter
            $parameters = ['message' => $request->message];

            // Send using template
            $result = $twilioService->sendWhatsAppTemplate(
                $request->to,
                $templateName,
                $parameters,
            );

            return response()->json([
                'success' => true,
                'message' => 'Template message sent',
                'template_used' => $templateName,
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'tip' => 'Make sure you have approved templates in Twilio',
            ], 500);
        }
    }

    /**
     * Direct endpoint for sending messages with template
     */
    public function sendTemplateMessage(Request $request)
    {
        return $this->sendWithTemplate($request);
    }

    /**
     * JSON endpoint for WhatsApp connection status (used by frontend to poll when not connected).
     * Returns gateway status and the current team's linked number so the UI shows per-team number.
     */
    public function scheduleMessage(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'recipient' => ['required', 'string'],
            'body' => ['required', 'string', 'max:4096'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'channel' => ['nullable', 'string', 'in:whatsapp'],
        ]);

        $team = auth()->user()->currentTeam;

        if (! $team)
        {
            return response()->json(['success' => false, 'message' => __('No team found')], 422);
        }

        $scheduledAt = \Carbon\Carbon::parse($request->input('scheduled_at'));

        $scheduled = ScheduledMessage::create([
            'team_id' => $team->id,
            'scheduled_by_user_id' => auth()->id(),
            'recipient' => preg_replace('/\D/', '', $request->input('recipient')),
            'channel' => $request->input('channel', 'whatsapp'),
            'body' => $request->input('body'),
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);

        SendScheduledMessageJob::dispatch($scheduled->id)->delay($scheduledAt);

        return response()->json($this->scheduledMessagePayload($scheduled->fresh()));
    }

    public function updateScheduledMessage(Request $request, ScheduledMessage $scheduledMessage): \Illuminate\Http\JsonResponse
    {
        $this->authorizeScheduledMessage($scheduledMessage);

        if (! $scheduledMessage->isPending())
        {
            return response()->json([
                'success' => false,
                'message' => __('This scheduled message can no longer be edited.'),
            ], 422);
        }

        $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $scheduledAt = \Carbon\Carbon::parse($request->input('scheduled_at'));

        $scheduledMessage->update([
            'scheduled_at' => $scheduledAt,
        ]);

        SendScheduledMessageJob::dispatch($scheduledMessage->id)->delay($scheduledAt);

        return response()->json($this->scheduledMessagePayload($scheduledMessage->fresh()));
    }

    public function destroyScheduledMessage(ScheduledMessage $scheduledMessage): \Illuminate\Http\JsonResponse
    {
        $this->authorizeScheduledMessage($scheduledMessage);

        if (! $scheduledMessage->isPending())
        {
            return response()->json([
                'success' => false,
                'message' => __('This scheduled message can no longer be deleted.'),
            ], 422);
        }

        $scheduledMessage->markAsCancelled();

        return response()->json(['success' => true]);
    }

    private function authorizeScheduledMessage(ScheduledMessage $scheduledMessage): void
    {
        $team = auth()->user()?->currentTeam;
        abort_unless($team && (int) $scheduledMessage->team_id === (int) $team->id, 403);
    }

    /**
     * @return array{recipient: array{calling_code: string, country: string, label: string, timezone: string}|null, sender: array{calling_code: string, country: string, label: string, timezone: string}|null}
     */
    private function whatsAppThreadClock(?Team $team, string $recipientDigits): array
    {
        return [
            'recipient' => PhoneHelper::clockForPhone($recipientDigits),
            'sender' => PhoneHelper::clockForPhone($team?->getWhatsAppFrom()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduledMessagePayload(?ScheduledMessage $scheduled): array
    {
        if (! $scheduled)
        {
            return ['success' => true];
        }

        $at = $scheduled->scheduled_at;
        $clock = PhoneHelper::clockForPhone($scheduled->recipient);

        return [
            'success' => true,
            'scheduled_at' => $at?->toIso8601String(),
            'scheduled_message_id' => $scheduled->id,
            'recipient_clock' => $clock,
            'scheduled_at_recipient' => ($clock && $at)
                ? $at->copy()->timezone($clock['timezone'])->toIso8601String()
                : null,
        ];
    }

    public function whatsappStatus()
    {
        $driver = WhatsAppDriver::forTeam(auth()->user()?->currentTeam);
        $status = null;
        $number = null;
        $numberFormatted = null;
        $teamNumber = null;
        $teamNumberFormatted = null;
        $isTeamConnected = false;
        if ($driver === 'local' && app()->bound(WhatsAppGateway::class))
        {
            $gateway = $this->getLocalGatewayForCurrentTeam() ?? app(WhatsAppGateway::class);
            $status = $gateway->getConnectionStatus();
            if (is_array($status))
            {
                $number = $status['number'] ?? null;
                $numberFormatted = ($number !== null && $number !== '')
                    ? \App\Helpers\PhoneHelper::formatForDisplayReadable($number)
                    : null;
            }
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $team = auth()->user()->currentTeam;
                if (is_array($status))
                {
                    TeamWhatsAppConnectionSync::syncLinkedNumberFromGatewayStatus($team, $status);
                }
                $teamNumber = $team->getWhatsAppFrom();
                $statusStr = is_array($status) ? ($status['status'] ?? 'disconnected') : 'disconnected';
                $teamNumberFormatted = $teamNumber
                    ? \App\Helpers\PhoneHelper::formatForDisplayReadable($teamNumber)
                    : null;
                $isTeamConnected = $statusStr === 'connected'
                    && $teamNumber !== null
                    && $teamNumber !== ''
                    && $number !== null
                    && $number !== ''
                    && \App\Helpers\PhoneHelper::digitsBelongToSameLine((string) $teamNumber, (string) $number);
            }
        }

        return response()->json([
            'driver' => $driver,
            'status' => (is_array($status) ? ($status['status'] ?? 'disconnected') : 'disconnected'),
            'number' => $number ?? null,
            'numberFormatted' => $numberFormatted ?? null,
            'teamNumber' => $teamNumber ?? null,
            'teamNumberFormatted' => $teamNumberFormatted ?? null,
            'isTeamConnected' => $isTeamConnected,
        ]);
    }

    /**
     * Proxy for WhatsApp QR image (same-origin so it loads on HTTPS).
     * When user is authenticated with a team, passes a signed link token so the Node can associate the connected number with that team.
     */
    public function whatsappQrImage(Request $request)
    {
        if (! $this->currentTeamUsesLocalWhatsApp())
        {
            return $this->missingQrImageResponse();
        }
        $baseUrl = auth()->user()?->currentTeam?->getWhatsAppServiceBaseUrl() ?? rtrim(config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl === '')
        {
            return $this->missingQrImageResponse();
        }
        $url = $baseUrl.'/qr.png';
        $headers = [];
        $hasToken = false;
        $team = auth()->user()?->currentTeam;
        if ($team)
        {
            $url .= (str_contains($url, '?') ? '&' : '?').'team_id='.$team->id;
        }
        if (auth()->check() && $team)
        {
            $token = $team->generateWhatsAppLinkToken();
            $url .= (str_contains($url, '?') ? '&' : '?').'link_token='.urlencode($token);
            $hasToken = true;
        }
        if ($request->query('link_current'))
        {
            $url .= (str_contains($url, '?') ? '&' : '?').'link_current=1';
        }
        try
        {
            $response = \Illuminate\Support\Facades\Http::timeout(8)->connectTimeout(3)->withHeaders($headers)->get($url);
        } catch (\Throwable $e)
        {
            report($e);

            return $this->missingQrImageResponse();
        }
        $body = $response->body();
        $bodyLen = strlen($body);

        if (! $response->successful())
        {
            return $this->missingQrImageResponse();
        }

        if ($bodyLen < 100)
        {
            return $this->missingQrImageResponse();
        }

        return response($body)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * 1x1 transparent PNG (non-QR flows only).
     */
    private function transparentPngResponse(): \Illuminate\Http\Response
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);

        return response($png)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * QR not ready yet — 204 avoids browser console 404 noise while the client polls.
     */
    private function missingQrImageResponse(): \Illuminate\Http\Response
    {
        return response('', 204)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Force the Node service to reconnect and generate a new QR (when disconnected).
     */
    public function whatsappRefreshQr(Request $request)
    {
        if (! $this->currentTeamUsesLocalWhatsApp())
        {
            return $request->expectsJson()
                ? response()->json(['ok' => false], 400)
                : redirect()->route('chat.index');
        }
        $baseUrl = auth()->user()?->currentTeam?->getWhatsAppServiceBaseUrl() ?? rtrim(config('whatsapp.local.base_url', ''), '/');
        $team = auth()->user()?->currentTeam;

        if ($baseUrl === '')
        {
            $err = __('The WhatsApp service URL is not configured for this team.');

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $err], 422)
                : redirect()->route('chat.index')->with('error', $err);
        }

        $refreshUrl = $baseUrl.'/refresh';
        if ($team)
        {
            $refreshUrl .= (str_contains($refreshUrl, '?') ? '&' : '?').'team_id='.$team->id;
        }
        $refreshHttpStatus = null;
        $refreshException = false;
        try
        {
            $refreshResp = \Illuminate\Support\Facades\Http::timeout(10)->get($refreshUrl);
            $refreshHttpStatus = $refreshResp->status();
        } catch (\Throwable $e)
        {
            $refreshException = true;
        }

        if ($refreshException)
        {
            $err = __('Could not reach the WhatsApp service. Check that it is running and the URL is correct.');

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $err], 503)
                : redirect()->route('chat.index')->with('error', $err);
        }

        if ($refreshHttpStatus !== null && $refreshHttpStatus >= 400)
        {
            $err = __('The WhatsApp service returned an error (:status).', ['status' => (string) $refreshHttpStatus]);

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $err], 502)
                : redirect()->route('chat.index')->with('error', $err);
        }

        $message = __('Request sent. Wait a few seconds for the QR code to appear.');

        if ($request->expectsJson())
        {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('chat.index')->with('success', $message);
    }

    /**
     * Start WhatsApp session without /refresh (no credential wipe). Used before polling for the QR image.
     */
    public function whatsappWarmupQr(Request $request)
    {
        if (! $this->currentTeamUsesLocalWhatsApp())
        {
            return response()->json(['ok' => false], 400);
        }

        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['ok' => false], 401);
        }

        $team = auth()->user()->currentTeam;
        $this->authorize('update', $team);

        $baseUrl = $team->getWhatsAppServiceBaseUrl() ?? rtrim(config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl === '')
        {
            return response()->json([
                'ok' => false,
                'message' => __('The WhatsApp service URL is not configured for this team.'),
            ], 422);
        }

        $presentation = TeamWhatsAppChatPresentation::resolveForTeam($team);
        if ($presentation['teamWhatsAppIsConnected'])
        {
            return response()->json(['ok' => true, 'skipped' => 'connected']);
        }

        $status = $presentation['whatsappStatus'] ?? null;
        $statusStr = is_array($status) ? (string) ($status['status'] ?? 'disconnected') : 'disconnected';
        if ($statusStr === 'unreachable')
        {
            return response()->json([
                'ok' => false,
                'message' => __('auth.registration.qr_whatsapp_service_unreachable'),
            ], 503);
        }
        if (in_array($statusStr, ['connected', 'waiting_qr'], true))
        {
            return response()->json(['ok' => true, 'skipped' => $statusStr]);
        }

        $warmupUrl = $baseUrl.'/warmup?team_id='.$team->id;

        try
        {
            $warmupResp = \Illuminate\Support\Facades\Http::timeout(15)->connectTimeout(3)->get($warmupUrl);
        } catch (\Throwable)
        {
            return response()->json([
                'ok' => false,
                'message' => __('auth.registration.qr_whatsapp_service_unreachable'),
            ], 503);
        }

        if (! $warmupResp->successful())
        {
            return response()->json([
                'ok' => false,
                'message' => __('auth.registration.qr_whatsapp_service_unreachable'),
            ], 502);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * End the local WhatsApp session for the current team (Node /logout) and clear the linked number in team settings.
     */
    public function whatsappDisconnect(Request $request)
    {
        if (! $this->currentTeamUsesLocalWhatsApp())
        {
            return $request->expectsJson()
                ? response()->json(['ok' => false], 400)
                : redirect()->route('chat.index');
        }
        $baseUrl = auth()->user()?->currentTeam?->getWhatsAppServiceBaseUrl() ?? rtrim(config('whatsapp.local.base_url', ''), '/');
        $team = auth()->user()?->currentTeam;

        // Cutting the line affects the whole team, so it is not something any member may do.
        if ($team && ! auth()->user()?->canManageTeam($team))
        {
            $err = __('No tenés permiso para desconectar WhatsApp.');

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $err], 403)
                : redirect()->route('chat.index')->with('error', $err);
        }

        if ($baseUrl === '')
        {
            $err = __('The WhatsApp service URL is not configured for this team.');

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $err], 422)
                : redirect()->route('chat.index')->with('error', $err);
        }

        $logoutUrl = $baseUrl.'/logout';
        if ($team)
        {
            $logoutUrl .= (str_contains($logoutUrl, '?') ? '&' : '?').'team_id='.$team->id;
        }
        $httpStatus = null;
        $exception = false;
        try
        {
            $resp = \Illuminate\Support\Facades\Http::timeout(30)->post($logoutUrl);
            $httpStatus = $resp->status();
        } catch (\Throwable $e)
        {
            $exception = true;
        }

        if ($exception)
        {
            $err = __('Could not reach the WhatsApp service. Check that it is running and the URL is correct.');

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $err], 503)
                : redirect()->route('chat.index')->with('error', $err);
        }

        if ($httpStatus !== null && $httpStatus >= 400)
        {
            $err = __('The WhatsApp service returned an error (:status).', ['status' => (string) $httpStatus]);

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $err], 502)
                : redirect()->route('chat.index')->with('error', $err);
        }

        if ($team)
        {
            $team->settings()->where('key', 'whatsapp_from')->delete();
        }

        $message = __('You have been disconnected from WhatsApp for this team.');

        if ($request->expectsJson())
        {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('chat.index')->with('success', $message);
    }

    /**
     * Callback from the Node WhatsApp service when a session connects.
     * Validates the link token (15 min expiry), extracts the team, and saves the connected number as the team's whatsapp_from.
     * Protected by X-Webhook-Secret when configured.
     */
    public function whatsappLinked(Request $request)
    {
        $token = $request->input('link_token') ?? $request->input('token');
        $number = $request->input('number') ?? $request->input('to');

        if (empty($token) || $number === null || $number === '')
        {
            Log::warning('WhatsApp linked callback: missing link_token or number');

            return response()->json(['error' => 'Missing link_token and number'], 422);
        }

        $team = Team::fromWhatsAppLinkToken($token);
        if (! $team)
        {
            Log::warning('WhatsApp linked callback: invalid or expired token');

            return response()->json(['error' => 'Invalid or expired token'], 400);
        }

        $normalized = preg_replace('/[^0-9]/', '', (string) $number);
        if ($normalized === '')
        {
            return response()->json(['error' => 'Invalid number'], 422);
        }

        Team::ensureOnlyTeamHasWhatsAppNumber($team->id, $normalized);
        $team->setSetting('whatsapp_from', $normalized);
        $team->setSetting('assistant_auto_respond', '1');
        Log::info('WhatsApp number linked to team', ['team_id' => $team->id, 'number' => $normalized]);

        return response()->json(['ok' => true, 'team_id' => $team->id]);
    }

    /**
     * Link the number currently connected in the Node service to the current team.
     * Use when auth files exist (e.g. after DB fresh) but team has no whatsapp_from saved.
     */
    public function linkCurrentNumberFromService(Request $request)
    {
        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        if (! $this->currentTeamUsesLocalWhatsApp())
        {
            return response()->json(['ok' => false, 'error' => 'Only for local driver'], 400);
        }

        $number = $request->input('number');
        $normalized = $number !== null && $number !== '' ? preg_replace('/[^0-9]/', '', (string) $number) : '';
        if ($normalized === '')
        {
            return response()->json(['ok' => false, 'error' => 'Missing or invalid number'], 422);
        }

        $gateway = $this->getLocalGatewayForCurrentTeam();
        if (! $gateway)
        {
            return response()->json(['ok' => false, 'error' => 'Gateway not available'], 503);
        }

        $status = $gateway->getConnectionStatus();
        if (! is_array($status) || ($status['status'] ?? '') !== 'connected')
        {
            return response()->json(['ok' => false, 'error' => 'Service not connected'], 400);
        }
        $gatewayNumber = preg_replace('/[^0-9]/', '', (string) ($status['number'] ?? ''));
        if ($gatewayNumber === '' || $gatewayNumber !== $normalized)
        {
            return response()->json(['ok' => false, 'error' => 'Number not connected in service or mismatch'], 400);
        }

        $team = auth()->user()->currentTeam;
        Team::ensureOnlyTeamHasWhatsAppNumber($team->id, $normalized);
        $team->setSetting('whatsapp_from', $normalized);
        Log::info('WhatsApp number re-linked to team from service', ['team_id' => $team->id, 'number' => $normalized]);

        return response()->json(['ok' => true, 'team_id' => $team->id]);
    }

    /**
     * When the message is an exact admin-only slash command, toggle team {@see Team::setSetting()} assistant_auto_respond (same as sidebar "Humano Assistant replies").
     *
     * @return array<string, mixed>|null Null if the message is not a recognized command.
     */
    private function tryHandleAdminTeamAssistantAutoRespondChatCommand(
        string $message,
        User $actor,
        int $teamId,
        User $contextUser,
        AgentConversationContextService $contextService,
        bool $hasAudio,
    ): ?array {
        $normalized = strtolower(preg_replace('/\s+/u', ' ', trim($message)));
        $commands = [
            '/asistente on' => true,
            '/asistente off' => false,
            '/asistente activar' => true,
            '/asistente desactivar' => false,
            '/assistant on' => true,
            '/assistant off' => false,
        ];
        if (! array_key_exists($normalized, $commands))
        {
            return null;
        }

        $turnOn = $commands[$normalized];
        $isPrivileged = $actor->hasRole('admin') || $actor->hasRole('root');
        if (! $isPrivileged)
        {
            return [
                'success' => false,
                'message' => __('Only administrators can use the /asistente command.'),
                '_http_status' => 403,
            ];
        }

        $team = $actor->currentTeam;
        if (! $team || (int) $team->id !== $teamId)
        {
            return [
                'success' => false,
                'message' => __('No team context.'),
                '_http_status' => 403,
            ];
        }

        $team->setSetting('assistant_auto_respond', $turnOn ? '1' : '0');
        $replyText = $turnOn
            ? __('Humano Assistant auto-replies for WhatsApp are now **on** for this team.')
            : __('Humano Assistant auto-replies for WhatsApp are now **off** for this team.');

        $contextService->persistMessages(
            $contextUser->id,
            $message,
            $replyText,
            null,
            [],
            [],
            [],
            [],
            $teamId,
            false,
            null,
        );

        $payload = [
            'success' => true,
            'response' => $replyText,
            'action_performed' => 'assistant_auto_respond_toggle',
            'assistant_auto_respond' => $turnOn,
            '_http_status' => 200,
        ];
        if ($hasAudio)
        {
            $payload['transcript'] = $message;
        }

        return $payload;
    }

    /**
     * @param  Collection<int, Conversation|object>  $messages
     * @return Collection<int, Conversation|object>
     */
    private function mergePendingScheduledMessages(Collection $messages, string $phone): Collection
    {
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return $messages;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '')
        {
            return $messages;
        }

        $pending = ScheduledMessage::query()
            ->where('team_id', $team->id)
            ->where('status', 'pending')
            ->where('recipient', $digits)
            ->orderBy('scheduled_at')
            ->get();

        if ($pending->isEmpty())
        {
            return $messages;
        }

        $scheduledDisplay = $pending->map(fn (ScheduledMessage $scheduled) => $this->scheduledMessageToChatDisplay($scheduled));

        return $messages->concat($scheduledDisplay)
            ->sortBy(fn ($message) => $this->chatDisplayMessageTimestamp($message))
            ->values();
    }

    private function whatsAppMessageIsFromAssistant(object $message): bool
    {
        $direction = (string) ($message->direction ?? '');
        $userId = $message->user_id ?? null;

        return $direction === 'outbound' && empty($userId);
    }

    /**
     * @param  Collection<int, object>  $messages
     * @return array<int|string, array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tool_calls: int, amount_cents: int}>
     */
    private function whatsAppThreadUsageByMessageId(Collection $messages, ?Team $team): array
    {
        $byId = [];
        $needMatch = [];

        foreach ($messages as $message)
        {
            if (! $this->whatsAppMessageIsFromAssistant($message))
            {
                continue;
            }

            $fromMeta = $this->presentWhatsAppTokenUsage(
                is_array($message->metadata ?? null) ? ($message->metadata['token_usage'] ?? null) : null,
                (int) (is_array($message->metadata ?? null) ? ($message->metadata['token_usage']['tool_calls'] ?? 0) : 0),
            );
            if ($fromMeta !== null)
            {
                $byId[$message->id] = $fromMeta;

                continue;
            }

            $body = trim((string) ($message->body ?? ''));
            if ($body !== '')
            {
                $needMatch[$message->id] = $body;
            }
        }

        if ($team === null || $needMatch === [])
        {
            return $byId;
        }

        $rows = AgentConversationMessage::query()
            ->where('role', 'assistant')
            ->whereIn('content', array_values(array_unique($needMatch)))
            ->whereHas('conversation', function ($query) use ($team)
            {
                $query->where('team_id', $team->id);
            })
            ->orderByDesc('created_at')
            ->get(['content', 'usage', 'tool_calls']);

        $latestByBody = [];
        foreach ($rows as $row)
        {
            $content = trim((string) $row->content);
            if ($content === '' || isset($latestByBody[$content]))
            {
                continue;
            }
            $latestByBody[$content] = $row;
        }

        foreach ($needMatch as $id => $body)
        {
            $row = $latestByBody[$body] ?? null;
            if ($row === null)
            {
                continue;
            }

            $toolCalls = is_array($row->tool_calls) ? count($row->tool_calls) : 0;
            $presented = $this->presentWhatsAppTokenUsage($row->usage, $toolCalls);
            if ($presented !== null)
            {
                $byId[$id] = $presented;
            }
        }

        return $byId;
    }

    /**
     * @param  array<string, mixed>|null  $usage
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tool_calls: int, amount_cents: int}|null
     */
    private function presentWhatsAppTokenUsage(mixed $usage, int $toolCalls = 0): ?array
    {
        if (! is_array($usage))
        {
            return null;
        }

        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? 0);
        if ($total <= 0)
        {
            $total = $prompt + $completion;
        }
        if ($total <= 0)
        {
            return null;
        }

        $rate = TeamApiUsageStatsService::sellRatePerMillion();

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'tool_calls' => max(0, $toolCalls),
            'amount_cents' => (int) round(($total / 1_000_000) * $rate * 100),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $messageUsers
     * @return array<string, mixed>
     */
    private function whatsAppMessageSenderAvatar(object $message, $messageUsers, ?User $authUser): array
    {
        if ($this->whatsAppMessageIsFromAssistant($message))
        {
            return ChatMessageAvatar::forAssistant();
        }

        $userId = $message->user_id ?? null;
        $sender = $userId ? ($messageUsers->get($userId) ?? $authUser) : $authUser;

        return ChatMessageAvatar::forUser($sender, 'bg-label-primary');
    }

    private function scheduledMessageToChatDisplay(ScheduledMessage $scheduled): object
    {
        return (object) [
            'id' => 'scheduled-'.$scheduled->id,
            'direction' => 'outbound',
            'body' => $scheduled->body,
            'status' => 'scheduled',
            'is_scheduled' => true,
            'scheduled_at' => $scheduled->scheduled_at,
            'scheduled_message_id' => $scheduled->id,
            'created_at' => $scheduled->scheduled_at,
            'user_id' => $scheduled->scheduled_by_user_id,
            'media' => [],
            'from' => $scheduled->recipient,
            'to' => $scheduled->recipient,
        ];
    }

    private function chatDisplayMessageTimestamp(object $message): int
    {
        if (! empty($message->is_scheduled) && $message->scheduled_at)
        {
            return $message->scheduled_at instanceof \Carbon\CarbonInterface
                ? $message->scheduled_at->getTimestamp()
                : (int) strtotime((string) $message->scheduled_at);
        }

        $created = $message->created_at ?? null;

        return $created instanceof \Carbon\CarbonInterface
            ? $created->getTimestamp()
            : (int) strtotime((string) $created);
    }
}
