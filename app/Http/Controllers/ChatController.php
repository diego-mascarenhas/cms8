<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppGateway;
use App\Helpers\TextHelper;
use App\Helpers\WhatsAppOutboundText;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AdminProactiveOutreachSlashDispatcher;
use App\Services\AgentConversationContextService;
use App\Services\ChatAssistantReplyService;
use App\Services\DocumentIngestionService;
use App\Services\TeamWhatsAppChatPresentation;
use App\Services\UserResolverService;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppContactSheetImportService;
use App\Services\WhatsApp\WhatsAppInvoiceSheetImportService;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppTaskSheetImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * Compare phone numbers allowing common WhatsApp prefix variants by suffix.
     */
    private function phonesBelongToSameLine(string $left, string $right): bool
    {
        $leftDigits = preg_replace('/[^0-9]/', '', $left);
        $rightDigits = preg_replace('/[^0-9]/', '', $right);
        if ($leftDigits === '' || $rightDigits === '')
        {
            return false;
        }
        if ($leftDigits === $rightDigits)
        {
            return true;
        }

        $minLen = min(strlen($leftDigits), strlen($rightDigits));
        if ($minLen < 8)
        {
            return false;
        }

        $suffixLen = min(10, $minLen);

        return substr($leftDigits, -$suffixLen) === substr($rightDigits, -$suffixLen);
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
     * For non-admin users, return list of external phone numbers they are allowed to see (their own + contacts they are responsible for). Null means no restriction (admin).
     *
     * @return array<int, string>|null
     */
    private function allowedExternalPhonesForChat(): ?array
    {
        if (! auth()->check() || auth()->user()->hasRole('admin'))
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
    private function getLocalGatewayForCurrentTeam(): ?WhatsAppGateway
    {
        if (config('whatsapp.driver') !== 'local')
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
        $team = auth()->check() ? auth()->user()->currentTeam : null;
        if (! $team || ! $team->getWhatsAppFrom())
        {
            return collect();
        }

        $query = $this->conversationQueryForTeam();
        $allowedPhones = $this->allowedExternalPhonesForChat();

        $inboundPhones = (clone $query)->where('direction', 'inbound')->distinct()->pluck('from');
        $outboundPhones = (clone $query)->where('direction', 'outbound')->distinct()->pluck('to');
        $allRaw = $inboundPhones->merge($outboundPhones)->filter()->values();

        $team = auth()->check() ? auth()->user()->currentTeam : null;
        $teamNumber = $team ? preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom()) : '';

        $byDigits = [];
        foreach ($allRaw as $raw)
        {
            $norm = $this->normalizePhoneForList((string) $raw);
            $digits = preg_replace('/[^0-9]/', '', $norm);
            if ($digits !== '' && ! isset($byDigits[$digits]))
            {
                $byDigits[$digits] = $norm;
            }
        }
        $normalizedUnique = collect(array_values($byDigits));

        if ($allowedPhones !== null)
        {
            $normalizedUnique = $normalizedUnique->filter(fn ($p) => $p !== $teamNumber && in_array(preg_replace('/[^0-9]/', '', (string) $p), $allowedPhones, true))->values();
        } else
        {
            $normalizedUnique = $normalizedUnique->filter(fn ($p) => preg_replace('/[^0-9]/', '', (string) $p) !== $teamNumber)->values();
        }

        $contacts = collect();
        foreach ($normalizedUnique as $normalizedPhone)
        {
            $digitsOnly = preg_replace('/[^0-9]/', '', (string) $normalizedPhone);
            $lastMessage = (clone $query)
                ->where(function ($q) use ($normalizedPhone)
                {
                    $q->where('from', $normalizedPhone)
                        ->orWhere('to', $normalizedPhone)
                        ->orWhere('from', 'like', $normalizedPhone.':%')
                        ->orWhere('to', 'like', $normalizedPhone.':%');
                })
                ->latest()
                ->first();
            if (! $lastMessage)
            {
                continue;
            }
            $unreadCount = (clone $query)
                ->where('direction', 'inbound')
                ->where('status', 'received')
                ->where(function ($q) use ($normalizedPhone)
                {
                    $q->where('from', $normalizedPhone)
                        ->orWhere('from', 'like', $normalizedPhone.':%');
                })
                ->count();

            $contact = (object) [
                'from' => $digitsOnly,
                'last_message' => $lastMessage->body,
                'last_message_time' => $lastMessage->created_at->diffForHumans(),
                'last_message_at' => $lastMessage->created_at,
                'unread_count' => $unreadCount,
            ];
            $userData = $this->getUserByPhone($normalizedPhone);
            if ($userData)
            {
                $contact->user_name = $userData->name;
                $contact->user_photo = $userData->profile_photo_path;
                $contact->user_id = $userData->id;
            }
            $crmProfile = $this->findContactForTeamByChatPhone((int) $team->id, $digitsOnly);
            $contact->crm_has_contact = $crmProfile !== null;
            $contact->crm_status_id = $crmProfile?->status_id;
            $contacts->push($contact);
        }

        $effectiveRequest = $request ?? request();
        $contacts = $this->applyWhatsAppCrmConversationFilter(
            $contacts,
            $effectiveRequest instanceof Request ? $this->resolveWhatsAppListCrmStatusFilter($effectiveRequest) : ['mode' => 'all'],
            $this->resolveLeadContactStatusId(),
        );

        return $contacts->sortByDesc(function ($c)
        {
            return $c->last_message_at ? $c->last_message_at->timestamp : 0;
        })->values();
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
            $message->body = TextHelper::sanitizeAndLink($message->body);
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

        $contactChatAiToggleDefault = $userChatAiToggleDefault;
        if ($selectedContact)
        {
            $contactData = $selectedContact->data;
            if (is_object($contactData) && property_exists($contactData, 'chat_assistant_ai_enabled'))
            {
                $contactChatAiToggleDefault = filter_var($contactData->chat_assistant_ai_enabled, FILTER_VALIDATE_BOOLEAN);
            }
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

        return view('chat.index', compact('contacts', 'messages', 'selectedPhone', 'selectedUser', 'hasContact', 'selectedContact', 'users', 'viewAssistant', 'assistantMessages', 'assistantClients', 'selectedAssistantUser', 'clientRecipientPhone', 'assistantClientPhoneDisplay', 'assistantContactId', 'userChatAiToggleDefault', 'contactChatAiToggleDefault', 'whatsappDriver', 'whatsappStatus', 'teamWhatsAppNumber', 'teamWhatsAppNumberFormatted', 'teamWhatsAppIsConnected', 'qrImageUrl', 'assistantAutoRespond', 'assistantChatStub', 'assistantKeywordIntentRouting', 'showAssistantConversations', 'showWhatsAppConversations', 'canManageChatTeamSidebarSettings', 'assistantFlowPrompts', 'contactStatuses', 'leadContactStatusId'));
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

        return Contact::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($digits)
            {
                $query->where('phone', $digits);
                if (strlen($digits) === 11 && str_starts_with($digits, '34'))
                {
                    $query->orWhere('phone', substr($digits, -9));
                }
                if (strlen($digits) === 9)
                {
                    $query->orWhere('phone', '34'.$digits);
                }
            })
            ->orderBy('id')
            ->first();
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

        return response()->json(['messages' => $messages]);
    }

    /**
     * Get WhatsApp conversation list as JSON for sidebar polling (live update without page refresh).
     */
    public function getChatList(Request $request)
    {
        $contacts = $this->getWhatsAppContacts($request);
        $list = $contacts->map(function ($c)
        {
            $item = [
                'from' => $c->from,
                'last_message' => $c->last_message ?? '',
                'last_message_time' => $c->last_message_time ?? '',
                'unread_count' => (int) ($c->unread_count ?? 0),
            ];
            if (! empty($c->user_name))
            {
                $item['user_name'] = $c->user_name;
            }
            if (! empty($c->user_photo))
            {
                $item['user_photo'] = Storage::url($c->user_photo);
            }

            return $item;
        })->values()->all();

        return response()->json(['contacts' => $list]);
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
        $messages = $contextService->getMessagesForDisplay($targetUserId, 50);

        return response()->json([
            'messages' => array_map(fn ($m) => [
                'role' => $m['role'],
                'content' => $m['content'],
                'created_at' => $m['created_at']->toIso8601String(),
            ], $messages),
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

            $payload = json_encode($contact->data ?? new \stdClass);
            $data = json_decode($payload ?: '{}', true);
            if (! is_array($data))
            {
                $data = [];
            }
            $data['chat_assistant_ai_enabled'] = $request->boolean('on');
            $contact->data = $data;
            $contact->save();

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
            'key' => ['required', 'string', 'in:assistant_auto_respond,notify_new_contact_email,assistant_chat_stub,assistant_keyword_intent_routing,chat_ai_assistance_blocked,chat_show_assistant_conversations,chat_show_whatsapp_conversations'],
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

        if (in_array($key, ['assistant_chat_stub', 'assistant_keyword_intent_routing', 'chat_ai_assistance_blocked', 'chat_show_assistant_conversations', 'chat_show_whatsapp_conversations'], true))
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
            'audio.mimes' => __('El audio debe ser mp3, wav, m4a, webm u ogg.'),
            'audio.max' => __('El audio no puede superar 25 MB.'),
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

        if ($contextUser === null && ! $request->filled('recipient'))
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
        );

        if (! $replyResponse['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $replyResponse['message'] ?? 'Assistant failed',
            ], 500);
        }

        $previewOnly = $request->boolean('preview_only');
        $assistantText = $replyResponse['text'] ?? '';
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
                $replyResponse['tool_results'] ?? [],
                $teamId,
                (bool) ($replyResponse['assistant_flow_routing_key_specified'] ?? false),
                $replyResponse['assistant_flow_routing_key'] ?? null,
            );
        }

        $payload = [
            'success' => true,
            'response' => $assistantText,
            'action_performed' => null,
        ];
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
        if (config('whatsapp.driver') === 'local')
        {
            $teamGateway = $this->getLocalGatewayForCurrentTeam();
            if ($teamGateway !== null)
            {
                $gateway = $teamGateway;
            }
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
        ], [
            'audio.mimes' => __('El audio debe ser mp3, wav, m4a, webm u ogg.'),
            'audio.max' => __('El audio no puede superar 25 MB.'),
        ]);

        $message = trim((string) $request->input('message', ''));
        if ($hasAudio && $message === '')
        {
            $message = __('[Mensaje de voz]');
        }

        try
        {
            // Check if this number needs registration
            $registrationResponse = $this->processRegistration($request->to, $message);
            if ($registrationResponse)
            {
                return response()->json([
                    'success' => true,
                    'registration' => true,
                    'message' => $registrationResponse['message'],
                ]);
            }

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
                $recipientDigits = preg_replace('/[^0-9]/', '', (string) $request->input('to', ''));
                $ingestionResult = $this->ingestUploadedDocumentsForAssistant(
                    $request,
                    (int) (auth()->user()?->currentTeam?->id ?? 0),
                    $recipientDigits !== '' ? 'WhatsApp' : 'Chat',
                    $recipientDigits !== '' ? $recipientDigits : null,
                );
                $assistantSummary = $this->buildDocumentIngestionAssistantResponse($ingestionResult['ingestions']);

                return response()->json([
                    'success' => true,
                    'message' => $assistantSummary,
                    'document_ingestion' => true,
                    'documents_registered' => $ingestionResult['count'],
                ]);
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
            $gateway->sendMessage($request->to, $message);

            // Persist agent's reply into conversation context so the AI has it for future turns
            $contextUser = $userResolver->resolveUserForConversation($request->to, $request->input('contact_id'));
            if ($contextUser !== null)
            {
                $contextService->persistAgentReply($contextUser->id, $message);
            }

            return response()->json(['success' => true, 'message' => 'Message sent']);
        } catch (\Exception $e)
        {
            // If it fails because it's outside the 24-hour window, try sending with template
            if (strpos($e->getMessage(), '63016') !== false)
            {
                return $this->sendWithTemplate($request);
            }

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
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

    /**
     * Check if the phone has a registration in progress and process accordingly
     *
     * @param  string  $phone  The phone number
     * @param  string  $message  The user message
     * @param  WhatsAppGateway|null  $gateway  Optional gateway for sending (e.g. local webhook)
     * @return array|null Response to send or null if no registration in progress
     */
    public function processRegistration($phone, $message, ?WhatsAppGateway $gateway = null)
    {
        $sender = $gateway ?? app(WhatsAppMessageService::class);
        $lastMessage = Conversation::where('to', $phone)
            ->where('channel', 'whatsapp')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastMessage)
        {
            return null;
        }

        $metadata = $lastMessage->metadata ?? [];
        $registrationStep = $metadata['registration_step'] ?? null;

        // If no registration in progress, start one
        if (! $registrationStep)
        {
            // Check if the user exists first
            $user = $this->getUserByPhone($phone);
            if (! $user)
            {
                $response = "¡Bienvenido a nuestra mesa de ayuda!\nVemos que aún nuca nos has escrito por aquí.\n\n¿Podrás decirnos tu nombre completo?";

                $sender->sendMessage($phone, $response, ['registration_step' => 'name']);

                return ['success' => true, 'message' => 'Registration initiated'];
            }

            return null;
        }

        // Process registration steps
        if ($registrationStep === 'name')
        {
            $name = $message;

            // Validate name has at least 3 letters
            if (strlen(trim($name)) < 3)
            {
                $response = "No parece ser un nombre válido.\n\n¿Podrías escribirlo nuevamente?";
                $sender->sendMessage($phone, $response, ['registration_step' => 'name']);

                return ['success' => true, 'message' => 'Invalid name'];
            }

            $response = "¡Gracias, $name!\n\n¿Podrás decirnos tu dirección de email para asociarla a tu cuenta?";

            $sender->sendMessage($phone, $response, [
                'registration_step' => 'email',
                'user_name' => $name,
            ]);

            return ['success' => true, 'message' => 'Name collected'];
        }

        if ($registrationStep === 'email')
        {
            $email = $message;
            $userName = $metadata['user_name'] ?? 'User';

            // Validate email
            if (! filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $response = "No parece ser una dirección de email válida.\n\n¿Podrás escribirla nuevamente?";
                $sender->sendMessage($phone, $response, [
                    'registration_step' => 'email',
                    'user_name' => $userName,
                ]);

                return ['success' => true, 'message' => 'Invalid email'];
            }

            // Create the new user
            try
            {
                $user = User::create([
                    'name' => $userName,
                    'email' => $email,
                    'phone' => preg_replace('/[^0-9]/', '', $phone),
                    'password' => bcrypt(substr(md5(rand()), 0, 10)), // Random password
                ]);

                // Assign client role (ID 7)
                if (class_exists('\\Spatie\\Permission\\Models\\Role'))
                {
                    $clientRole = \Spatie\Permission\Models\Role::findById(7);
                    if ($clientRole)
                    {
                        $user->assignRole($clientRole);
                    }
                }

                $response = "¡Gracias por registrarte!\n\nVamos a confirmar tus datos y a partir de ahora todas las comunicaciones con nosotros estarán validadas con este número telefónico.\nEn breve nos pondremos en contacto por este mismo medio.";

                $sender->sendMessage($phone, $response, null, $user->id);

                return ['success' => true, 'message' => 'User registered', 'user_id' => $user->id];
            } catch (\Exception $e)
            {
                Log::error('User registration error: '.$e->getMessage());
                $response = "Lo sentimos, ha ocurrido un error al crear tu cuenta.\nPor favor escríbenos a administracion@revisionalpha.com para que podamos ayudarte.";
                $sender->sendMessage($phone, $response);

                return ['success' => false, 'message' => 'Registration error'];
            }
        }

        return null;
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
    public function whatsappStatus()
    {
        $driver = config('whatsapp.driver');
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
                    && $this->phonesBelongToSameLine((string) $teamNumber, (string) $number);
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
        if (config('whatsapp.driver') !== 'local')
        {
            return $this->transparentPngResponse();
        }
        $baseUrl = auth()->user()?->currentTeam?->getWhatsAppServiceBaseUrl() ?? rtrim(config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl === '')
        {
            return $this->transparentPngResponse();
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

            return $this->transparentPngResponse();
        }
        $body = $response->body();
        $bodyLen = strlen($body);

        if (! $response->successful())
        {
            return $this->transparentPngResponse();
        }

        if ($bodyLen < 10)
        {
            return $this->transparentPngResponse();
        }

        return response($body)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * 1x1 transparent PNG so img tag does not show broken icon.
     */
    private function transparentPngResponse(): \Illuminate\Http\Response
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);

        return response($png)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Force the Node service to reconnect and generate a new QR (when disconnected).
     */
    public function whatsappRefreshQr(Request $request)
    {
        if (config('whatsapp.driver') !== 'local')
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
        if (config('whatsapp.driver') !== 'local')
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
}
