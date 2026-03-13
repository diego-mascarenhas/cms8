<?php

namespace App\Http\Controllers;

use App\Helpers\TextHelper;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AgentConversationContextService;
use App\Services\ChatAssistantReplyService;
use App\Services\TwilioService;
use App\Services\UserResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function index()
    {
        // Get unique WhatsApp contacts (phone numbers)
        $contacts = Conversation::where('channel', 'whatsapp')
            ->selectRaw('DISTINCT `from`, MAX(created_at) as last_message_at')
            ->where('direction', 'inbound')
            ->groupBy('from')
            ->orderBy('last_message_at', 'desc')
            ->get();

        // Get the last message from each contact and enrich with user data
        foreach ($contacts as $contact)
        {
            $lastMessage = Conversation::where('from', $contact->from)
                ->where('channel', 'whatsapp')
                ->latest()
                ->first();

            $contact->last_message = $lastMessage->body;
            $contact->last_message_time = $lastMessage->created_at->diffForHumans();

            // Get user information if available
            $userData = $this->getUserByPhone($contact->from);
            if ($userData)
            {
                $contact->user_name = $userData->name;
                $contact->user_photo = $userData->profile_photo_path;
                $contact->user_id = $userData->id;
            }
        }

        // If a contact is selected, get their messages
        $selectedPhone = request('phone');
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
            // Get all messages for this conversation
            $messages = Conversation::where('channel', 'whatsapp')
                ->where(function ($query) use ($selectedPhone)
                {
                    $query->where('from', $selectedPhone)
                        ->orWhere('to', $selectedPhone);
                })
                ->orderBy('created_at')
                ->get();

            // Mark inbound messages as read when user views the conversation
            Conversation::where('channel', 'whatsapp')
                ->where('direction', 'inbound')
                ->where('from', $selectedPhone)
                ->where('status', 'received')
                ->update(['status' => 'read']);

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

        return view('chat.index', compact('contacts', 'messages', 'selectedPhone', 'selectedUser', 'hasContact', 'selectedContact', 'users', 'viewAssistant', 'assistantMessages', 'assistantClients', 'selectedAssistantUser', 'clientRecipientPhone', 'assistantContactId'));
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
                $query->where('from', $phone)
                    ->orWhere('to', $phone);
            })
            ->orderBy('created_at')
            ->get();

        return response()->json(['messages' => $messages]);
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
     * Chat assistant: process message with context from agent_conversations.
     * When recipient (phone) or contact_id is provided, context is that user's conversation; otherwise the auth user's.
     */
    public function assistant(Request $request, UserResolverService $userResolver, AgentConversationContextService $contextService, ChatAssistantReplyService $replyService)
    {
        $request->validate([
            'message' => 'required|string|max:16000',
            'recipient' => 'nullable|string|max:50',
            'contact_id' => 'nullable|integer|exists:contacts,id',
        ]);

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
        $replyResponse = $replyService->getReply($request->input('message'), $history, $teamId);

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
            $request->input('message'),
            $assistantText,
            $replyResponse['routed_to'] ?? null,
        );

        return response()->json([
            'success' => true,
            'response' => $assistantText,
            'action_performed' => null,
        ]);
    }

    public function sendMessage(Request $request, TwilioService $twilioService, ChatAssistantReplyService $replyService)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string',
            'use_ai' => 'boolean',
        ]);

        try
        {
            // Check if this number needs registration
            $registrationResponse = $this->processRegistration($request->to, $request->message);
            if ($registrationResponse)
            {
                return response()->json([
                    'success' => true,
                    'registration' => true,
                    'message' => $registrationResponse['message'],
                ]);
            }

            // Check if AI assistance was requested
            if ($request->input('use_ai', false))
            {
                // Get chat history for context
                $history = $this->getChatHistory($request->to, 10);
                $teamId = auth()->user()?->currentTeam?->id;
                $replyResponse = $replyService->getReply($request->message, $history, $teamId);

                // If assistant responded successfully, use its response
                if ($replyResponse['success'])
                {
                    $aiMessage = $replyResponse['text'];

                    // Send the AI message
                    $result = $twilioService->sendWhatsApp($request->to, $aiMessage);

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

            // Send original message
            $result = $twilioService->sendWhatsApp($request->to, $request->message);

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
     * @return array|null Response to send or null if no registration in progress
     */
    public function processRegistration($phone, $message)
    {
        $twilioService = app(TwilioService::class);
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

                // The TwilioService will save the message in the database
                $result = $twilioService->sendWhatsApp($phone, $response, ['registration_step' => 'name']);

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
                $twilioService->sendWhatsApp($phone, $response, ['registration_step' => 'name']);

                return ['success' => true, 'message' => 'Invalid name'];
            }

            $response = "¡Gracias, $name!\n\n¿Podrás decirnos tu dirección de email para asociarla a tu cuenta?";

            // The TwilioService will save the message to database
            $twilioService->sendWhatsApp($phone, $response, [
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
                $twilioService->sendWhatsApp($phone, $response, [
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

                // The TwilioService will save the message to database with user_id
                $twilioService->sendWhatsApp($phone, $response, null, $user->id);

                return ['success' => true, 'message' => 'User registered', 'user_id' => $user->id];
            } catch (\Exception $e)
            {
                Log::error('User registration error: '.$e->getMessage());
                $response = "Lo sentimos, ha ocurrido un error al crear tu cuenta.\nPor favor escríbenos a administracion@revisionalpha.com para que podamos ayudarte.";
                $twilioService->sendWhatsApp($phone, $response);

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
}
