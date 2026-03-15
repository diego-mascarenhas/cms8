<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppGateway;
use App\Helpers\TextHelper;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AgentConversationContextService;
use App\Services\ChatAssistantReplyService;
use App\Services\TwilioService;
use App\Services\UserResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * @return \Illuminate\Support\Collection<int, object{from: string, last_message: string, last_message_time: string, last_message_at: \Carbon\Carbon, unread_count: int, user_name?: string, user_photo?: string, user_id?: int}>
     */
    private function getWhatsAppContacts(): \Illuminate\Support\Collection
    {
        $inboundPhones = Conversation::where('channel', 'whatsapp')
            ->where('direction', 'inbound')
            ->distinct()
            ->pluck('from');
        $outboundPhones = Conversation::where('channel', 'whatsapp')
            ->where('direction', 'outbound')
            ->distinct()
            ->pluck('to');
        $allRaw = $inboundPhones->merge($outboundPhones)->filter()->values();
        $normalizedUnique = $allRaw->map(fn ($p) => $this->normalizePhoneForList((string) $p))
            ->unique()
            ->values();

        $contacts = collect();
        foreach ($normalizedUnique as $normalizedPhone)
        {
            $lastMessage = Conversation::where('channel', 'whatsapp')
                ->where(function ($query) use ($normalizedPhone)
                {
                    $query->where('from', $normalizedPhone)
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
            $unreadCount = Conversation::where('channel', 'whatsapp')
                ->where('direction', 'inbound')
                ->where('status', 'received')
                ->where(function ($q) use ($normalizedPhone)
                {
                    $q->where('from', $normalizedPhone)
                        ->orWhere('from', 'like', $normalizedPhone.':%');
                })
                ->count();

            $contact = (object) [
                'from' => $normalizedPhone,
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
            $contacts->push($contact);
        }

        return $contacts->sortByDesc(function ($c)
        {
            return $c->last_message_at ? $c->last_message_at->timestamp : 0;
        })->values();
    }

    public function index()
    {
        $contacts = $this->getWhatsAppContacts();

        // If a contact is selected, get their messages (normalize phone so JID suffix doesn't duplicate)
        $selectedPhone = request('phone') ? $this->normalizePhoneForList((string) request('phone')) : null;
        $viewAssistant = request('view') === 'assistant';
        $assistantUserId = request()->integer('user_id', 0) ?: null;
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
            // Get all messages for this conversation (match normalized phone and JID suffix variant)
            $messages = Conversation::where('channel', 'whatsapp')
                ->where(function ($query) use ($selectedPhone)
                {
                    $this->applyConversationPhoneFilter($query, $selectedPhone);
                })
                ->orderBy('created_at')
                ->get();

            // Mark inbound messages as read when user views the conversation
            $norm = $this->normalizePhoneForList($selectedPhone);
            Conversation::where('channel', 'whatsapp')
                ->where('direction', 'inbound')
                ->where('status', 'received')
                ->where(function ($q) use ($norm)
                {
                    $q->where('from', $norm)->orWhere('from', 'like', $norm.':%');
                })
                ->update(['status' => 'read']);
            Cache::forget(Conversation::CACHE_KEY_INBOUND_UNREAD);

            // Get user information for the header
            $selectedUser = $this->getUserByPhone($selectedPhone);
        }

        $hasContact = false;
        $selectedContact = null;
        if ($selectedUser && $selectedUser->id)
        {
            $selectedContact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
                ->where('user_id', $selectedUser->id)->first();
            $hasContact = $selectedContact !== null;
        }

        $userIds = $messages->pluck('user_id')->filter()->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($messages as $message)
        {
            $message->body = TextHelper::sanitizeAndLink($message->body);
        }

        $clientRecipientPhone = $selectedAssistantUser ? $this->getWhatsAppPhoneForUser($selectedAssistantUser) : '';
        $assistantContactId = $selectedAssistantUser
            ? (Contact::withoutGlobalScopes()->where('user_id', $selectedAssistantUser->id)->first()?->id ?? '')
            : '';
        // Preference owner = client user when viewing a client's conversation, else the operator (auth user)
        $preferenceUserId = $viewAssistant && auth()->check()
            ? ($selectedAssistantUser ? $selectedAssistantUser->id : auth()->id())
            : null;
        $userChatAiToggleDefault = $preferenceUserId !== null
            ? $this->getChatAiToggleDefaultForUser($preferenceUserId)
            : true;

        $whatsappDriver = config('whatsapp.driver');
        $whatsappStatus = null;
        $qrImageUrl = null;
        if ($whatsappDriver === 'local' && app()->bound(WhatsAppGateway::class))
        {
            $gateway = app(WhatsAppGateway::class);
            $whatsappStatus = $gateway->getConnectionStatus();
            $baseUrl = rtrim(config('whatsapp.local.base_url', ''), '/');
            if ($baseUrl !== '')
            {
                $qrImageUrl = route('chat.whatsapp-qr-image');
            }
        }

        $notifyNewContactEmail = auth()->check() && auth()->user()->currentTeam
            ? filter_var(auth()->user()->currentTeam->getSetting('notify_new_contact_email', '0'), FILTER_VALIDATE_BOOLEAN)
            : false;

        $assistantAutoRespond = auth()->check() && auth()->user()->currentTeam
            ? filter_var(auth()->user()->currentTeam->getSetting('assistant_auto_respond', '0'), FILTER_VALIDATE_BOOLEAN)
            : false;

        return view('chat.index', compact('contacts', 'messages', 'selectedPhone', 'selectedUser', 'hasContact', 'selectedContact', 'users', 'viewAssistant', 'assistantMessages', 'assistantClients', 'selectedAssistantUser', 'clientRecipientPhone', 'assistantContactId', 'userChatAiToggleDefault', 'preferenceUserId', 'whatsappDriver', 'whatsappStatus', 'qrImageUrl', 'notifyNewContactEmail', 'assistantAutoRespond'));
    }

    /**
     * Get phone for WhatsApp (recipient field) from a user.
     */
    private function getWhatsAppPhoneForUser(User $user): string
    {
        $phone = $user->phone !== null ? (string) $user->phone : null;
        if ($phone === null || $phone === '')
        {
            $contact = Contact::withoutGlobalScopes()->where('user_id', $user->id)->first();
            if ($contact && $contact->phone)
            {
                $phone = preg_replace('/[^0-9]/', '', (string) $contact->phone);
            }
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
     * Get chat_ai_toggle_default for a user (client or operator) from the settings table.
     */
    private function getChatAiToggleDefaultForUser(int $userId): bool
    {
        $row = DB::table('settings')
            ->where('group', 'user_'.$userId)
            ->where('name', 'chat_ai_toggle_default')
            ->value('payload');

        if ($row === null)
        {
            return true;
        }

        $decoded = json_decode($row, true);

        return (bool) $decoded;
    }

    /**
     * Set chat_ai_toggle_default for a user (client or operator) in the settings table.
     */
    private function setChatAiToggleDefaultForUser(int $userId, bool $value): void
    {
        $group = 'user_'.$userId;
        $name = 'chat_ai_toggle_default';
        $payload = json_encode($value);
        $now = now();

        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'name' => $name],
            [
                'locked' => false,
                'payload' => $payload,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    /**
     * Users that have an assistant conversation (for sidebar list). Current user + same team + placeholder (no team).
     */
    private function getAssistantClientsList()
    {
        $contextService = app(AgentConversationContextService::class);
        $userIds = \App\Models\AgentConversation::whereHas('messages', fn ($q) => $q->where('agent', AgentConversationContextService::AGENT_NAME))
            ->distinct()
            ->pluck('user_id');

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

        // Try without country code if not found
        if (strlen($cleanNumber) > 9)
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

    public function getMessages(Request $request, $phone)
    {
        $messages = Conversation::where('channel', 'whatsapp')
            ->where(function ($query) use ($phone)
            {
                $this->applyConversationPhoneFilter($query, (string) $phone);
            })
            ->orderBy('created_at')
            ->get();

        return response()->json(['messages' => $messages]);
    }

    /**
     * Get WhatsApp conversation list as JSON for sidebar polling (live update without page refresh).
     */
    public function getChatList()
    {
        $contacts = $this->getWhatsAppContacts();
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
     * Save default AI toggle preference for the conversation's user (client or operator).
     * Optional user_id = client user id; when omitted, saves for the authenticated operator.
     */
    public function updateAiTogglePreference(Request $request)
    {
        $request->validate([
            'on' => 'required|boolean',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if (! auth()->check())
        {
            return response()->json(['success' => false], 401);
        }

        $userId = $request->integer('user_id', 0) ?: null;
        if ($userId !== null && $userId !== auth()->id() && ! $this->canViewAssistantConversation($userId))
        {
            return response()->json(['success' => false], 403);
        }

        $targetUserId = $userId ?? auth()->id();
        $on = $request->boolean('on');
        $this->setChatAiToggleDefaultForUser($targetUserId, $on);

        if (auth()->user()->currentTeam)
        {
            auth()->user()->currentTeam->setSetting('assistant_auto_respond', $on ? '1' : '0');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Save team setting: assistant auto-responds to inbound WhatsApp messages (sidebar "Respuestas del Asistente Humano").
     */
    public function updateAssistantAutoRespond(Request $request)
    {
        $request->validate(['on' => 'required|boolean']);

        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        auth()->user()->currentTeam->setSetting('assistant_auto_respond', $request->boolean('on') ? '1' : '0');

        return response()->json(['success' => true]);
    }

    /**
     * Save team setting: notify by email when a new client contacts (sidebar notification toggle).
     */
    public function updateNotificationPreference(Request $request)
    {
        $request->validate(['on' => 'required|boolean']);

        if (! auth()->check() || ! auth()->user()->currentTeam)
        {
            return response()->json(['success' => false], 401);
        }

        auth()->user()->currentTeam->setSetting('notify_new_contact_email', $request->boolean('on') ? '1' : '0');

        return response()->json(['success' => true]);
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
        $request->validate([
            'message' => ['required_without:audio', 'nullable', 'string', 'max:16000'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,m4a,webm,ogg,mp4,mpeg', 'max:25600'],
            'respond_with_audio' => 'nullable|boolean',
            'recipient' => 'nullable|string|max:50',
            'contact_id' => 'nullable|integer|exists:contacts,id',
        ], [
            'audio.mimes' => __('El audio debe ser mp3, wav, m4a, webm u ogg.'),
            'audio.max' => __('El audio no puede superar 25 MB.'),
        ]);

        // #region agent log
        $debugLogPath = base_path('.cursor/debug-cd916b.log');
        if (is_writable(dirname($debugLogPath)) || is_writable($debugLogPath)) {
            @file_put_contents($debugLogPath, json_encode(['sessionId' => 'cd916b', 'location' => 'ChatController::assistant', 'message' => 'validation passed', 'data' => ['hasAudio' => $hasAudio], 'timestamp' => (int) (microtime(true) * 1000), 'hypothesisId' => 'A'])."\n", FILE_APPEND | LOCK_EX);
        }
        // #endregion

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
            return response()->json(['success' => false, 'message' => __('El mensaje no puede estar vacío.')], 422);
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

        $history = $contextService->getHistoryForPrompt($contextUser->id, AgentConversationContextService::DEFAULT_HISTORY_LIMIT);
        $teamId = auth()->user()?->currentTeam?->id;
        $withTools = ! $request->filled('recipient') && ! $request->filled('contact_id');
        $replyResponse = $replyService->getReply($message, $history, $teamId, $withTools);

        if (! $replyResponse['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $replyResponse['message'] ?? 'Assistant failed',
            ], 500);
        }

        $assistantText = $replyResponse['text'] ?? '';
        $contextService->persistMessages(
            $contextUser->id,
            $message,
            $assistantText,
            $replyResponse['routed_to'] ?? null,
        );

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

    public function sendMessage(Request $request, WhatsAppGateway $gateway, ChatAssistantReplyService $replyService, UserResolverService $userResolver, AgentConversationContextService $contextService)
    {
        $hasAudio = $request->hasFile('audio');
        $request->validate([
            'to' => 'required|string',
            'message' => ['required_without:audio', 'nullable', 'string'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,m4a,webm,ogg,mp4,mpeg', 'max:25600'],
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

            // Check if AI assistance was requested
            if ($request->input('use_ai', false))
            {
                // Get chat history for context
                $history = $this->getChatHistory($request->to, 10);
                $teamId = auth()->user()?->currentTeam?->id;
                $replyResponse = $replyService->getReply($message, $history, $teamId);

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
        $sender = $gateway ?? app(TwilioService::class);
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

        $twilioService = app(TwilioService::class);

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
     */
    public function whatsappStatus()
    {
        $driver = config('whatsapp.driver');
        $status = null;
        $number = null;
        $numberFormatted = null;
        if ($driver === 'local' && app()->bound(WhatsAppGateway::class))
        {
            $gateway = app(WhatsAppGateway::class);
            $status = $gateway->getConnectionStatus();
            if (is_array($status))
            {
                $number = $status['number'] ?? null;
                $numberFormatted = ($number !== null && $number !== '')
                    ? \App\Helpers\PhoneHelper::formatForDisplayReadable($number)
                    : null;
            }
        }

        return response()->json([
            'driver' => $driver,
            'status' => (is_array($status) ? ($status['status'] ?? 'disconnected') : 'disconnected'),
            'number' => $number ?? null,
            'numberFormatted' => $numberFormatted ?? null,
        ]);
    }

    /**
     * Proxy for WhatsApp QR image (same-origin so it loads on HTTPS).
     */
    public function whatsappQrImage()
    {
        // #region agent log
        $logPath = base_path('.cursor/debug-aac33a.log');
        $log = function (array $data) use ($logPath): void
        {
            if (is_writable($logPath) || is_writable(dirname($logPath)))
            {
                $line = json_encode(array_merge([
                    'sessionId' => 'aac33a',
                    'location' => 'ChatController::whatsappQrImage',
                    'timestamp' => (int) (microtime(true) * 1000),
                ], $data))."\n";
                @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
            }
        };
        // #endregion

        if (config('whatsapp.driver') !== 'local')
        {
            $log(['message' => 'Driver not local', 'data' => ['driver' => config('whatsapp.driver')]]);

            return $this->transparentPngResponse();
        }
        $baseUrl = rtrim(config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl === '')
        {
            $log(['message' => 'Empty base_url']);

            return $this->transparentPngResponse();
        }
        $url = $baseUrl.'/qr.png';
        $response = \Illuminate\Support\Facades\Http::timeout(8)->connectTimeout(3)->get($url);
        $status = $response->status();
        $body = $response->body();
        $bodyLen = strlen($body);
        $isPng = ($bodyLen >= 8 && substr($body, 0, 8) === "\x89PNG\r\n\x1a\n");

        if (! $response->successful())
        {
            $log(['message' => 'Node response not successful', 'data' => ['url' => $url, 'status' => $status, 'bodyLen' => $bodyLen, 'bodyPreview' => substr($body, 0, 200)]]);

            return $this->transparentPngResponse();
        }

        if ($bodyLen < 10)
        {
            $log(['message' => 'Body too short', 'data' => ['bodyLen' => $bodyLen]]);

            return $this->transparentPngResponse();
        }

        $log(['message' => 'Serving QR PNG', 'data' => ['status' => $status, 'bodyLen' => $bodyLen, 'isPng' => $isPng]]);

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
        $baseUrl = rtrim(config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl !== '')
        {
            \Illuminate\Support\Facades\Http::timeout(10)->get($baseUrl.'/refresh');
        }

        $message = __('Request sent. Wait a few seconds for the QR code to appear.');

        if ($request->expectsJson())
        {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('chat.index')->with('success', $message);
    }

    /**
     * End WhatsApp session (unlink device) when using local driver.
     */
    public function whatsappLogout(Request $request)
    {
        if (config('whatsapp.driver') !== 'local')
        {
            return redirect()->route('chat.index');
        }

        $gateway = app(WhatsAppGateway::class);
        if (method_exists($gateway, 'logout'))
        {
            $gateway->logout();
        }

        return redirect()->route('chat.index')->with('success', __('WhatsApp session closed. Scan QR to link again.'));
    }
}
