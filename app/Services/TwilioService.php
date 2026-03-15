<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Jobs\RecordContactSentimentJob;
use App\Mail\IncomingMessageNotification;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QROptions;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class TwilioService implements WhatsAppGateway
{
    protected $client;

    protected $team;

    protected $config;

    /** @var WhatsAppGateway|null Override for sending when processing local webhook */
    protected $sendingGateway = null;

    public function __construct(?Team $team = null)
    {
        $this->team = $team;

        // Only initialize config if not in console mode or if team is explicitly provided
        if ($this->team && ! app()->runningInConsole() && $this->team->hasTwilioConfig())
        {
            // Use team-specific configuration
            $this->config = $this->team->getTwilioConfig();
            $this->client = new Client($this->config['sid'], $this->config['token']);
        } else
        {
            // Use global .env configuration
            $this->config = [
                'sid' => config('services.twilio.sid'),
                'token' => config('services.twilio.token'),
                'sms_from' => config('services.twilio.from'),
                'whatsapp_from' => config('services.twilio.whatsapp_from'),
            ];

            if ($this->config['sid'] && $this->config['token'])
            {
                $this->client = new Client($this->config['sid'], $this->config['token']);
            }
        }
    }

    /**
     * Get default team (either current user's team or null for global config)
     */
    protected function getDefaultTeam()
    {
        if (auth()->check() && auth()->user()->currentTeam)
        {
            return auth()->user()->currentTeam;
        }

        return null;  // Return null to use global .env configuration
    }

    /**
     * Create instance with default team (for app usage, not webhooks)
     */
    public static function forCurrentUser()
    {
        $team = null;
        if (auth()->check() && auth()->user()->currentTeam)
        {
            $team = auth()->user()->currentTeam;
        }

        return new self($team);
    }

    /**
     * Set team for this service instance
     */
    public function setTeam(Team $team)
    {
        $this->team = $team;

        if ($team->hasTwilioConfig())
        {
            $this->config = $team->getTwilioConfig();
            $this->client = new Client($this->config['sid'], $this->config['token']);
        } else
        {
            // Fallback to global config
            $this->config = [
                'sid' => config('services.twilio.sid'),
                'token' => config('services.twilio.token'),
                'sms_from' => config('services.twilio.from'),
                'whatsapp_from' => config('services.twilio.whatsapp_from'),
            ];

            if ($this->config['sid'] && $this->config['token'])
            {
                $this->client = new Client($this->config['sid'], $this->config['token']);
            }
        }

        return $this;
    }

    /**
     * Check if Twilio is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    /**
     * Get system phone numbers for this team
     */
    public function getSystemPhoneNumbers()
    {
        $numbers = [];

        if (isset($this->config['sms_from']))
        {
            $cleanSms = preg_replace('/[^0-9]/', '', $this->config['sms_from']);
            $numbers[] = $cleanSms;
            $numbers[] = $this->config['sms_from'];
        }

        if (isset($this->config['whatsapp_from']))
        {
            $cleanWhatsapp = preg_replace('/[^0-9]/', '', $this->config['whatsapp_from']);
            $numbers[] = $cleanWhatsapp;
            $numbers[] = $this->config['whatsapp_from'];
            $numbers[] = 'whatsapp:'.$this->config['whatsapp_from'];
            $numbers[] = 'whatsapp:+'.$this->config['whatsapp_from'];
        }

        return array_unique($numbers);
    }

    /**
     * Check if a phone number belongs to the system
     */
    public function isSystemPhoneNumber($phoneNumber)
    {
        $systemNumbers = $this->getSystemPhoneNumbers();
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        return in_array($phoneNumber, $systemNumbers) || in_array($cleanNumber, $systemNumbers);
    }

    /**
     * Get user information by phone number
     */
    private function getUserByPhone($phoneNumber)
    {
        // Clean the phone number (remove whatsapp: prefix, plus sign, and any non-digits)
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Always try to get user directly by phone (full number)
        $user = User::where('phone', $cleanNumber)->first();
        if ($user)
        {
            return $user;
        }

        // Try without country code if not found
        if (strlen($cleanNumber) > 9)
        {
            $withoutCountryCode = substr($cleanNumber, -9);
            $user = User::where('phone', $withoutCountryCode)->first();
            if ($user)
            {
                return $user;
            }
        }

        // If no user found directly, try to find through contact relationship
        $contact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
            ->whereHas('sources', function ($query) use ($cleanNumber)
            {
                $query
                    ->where('source_id', 2)  // Phone source
                    ->where('value', $cleanNumber);
            })->first();

        if ($contact && $contact->user)
        {
            return $contact->user;
        }

        // If still no user found, try to get contact name
        if ($contact)
        {
            return (object) [
                'name' => $contact->name,
                'id' => null,
                'is_contact' => true,
            ];
        }

        return null;
    }

    /**
     * Check if this is the first message of the day from this contact
     */
    private function isFirstMessageToday($phoneNumber)
    {
        $today = Carbon::today();

        $messageCount = Conversation::where('from', $phoneNumber)
            ->where('direction', 'inbound')
            ->whereDate('created_at', $today)
            ->count();

        // Return true if this is the first message (count = 1, which includes the message just saved)
        return $messageCount === 1;
    }

    /**
     * Send automatic greeting if it's the first message of the day.
     * Returns the greeting text when sent, null otherwise (so caller can persist to agent context).
     */
    private function sendAutoGreeting($phoneNumber): ?string
    {
        if (! $this->isFirstMessageToday($phoneNumber))
        {
            return null;
        }

        $user = $this->getUserByPhone($phoneNumber);
        $name = $user && ! empty($user->name) ? trim($user->name) : null;
        if ($name === null || $name === '')
        {
            $name = 'Usuario '.$phoneNumber;
        }

        $greeting = "¡Hola {$name}! 👋";

        try
        {
            $this->sendWhatsApp($phoneNumber, $greeting);
            Log::info("Auto greeting sent to {$phoneNumber}: {$greeting}");

            return $greeting;
        } catch (\Exception $e)
        {
            Log::error("Failed to send auto greeting to {$phoneNumber}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Persist a WhatsApp user message and assistant reply into agent_conversation_messages
     * so the assistant has context for follow-up messages (web or auto-respond).
     *
     * @param  array<string, mixed>  $replyResponse  Optional full reply from getReply (usage, tool_calls, tool_results).
     */
    private function persistWhatsAppExchangeToAgentContext(string $phone, string $userMessage, string $assistantMessage, array $replyResponse = []): void
    {
        try
        {
            $userResolver = app(UserResolverService::class);
            $contextService = app(AgentConversationContextService::class);
            $contextUser = $userResolver->resolveUserForConversation($phone, null);
            if ($contextUser !== null)
            {
                $teamId = $this->team ? (int) $this->team->id : null;
                $contextService->persistMessages(
                    $contextUser->id,
                    $userMessage,
                    $assistantMessage,
                    $replyResponse['routed_to'] ?? null,
                    $replyResponse['usage'] ?? [],
                    $replyResponse['meta'] ?? [],
                    $replyResponse['tool_calls'] ?? [],
                    $replyResponse['tool_results'] ?? [],
                    $teamId,
                );
            }
        } catch (\Throwable $e)
        {
            Log::warning('Could not persist WhatsApp exchange to agent context: '.$e->getMessage());
        }
    }

    public function sendSms($to, $message)
    {
        if (! $this->isConfigured())
        {
            throw new \Exception('Twilio not configured for team: '.($this->team ? $this->team->name : 'No team'));
        }

        try
        {
            // Get the status callback URL for this team
            $statusCallbackUrl = $this->team ? $this->team->getTwilioStatusCallbackUrl() : url(route('twilio.status'));

            $twilioMessage = $this->client->messages->create(
                $to,
                [
                    'from' => $this->config['sms_from'],
                    'body' => $message,
                    'statusCallback' => $statusCallbackUrl,
                ],
            );

            // Clean phone numbers before saving to database
            $cleanFrom = preg_replace('/[^0-9]/', '', $this->config['sms_from']);
            $cleanTo = preg_replace('/[^0-9]/', '', $to);

            // Save outbound SMS to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'sms',
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'body' => $message,
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => auth()->id(),
                'team_id' => $this->team ? $this->team->id : null,
                'metadata' => [
                    'twilio_response' => [
                        'sid' => $twilioMessage->sid,
                        'status' => $twilioMessage->status,
                        'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                    ],
                ],
            ]);

            return $twilioMessage;
        } catch (\Exception $e)
        {
            Log::error('Twilio SMS Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Gateway to use for sending (when processing incoming with alternate gateway).
     */
    protected function getSender(): WhatsAppGateway
    {
        return $this->sendingGateway ?? $this;
    }

    public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
    {
        return $this->sendWhatsApp($to, $message, $metadata, $userId);
    }

    public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
    {
        $this->sendWhatsAppWithMedia($to, $mediaPath, 'media');

        return true;
    }

    public function getQrUrl(): ?string
    {
        return null;
    }

    public function getConnectionStatus(): ?array
    {
        return null;
    }

    public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
    {
        if (config('whatsapp.driver') === 'local' && app()->bound(WhatsAppGateway::class))
        {
            $sender = $this->getSender();
            if ($sender !== $this)
            {
                return $sender->sendMessage($to, $message, $metadata, $userId);
            }

            return app(WhatsAppGateway::class)->sendMessage($to, $message, $metadata, $userId);
        }

        $sender = $this->getSender();
        if ($sender !== $this)
        {
            return $sender->sendMessage($to, $message, $metadata, $userId);
        }

        if (! $this->isConfigured())
        {
            throw new \Exception('Twilio not configured for team: '.($this->team ? $this->team->name : 'No team'));
        }

        try
        {
            // Format the numbers with whatsapp: prefix for Twilio
            $formattedTo = 'whatsapp:'.$to;
            $whatsappFromNumber = 'whatsapp:'.$this->config['whatsapp_from'];

            // Get the status callback URL for this team
            $statusCallbackUrl = $this->team ? $this->team->getTwilioStatusCallbackUrl() : url(route('twilio.status'));

            $twilioMessage = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $whatsappFromNumber,
                    'body' => $message,
                    'statusCallback' => $statusCallbackUrl,
                ],
            );

            // Clean phone numbers before saving to database
            $cleanFrom = preg_replace('/[^0-9]/', '', $this->config['whatsapp_from']);
            $cleanTo = preg_replace('/[^0-9]/', '', $to);

            // Prepare metadata
            $messageMetadata = [
                'twilio_response' => [
                    'sid' => $twilioMessage->sid,
                    'status' => $twilioMessage->status,
                    'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                ],
            ];

            // Merge custom metadata if provided
            if ($metadata)
            {
                $messageMetadata = array_merge($messageMetadata, $metadata);
            }

            // Save outbound message to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'body' => $message,
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => $userId ?? auth()->id(),
                'team_id' => $this->team ? $this->team->id : null,
                'metadata' => $messageMetadata,
            ]);

            return $twilioMessage;
        } catch (\Exception $e)
        {
            Log::error('Twilio WhatsApp Error: '.$e->getMessage());
            throw $e;
        }
    }

    public function processIncomingMessage($request, ?WhatsAppGateway $gateway = null)
    {
        $this->sendingGateway = $gateway ?? $this;
        try
        {
            $messageSid = $request->input('MessageSid');
            $from = $request->input('From');
            $to = $request->input('To');
            $body = $request->input('Body');
            $numMedia = (int) $request->input('NumMedia', 0);

            // Clean phone numbers by removing whatsapp: prefix and non-numeric characters
            $cleanFrom = preg_replace('/[^0-9]/', '', (string) $from);
            $cleanTo = preg_replace('/[^0-9]/', '', (string) $to);

            if ($cleanFrom === '' && (strpos((string) $from, 'whatsapp') !== false || strpos((string) $to, 'whatsapp') !== false))
            {
                Log::warning('Incoming WhatsApp message ignored: missing or invalid From', ['From' => $from, 'To' => $to]);

                return response()->json(['status' => 'ignored', 'reason' => 'missing_from'], 200);
            }

            // Determine the channel type
            $channel = 'sms';
            if (strpos($from, 'whatsapp:') !== false || strpos($to, 'whatsapp:') !== false)
            {
                $channel = 'whatsapp';
            }

            // Log the incoming message (body may be empty for voice notes; media holds the audio)
            $bodyPreview = trim((string) $body) !== '' ? $body : ($numMedia > 0 ? '(audio/media)' : '(empty)');
            Log::info("Incoming {$channel} message from {$cleanFrom}: {$bodyPreview}");

            // Process media if present
            $media = [];
            if ($numMedia > 0)
            {
                for ($i = 0; $i < $numMedia; $i++)
                {
                    $mediaUrl = $request->input("MediaUrl{$i}");
                    $contentType = $request->input("MediaContentType{$i}");
                    $media[] = [
                        'url' => $mediaUrl,
                        'content_type' => $contentType,
                    ];
                }
            }

            // Save incoming message to database
            $conversation = Conversation::create([
                'message_sid' => $messageSid,
                'channel' => $channel,
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'body' => $body,
                'status' => 'received',
                'direction' => 'inbound',
                'media' => ! empty($media) ? $media : null,
                'metadata' => $request->except(['_token']),
            ]);

            if ($channel === 'whatsapp' && $this->team)
            {
                app(UserResolverService::class)->linkPhoneToContactInTeam($this->team->id, $cleanFrom);
            }

            // Send automatic greeting if it's WhatsApp and first message of the day; persist to agent context
            if ($channel == 'whatsapp')
            {
                $greetingSent = $this->sendAutoGreeting($cleanFrom);
                if ($greetingSent !== null)
                {
                    $this->persistWhatsAppExchangeToAgentContext($cleanFrom, $body, $greetingSent);
                }
            }

            // Send email only when notification is enabled and this is a new client (first inbound from this number)
            $isNewClient = Conversation::where('channel', $channel)
                ->where('direction', 'inbound')
                ->where(function ($q) use ($cleanFrom)
                {
                    $q->where('from', $cleanFrom)->orWhere('from', 'like', $cleanFrom.':%');
                })
                ->count() === 1;
            $notifyEnabled = $this->team && filter_var(
                $this->team->getSetting('notify_new_contact_email', '0'),
                FILTER_VALIDATE_BOOLEAN,
            );
            $notificationEmail = config('services.notifications.email') ?: config('app.notification_email');
            if ($isNewClient && $notifyEnabled && $notificationEmail)
            {
                Mail::to($notificationEmail)->send(new IncomingMessageNotification($conversation));
                Log::info("New contact email notification sent to {$notificationEmail} for from {$cleanFrom}");
            }

            // Queue AI sentiment analysis for WhatsApp (all channels use same job)
            if ($channel === 'whatsapp')
            {
                $this->dispatchSentimentAnalysis($cleanFrom, $body);
            }

            // Check if this is part of a registration process
            if ($channel == 'whatsapp')
            {
                $chatController = app(\App\Http\Controllers\ChatController::class);
                $registrationResponse = $chatController->processRegistration(
                    $cleanFrom,
                    $body,
                    $this->getSender() !== $this ? $this->getSender() : null,
                );

                // If this was a registration step, we've already handled it
                if ($registrationResponse)
                {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'registration' => true]);
                }

                // Check if user is using cart commands (HIGHEST PRIORITY)
                $cartResponse = $this->processCartCommands($cleanFrom, $body);
                if ($cartResponse)
                {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'cart_processed' => true]);
                }

                // Check if user is asking about products
                $productResponse = $this->processProductCommands($cleanFrom, $body);
                if ($productResponse)
                {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'product_processed' => true]);
                }

                // Check if user is trying to report service information
                $serviceResponse = $this->processServiceCommands($cleanFrom, $body);
                if ($serviceResponse)
                {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'service_processed' => true]);
                }

                // Check if user sent "DEMO" command
                $demoResponse = $this->processDemoCommand($cleanFrom, $body);
                if ($demoResponse)
                {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'demo_processed' => true]);
                }

                // Check if user is requesting QR code
                $qrResponse = $this->processQrCommand($cleanFrom, $body);
                if ($qrResponse)
                {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'qr_processed' => true]);
                }
            }

            // Automatic AI response using Claude (enabled when team has "Respuestas del Asistente Humano" ON)
            $assistantAutoRespond = $this->team && filter_var(
                $this->team->getSetting('assistant_auto_respond', '1'),
                FILTER_VALIDATE_BOOLEAN,
            );
            if ($assistantAutoRespond && $channel == 'whatsapp')
            {
                try
                {
                    $teamNumber = $this->team ? preg_replace('/[^0-9]/', '', (string) $this->team->getWhatsAppFrom()) : null;

                    $historyQuery = Conversation::where('channel', 'whatsapp')
                        ->where(function ($query) use ($cleanFrom)
                        {
                            $query
                                ->where('from', $cleanFrom)
                                ->orWhere('to', $cleanFrom);
                        });

                    if ($teamNumber !== null && $teamNumber !== '')
                    {
                        $historyQuery->where(function ($query) use ($teamNumber)
                        {
                            $query
                                ->where('from', $teamNumber)
                                ->orWhere('to', $teamNumber)
                                ->orWhere('from', 'like', $teamNumber.':%')
                                ->orWhere('to', 'like', $teamNumber.':%');
                        });
                    }

                    $history = $historyQuery
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get()
                        ->sortBy('created_at')
                        ->values()
                        ->toArray();

                    $replyService = app(\App\Services\ChatAssistantReplyService::class);
                    $teamId = $this->team?->id;
                    $withTools = $teamId !== null;
                    $replyResponse = $replyService->getReply($body, $history, $teamId, $withTools);

                    if ($replyResponse['success'] ?? false)
                    {
                        $aiMessage = $replyResponse['text'] ?? '';

                        $this->sendWhatsApp($cleanFrom, $aiMessage);
                        $this->persistWhatsAppExchangeToAgentContext($cleanFrom, $body, $aiMessage, $replyResponse);

                        Log::info("Auto AI response sent to {$cleanFrom}: ".\Illuminate\Support\Str::limit($aiMessage, 100));
                    } else
                    {
                        Log::warning('Failed to get AI response: '.($replyResponse['message'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e)
                {
                    Log::error('Error in auto AI response: '.$e->getMessage());
                }
            }

            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id]);
        } catch (\Exception $e)
        {
            Log::error('Error processing incoming message: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        } finally
        {
            $this->sendingGateway = null;
        }
    }

    /**
     * Send WhatsApp message using a template
     *
     * @param  string  $to  Recipient phone number (without whatsapp: prefix)
     * @param  string  $templateName  The name of the approved template
     * @param  array  $parameters  Template parameters
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendWhatsAppTemplate($to, $templateName, $parameters = [])
    {
        if (! $this->isConfigured())
        {
            throw new \Exception('Twilio not configured for team: '.($this->team ? $this->team->name : 'No team'));
        }

        try
        {
            // Format the numbers with whatsapp: prefix
            $formattedTo = 'whatsapp:'.$to;
            $whatsappFromNumber = 'whatsapp:'.$this->config['whatsapp_from'];

            // Get the status callback URL for this team
            $statusCallbackUrl = $this->team ? $this->team->getTwilioStatusCallbackUrl() : url(route('twilio.status'));

            // Prepare template parameters
            $contentSid = null;
            $contentVariables = null;

            if (! empty($parameters))
            {
                $contentVariables = json_encode(['1' => $parameters]);
            }

            // Create message with template
            $twilioMessage = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $whatsappFromNumber,
                    'statusCallback' => $statusCallbackUrl,
                    'contentSid' => $templateName,
                    'contentVariables' => $contentVariables,
                ],
            );

            // Clean phone numbers before saving to database
            $cleanFrom = preg_replace('/[^0-9]/', '', $this->config['whatsapp_from']);
            $cleanTo = preg_replace('/[^0-9]/', '', $to);

            // Save outbound message to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'body' => "Template: {$templateName}",
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => auth()->id(),
                'team_id' => $this->team ? $this->team->id : null,
                'metadata' => [
                    'twilio_response' => [
                        'sid' => $twilioMessage->sid,
                        'status' => $twilioMessage->status,
                        'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                    ],
                    'template' => [
                        'name' => $templateName,
                        'parameters' => $parameters,
                    ],
                ],
            ]);

            return $twilioMessage;
        } catch (\Exception $e)
        {
            Log::error('Twilio WhatsApp Template Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Queue AI sentiment analysis for incoming WhatsApp message (contact resolved by phone).
     */
    private function dispatchSentimentAnalysis(string $phoneNumber, string $messageBody): void
    {
        try
        {
            $phoneAsInt = is_numeric($phoneNumber) ? (int) $phoneNumber : null;
            $user = null;

            if ($phoneAsInt)
            {
                $user = User::where('phone', $phoneAsInt)->first();
            }

            if (! $user)
            {
                $user = User::where('phone', 'like', '%'.$phoneNumber.'%')->first();
            }

            if (! $user)
            {
                return;
            }

            $contact = Contact::withoutGlobalScopes()->where('user_id', $user->id)->first();
            if (! $contact)
            {
                return;
            }

            RecordContactSentimentJob::dispatch($contact->id, $messageBody, 'whatsapp');
        } catch (\Exception $e)
        {
            Log::error('Error dispatching sentiment analysis: '.$e->getMessage());
        }
    }

    /**
     * Process service-related commands from WhatsApp messages
     */
    public function processServiceCommands($phoneNumber, $message)
    {
        try
        {
            // Clean and normalize the message
            $normalizedMessage = strtolower(trim($message));

            // Skip if this is a cart command (comprar, contratar, etc.)
            if (preg_match('/^(comprar|contratar|compra|contrata|carrito|checkout|finalizar|vaciar)/i', $normalizedMessage))
            {
                return null;
            }

            // Find user by phone number
            $user = $this->getUserByPhone($phoneNumber);
            if (! $user || isset($user->is_contact))
            {
                // User not found or is just a contact, skip service processing
                return null;
            }

            // Get the user's contact for enterprises
            $contact = Contact::where('user_id', $user->id)->first();
            if (! $contact)
            {
                return null;
            }

            // Check if message contains service-related keywords
            $serviceKeywords = [
                'servicio',
                'service',
                'hosting',
                'dominio',
                'domain',
                'web',
                'desarrollo',
                'development',
                'mantenimiento',
                'maintenance',
                'soporte',
                'support',
                'backup',
                'ssl',
                'certificado',
                'renovar',
                'renew',
                'vence',
                'expires',
                'caducidad',
            ];

            $containsServiceKeyword = false;
            foreach ($serviceKeywords as $keyword)
            {
                if (strpos($normalizedMessage, $keyword) !== false)
                {
                    $containsServiceKeyword = true;
                    break;
                }
            }

            // Check for service reporting patterns
            $isServiceReport = (
                preg_match('/mi\s+(servicio|hosting|dominio|web)/i', $message) ||
                preg_match('/(tengo|necesito|quiero|contratar)\s+(un\s+)?(servicio|hosting|dominio)/i', $message) ||
                preg_match('/(vence|expira|caduca)\s+(mi|el)/i', $message) ||
                preg_match('/información\s+de\s+(mi\s+)?(servicio|hosting|dominio)/i', $message)
            );

            if (! $containsServiceKeyword && ! $isServiceReport)
            {
                return null;
            }

            // Get user's enterprises to check existing services
            $enterprises = $contact->enterprises()->get();
            $responseMessage = '';

            if ($enterprises->isEmpty())
            {
                $responseMessage = "Veo que estás preguntando sobre servicios. Actualmente no tienes empresas registradas en nuestro sistema.\n\n";
                $responseMessage .= "Para consultar sobre nuevos servicios o registrar tu empresa, puedes:\n";
                $responseMessage .= "• Contactarnos a través de: https://revisionalpha.com/contactenos\n";
                $responseMessage .= '• O déjanos más detalles aquí y te ayudaremos.';
            } else
            {
                $responseMessage = "📋 *Información de tus servicios:*\n\n";

                foreach ($enterprises as $enterprise)
                {
                    $services = Service::where('enterprise_id', $enterprise->id)
                        ->with(['category', 'currency'])
                        ->orderBy('status', 'desc')
                        ->orderBy('next_billing', 'asc')
                        ->get();

                    $responseMessage .= "🏢 *{$enterprise->name}*\n";

                    if ($services->isEmpty())
                    {
                        $responseMessage .= "• No hay servicios registrados actualmente\n\n";
                    } else
                    {
                        foreach ($services as $service)
                        {
                            $statusEmoji = $service->status == 1 ? '✅' : '⚠️';
                            $responseMessage .= "{$statusEmoji} *{$service->description}*\n";

                            if ($service->category)
                            {
                                $responseMessage .= "   Categoría: {$service->category->name}\n";
                            }

                            if ($service->price)
                            {
                                $currency = $service->currency ? $service->currency->symbol : '$';
                                $responseMessage .= "   Precio: {$currency}".number_format($service->price, 2)."\n";
                            }

                            if ($service->next_billing)
                            {
                                $nextBilling = \Carbon\Carbon::parse($service->next_billing)->format('d/m/Y');
                                $responseMessage .= "   Próxima facturación: {$nextBilling}\n";
                            }

                            if ($service->expires_at)
                            {
                                $expiresAt = \Carbon\Carbon::parse($service->expires_at)->format('d/m/Y');
                                $daysUntilExpiry = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($service->expires_at), false);

                                if ($daysUntilExpiry <= 30 && $daysUntilExpiry >= 0)
                                {
                                    $responseMessage .= "   ⚠️ Expira: {$expiresAt} (en {$daysUntilExpiry} días)\n";
                                } elseif ($daysUntilExpiry < 0)
                                {
                                    $responseMessage .= "   🔴 Expiró: {$expiresAt}\n";
                                } else
                                {
                                    $responseMessage .= "   Expira: {$expiresAt}\n";
                                }
                            }

                            $responseMessage .= "\n";
                        }
                    }
                }

                // Add helpful information
                $responseMessage .= "💡 *¿Necesitas ayuda?*\n";

                $responseMessage .= "• Área de clientes: https://revisionalpha.com/login\n";
                $responseMessage .= "• Soporte: https://revisionalpha.com/contactenos\n";
                $responseMessage .= '• O puedes escribirme aquí para consultas rápidas 😊';
            }

            // Send the response
            $this->sendWhatsApp($phoneNumber, $responseMessage);

            // Log the service inquiry
            Log::info('Service inquiry processed for user', [
                'phone' => $phoneNumber,
                'user_id' => $user->id,
                'enterprises_count' => $enterprises->count(),
                'message_preview' => substr($message, 0, 50),
            ]);

            return ['success' => true, 'message' => 'Service information sent'];
        } catch (\Exception $e)
        {
            Log::error('Error processing service commands: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Process product-related commands from WhatsApp messages
     */
    public function processProductCommands($phoneNumber, $message)
    {
        try
        {
            // Clean and normalize the message
            $normalizedMessage = strtolower(trim($message));

            // Check if message contains product-related keywords
            $productKeywords = [
                'productos',
                'servicios',
                'catalogo',
                'precios',
                'hosting',
                'dominio',
                'ssl',
                'backup',
                'desarrollo',
                'app',
                'consultoria',
                'soporte',
            ];

            $containsProductKeyword = false;
            foreach ($productKeywords as $keyword)
            {
                if (strpos($normalizedMessage, $keyword) !== false)
                {
                    $containsProductKeyword = true;
                    break;
                }
            }

            // Check for specific product commands
            $isProductCommand = (
                preg_match('/productos?/i', $message) ||
                preg_match('/servicios?/i', $message) ||
                preg_match('/catalogo/i', $message) ||
                preg_match('/precios?/i', $message)
            );

            if (! $containsProductKeyword && ! $isProductCommand)
            {
                return null;
            }

            // Send product catalog and get the message content
            $catalogMessage = $this->sendProductCatalog($phoneNumber);

            // Log the product inquiry
            Log::info('Product inquiry processed', [
                'phone' => $phoneNumber,
                'message_preview' => substr($message, 0, 50),
            ]);

            return ['success' => true, 'message' => $catalogMessage];
        } catch (\Exception $e)
        {
            Log::error('Error processing product commands: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Send product catalog via WhatsApp
     */
    private function sendProductCatalog($phoneNumber)
    {
        try
        {
            // Find user and their team for product filtering
            $user = $this->getUserByPhone($phoneNumber);
            $teamId = 1;  // Default to team 1 for demo

            if ($user && ! isset($user->is_contact) && $user->currentTeam)
            {
                $teamId = $user->currentTeam->id;
            }

            // Get active products that are WhatsApp enabled for the specific team
            $products = \App\Models\Product::where('team_id', $teamId)
                ->active()
                ->whatsAppEnabled()
                ->with(['category', 'currency'])
                ->orderBy('category_id')
                ->orderBy('price')
                ->get();

            if ($products->isEmpty())
            {
                $message = "📦 *Catálogo de Productos*\n\n";
                $message .= "Actualmente no hay productos disponibles.\n\n";
                $message .= '📞 Contacta a soporte: https://revisionalpha.com/contactenos';

                $this->sendWhatsApp($phoneNumber, $message);

                return $message;
            }

            $message = "🛍️ *Catálogo de Productos y Servicios*\n\n";

            // Group products by category
            $productsByCategory = $products->groupBy('category.name');

            foreach ($productsByCategory as $categoryName => $categoryProducts)
            {
                $message .= "📂 *{$categoryName}*\n";

                foreach ($categoryProducts as $product)
                {
                    $currency = $product->currency ? $product->currency->symbol : '$';
                    $message .= "• *{$product->name}*\n";
                    $message .= "  💰 {$currency}".number_format($product->price, 2)."\n";
                    $message .= '  📝 '.\Illuminate\Support\Str::limit($product->description, 80)."\n\n";
                }
            }

            $message .= "💡 *Para contratar:*\n";
            $message .= "• Escribe: *comprar [nombre del producto]*\n";
            $message .= "• O contacta soporte: https://revisionalpha.com/contactenos\n\n";
            $message .= '🛒 *Tu carrito:* Escribe *carrito* para ver tus productos seleccionados';

            $this->sendWhatsApp($phoneNumber, $message);

            return $message;
        } catch (\Exception $e)
        {
            Log::error('Error sending product catalog: '.$e->getMessage());

            // Fallback message
            $fallbackMessage = "📦 *Catálogo de Productos*\n\n";
            $fallbackMessage .= "Tenemos una amplia variedad de servicios:\n";
            $fallbackMessage .= "• Hosting y dominios\n";
            $fallbackMessage .= "• Desarrollo web y apps\n";
            $fallbackMessage .= "• Consultoría IT\n";
            $fallbackMessage .= "• Soporte técnico\n\n";
            $fallbackMessage .= '📞 Contacta a soporte: https://revisionalpha.com/contactenos';

            $this->sendWhatsApp($phoneNumber, $fallbackMessage);

            return $fallbackMessage;
        }
    }

    /**
     * Process DEMO command for automatic user registration
     */
    public function processDemoCommand($phoneNumber, $message)
    {
        try
        {
            $normalizedMessage = trim($message);

            // Check if message is exactly "DEMO" (case sensitive)
            if ($normalizedMessage === 'DEMO')
            {
                // Check if user already exists
                $existingUser = $this->getUserByPhone($phoneNumber);
                if ($existingUser && ! isset($existingUser->is_contact))
                {
                    $this->sendWhatsApp($phoneNumber, "¡Hola! Ya tienes una cuenta registrada en nuestro sistema. 😊\n\nPuedes acceder a tu área de cliente o contactarnos si necesitas ayuda.");

                    return ['success' => true, 'message' => 'User already exists'];
                }

                // Start demo registration process
                $this->sendWhatsApp($phoneNumber, "🎉 ¡Bienvenido al DEMO de Idoneo Technologies!\n\nPara crear tu cuenta demo, necesito algunos datos:\n\n👤 Por favor, envíame tu *nombre completo*:");

                // Store demo state in conversation metadata
                $this->storeDemoState($phoneNumber, 'awaiting_name');

                return ['success' => true, 'message' => 'Demo registration started'];
            }

            // Check if we're in a demo registration flow
            $demoState = $this->getDemoState($phoneNumber);
            if ($demoState)
            {
                return $this->handleDemoRegistrationStep($phoneNumber, $message, $demoState);
            }

            return null;
        } catch (\Exception $e)
        {
            Log::error('Error processing demo command: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Handle demo registration steps
     */
    private function handleDemoRegistrationStep($phoneNumber, $message, $state)
    {
        try
        {
            switch ($state)
            {
                case 'awaiting_name':
                    if (strlen(trim($message)) < 2)
                    {
                        $this->sendWhatsApp($phoneNumber, '❌ Por favor, ingresa un nombre válido (mínimo 2 caracteres):');

                        return ['success' => false, 'message' => 'Invalid name'];
                    }

                    // Store name and ask for email
                    $this->storeDemoData($phoneNumber, 'name', trim($message));
                    $this->storeDemoState($phoneNumber, 'awaiting_email');
                    $this->sendWhatsApp($phoneNumber, "✅ Perfecto, *{$message}*!\n\n📧 Ahora envíame tu *email*:");

                    return ['success' => true, 'message' => 'Name collected'];

                case 'awaiting_email':
                    $email = trim($message);

                    // Validate email format
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL))
                    {
                        $this->sendWhatsApp($phoneNumber, '❌ Por favor, ingresa un email válido (ejemplo: tu@email.com):');

                        return ['success' => false, 'message' => 'Invalid email'];
                    }

                    // Check if email already exists
                    $existingUser = \App\Models\User::where('email', $email)->first();
                    if ($existingUser)
                    {
                        $this->sendWhatsApp($phoneNumber, "❌ Este email ya está registrado en nuestro sistema.\n\n📧 Por favor, usa otro email:");

                        return ['success' => false, 'message' => 'Email already exists'];
                    }

                    // Create the demo user and contact
                    $result = $this->createDemoUser($phoneNumber, $email);

                    // Clear demo state
                    $this->clearDemoState($phoneNumber);

                    return $result;

                default:
                    $this->clearDemoState($phoneNumber);

                    return null;
            }
        } catch (\Exception $e)
        {
            Log::error('Error handling demo registration step: '.$e->getMessage());
            $this->clearDemoState($phoneNumber);

            return null;
        }
    }

    /**
     * Create demo user and contact
     */
    private function createDemoUser($phoneNumber, $email)
    {
        try
        {
            $name = $this->getDemoData($phoneNumber, 'name');
            $cleanPhone = \App\Helpers\PhoneHelper::clean($phoneNumber, '54', true);

            // Create user
            $user = \App\Models\User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $cleanPhone,
                'password' => \Illuminate\Support\Facades\Hash::make('Simplicity!'),
                'email_verified_at' => now(),
            ]);

            // Assign role
            $user->assignRole('client');

            // Associate with team 1
            $user->teams()->attach(1, ['role' => 'client']);

            // Create contact
            $contact = \App\Models\Contact::create([
                'team_id' => 1,
                'user_id' => $user->id,
                'name' => $name,
                'email' => $email,
                'phone' => $cleanPhone,
                'whatsapp' => $cleanPhone,
                'status_id' => 1,  // Active
                'creator_id' => 1,  // System user
            ]);

            // Associate contact with Idoneo Technologies (ID: 2)
            $idoneoTech = \App\Models\Enterprise::find(2);
            if ($idoneoTech)
            {
                $contact->enterprises()->attach(2);
            }

            // Send success message
            $responseMessage = "🎉 ¡Felicidades! Tu cuenta demo ha sido creada exitosamente.\n\n";
            $responseMessage .= "📧 Email: {$email}\n";
            $responseMessage .= "🔐 Contraseña temporal: Simplicity!\n\n";
            $responseMessage .= "🚀 Ahora tienes acceso a nuestros servicios de Idoneo Technologies:\n";
            $responseMessage .= "• Desarrollo de Software con IA\n";
            $responseMessage .= "• Gestión de Infraestructura en la Nube\n";
            $responseMessage .= "• Desarrollo de Apps Móviles\n";
            $responseMessage .= "• Consultoría en Ciberseguridad\n\n";
            $responseMessage .= "🌐 Visita nuestro sitio web: https://revisionalpha.com\n";
            $responseMessage .= "🌐 Accede a tu área de cliente: https://revisionalpha.com/login\n\n";
            $responseMessage .= '¡Gracias por probar nuestro demo! 🚀';

            $this->sendWhatsApp($phoneNumber, $responseMessage);

            // Clear demo data
            $this->clearDemoData($phoneNumber);

            Log::info('Demo user created successfully', [
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'email' => $email,
                'phone' => $phoneNumber,
            ]);

            return ['success' => true, 'message' => 'Demo user created'];
        } catch (\Exception $e)
        {
            Log::error('Error creating demo user: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Hubo un error creando tu cuenta demo. Por favor, intenta más tarde o contacta soporte.');
            $this->clearDemoData($phoneNumber);

            return ['success' => false, 'message' => 'Error creating user'];
        }
    }

    /**
     * Store demo registration state
     */
    private function storeDemoState($phoneNumber, $state)
    {
        \Illuminate\Support\Facades\Cache::put("demo_state_{$phoneNumber}", $state, 600);  // 10 minutes
    }

    /**
     * Get demo registration state
     */
    private function getDemoState($phoneNumber)
    {
        return \Illuminate\Support\Facades\Cache::get("demo_state_{$phoneNumber}");
    }

    /**
     * Clear demo registration state
     */
    private function clearDemoState($phoneNumber)
    {
        \Illuminate\Support\Facades\Cache::forget("demo_state_{$phoneNumber}");
    }

    /**
     * Store demo data
     */
    private function storeDemoData($phoneNumber, $key, $value)
    {
        $data = \Illuminate\Support\Facades\Cache::get("demo_data_{$phoneNumber}", []);
        $data[$key] = $value;
        \Illuminate\Support\Facades\Cache::put("demo_data_{$phoneNumber}", $data, 600);  // 10 minutes
    }

    /**
     * Get demo data
     */
    private function getDemoData($phoneNumber, $key)
    {
        $data = \Illuminate\Support\Facades\Cache::get("demo_data_{$phoneNumber}", []);

        return $data[$key] ?? null;
    }

    /**
     * Clear demo data
     */
    private function clearDemoData($phoneNumber)
    {
        \Illuminate\Support\Facades\Cache::forget("demo_data_{$phoneNumber}");
    }

    /**
     * Process QR code generation command
     */
    public function processQrCommand($phoneNumber, $message)
    {
        // Check if user is requesting QR code
        $qrKeywords = ['qr', 'QR', 'codigo', 'código', 'qrcode', 'QRCODE'];

        if (! in_array(trim($message), $qrKeywords))
        {
            return false;
        }

        try
        {
            // Get user information
            $user = $this->getUserByPhone($phoneNumber);

            if (! $user)
            {
                // If no user found, send generic QR
                $this->sendGenericQr($phoneNumber);

                return true;
            }

            // Generate personalized QR with user data
            $this->sendPersonalizedQr($phoneNumber, $user);

            return true;
        } catch (\Exception $e)
        {
            Log::error('Error processing QR command: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Hubo un error generando tu código QR. Por favor, intenta más tarde.');

            return true;
        }
    }

    /**
     * Send generic QR code for unknown users
     */
    private function sendGenericQr($phoneNumber)
    {
        try
        {
            // Generate QR code image
            $qrImagePath = $this->generateQrCodeImage(
                'https://revisionalpha.com/contactenos?name=NOMBRE&email=EMAIL&phone=PHONE&message=FRASE_INSPIRE_LARAVEL',
                'generic_qr',
            );

            if ($qrImagePath)
            {
                // Send QR image via WhatsApp
                $this->sendWhatsAppWithMedia($phoneNumber, $qrImagePath, 'generic_qr');

                $message = "📱 *Código QR de Contacto*\n\n";
                $message .= "🔗 *Enlace directo:*\n";
                $message .= "https://revisionalpha.com/contactenos?name=NOMBRE&email=EMAIL&phone=PHONE&message=FRASE_INSPIRE_LARAVEL\n\n";
                $message .= "💡 *Tip:* Personaliza el enlace reemplazando:\n";
                $message .= "• NOMBRE: Tu nombre completo\n";
                $message .= "• EMAIL: Tu correo electrónico\n";
                $message .= "• PHONE: Tu número de teléfono\n";
                $message .= "• FRASE_INSPIRE_LARAVEL: Tu mensaje personalizado\n\n";
                $message .= "🎯 *¿Necesitas ayuda?* Responde con 'ayuda' para más información.";

                $this->sendWhatsApp($phoneNumber, $message);
            } else
            {
                // Fallback to text-only if image generation fails
                $this->sendGenericQrTextOnly($phoneNumber);
            }
        } catch (\Exception $e)
        {
            Log::error('Error generating generic QR: '.$e->getMessage());
            $this->sendGenericQrTextOnly($phoneNumber);
        }
    }

    /**
     * Send personalized QR code for known users
     */
    private function sendPersonalizedQr($phoneNumber, $user)
    {
        try
        {
            // Prepare user data
            $name = $user->name ?? 'Usuario';
            $email = $user->email ?? 'email@ejemplo.com';
            $phone = $user->phone ?? $phoneNumber;

            // Create personalized message
            $personalMessage = "¡Hola {$name}! Estamos aquí para ayudarte con tus necesidades tecnológicas.";

            // Generate personalized URL
            $personalizedUrl = 'https://revisionalpha.com/contactenos?'.http_build_query([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'message' => $personalMessage,
            ]);

            // Generate QR code image
            $qrImagePath = $this->generateQrCodeImage($personalizedUrl, 'personalized_qr');

            if ($qrImagePath)
            {
                // Send QR image via WhatsApp
                $this->sendWhatsAppWithMedia($phoneNumber, $qrImagePath, 'personalized_qr');

                $message = "👤 *Código QR Personalizado*\n";
                $message .= "Generado especialmente para: *{$name}*\n\n";
                $message .= "📱 *Datos incluidos:*\n";
                $message .= "• Nombre: {$name}\n";
                $message .= "• Email: {$email}\n";
                $message .= "• Teléfono: {$phone}\n";
                $message .= "• Mensaje: {$personalMessage}\n\n";
                $message .= "🔗 *Enlace personalizado:*\n";
                $message .= $personalizedUrl."\n\n";
                $message .= "💡 *¿Qué puedes hacer?*\n";
                $message .= "• Compartir este QR con colegas\n";
                $message .= "• Usarlo en presentaciones\n";
                $message .= "• Agregarlo a tu firma de email\n\n";
                $message .= "🎯 *¿Necesitas modificar algo?* Responde con 'modificar' para cambiar los datos.";

                $this->sendWhatsApp($phoneNumber, $message);
            } else
            {
                // Fallback to text-only if image generation fails
                $this->sendPersonalizedQrTextOnly($phoneNumber, $user);
            }
        } catch (\Exception $e)
        {
            Log::error('Error generating personalized QR: '.$e->getMessage());
            $this->sendPersonalizedQrTextOnly($phoneNumber, $user);
        }
    }

    /**
     * Generate QR code image using chillerlan/php-qrcode
     */
    private function generateQrCodeImage($data, $filename)
    {
        try
        {
            // Create QR code instance with PNG output
            $qrcode = new \chillerlan\QRCode\QRCode(new QROptions([
                'outputType' => QROutputInterface::GDIMAGE_PNG,
            ]));

            // Generate QR code as data URI (PNG)
            $qrImageDataUri = $qrcode->render($data);

            // Extract binary PNG data from data URI
            $pngBase64 = str_replace('data:image/png;base64,', '', $qrImageDataUri);
            $pngBinary = base64_decode($pngBase64);

            // Create storage directory if it doesn't exist
            $storagePath = storage_path('app/public/qr-codes');
            if (! file_exists($storagePath))
            {
                mkdir($storagePath, 0755, true);
            }

            // Generate unique filename
            $uniqueFilename = $filename.'_'.time().'_'.uniqid().'.png';
            $fullPath = $storagePath.'/'.$uniqueFilename;

            // Save binary PNG to file
            file_put_contents($fullPath, $pngBinary);

            // Return the public URL path
            return 'storage/qr-codes/'.$uniqueFilename;
        } catch (\Exception $e)
        {
            Log::error('Error generating QR code image: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send WhatsApp message with media attachment (public for gateway use).
     *
     * @param  string|null  $caption  Optional text (e.g. "[Mensaje de voz]") to send with the media
     */
    public function sendWhatsAppWithMedia($phoneNumber, $mediaPath, $type = 'media', $caption = null)
    {
        $sender = $this->getSender();
        if ($sender !== $this)
        {
            return $sender->sendMedia($phoneNumber, $mediaPath, $caption);
        }

        return $this->doSendWhatsAppWithMedia($phoneNumber, $mediaPath, $type, $caption);
    }

    /**
     * Send WhatsApp message with media attachment using Twilio API
     */
    private function doSendWhatsAppWithMedia($phoneNumber, $mediaPath, $type, $caption = null)
    {
        try
        {
            $fullMediaPath = public_path($mediaPath);

            if (! file_exists($fullMediaPath))
            {
                Log::error("Media file not found: {$fullMediaPath}");

                return false;
            }

            // Format phone number for WhatsApp
            $whatsappTo = 'whatsapp:'.$this->formatPhoneNumber($phoneNumber);
            $whatsappFrom = 'whatsapp:'.$this->config['whatsapp_from'];

            // Get file info
            $fileSize = filesize($fullMediaPath);
            $fileExtension = pathinfo($fullMediaPath, PATHINFO_EXTENSION);
            $mimeType = $this->getMimeType($fileExtension);

            // Check file size (Twilio limit: 16MB for media)
            if ($fileSize > 16 * 1024 * 1024)
            {
                Log::error("File too large for Twilio: {$fileSize} bytes");

                return false;
            }

            // Get the full public URL for the media
            $publicUrl = url($mediaPath);

            // Body text: use caption when provided (e.g. voice message); for QR types use the legacy text
            $isQrType = in_array($type, ['generic_qr', 'personalized_qr'], true);
            $bodyText = $caption ?? ($isQrType ? '🔄 Tu código QR está listo! Escanéalo para acceder a revision alpha.' : '🎤 Mensaje de voz');

            // For local development, Twilio cannot fetch .test URLs; send text only (caption or fallback)
            if (strpos($publicUrl, 'localhost') !== false || strpos($publicUrl, '.test') !== false)
            {
                Log::info("Local environment detected, sending text instead of media: {$publicUrl}");
                $message = $this->client->messages->create(
                    $whatsappTo,
                    [
                        'from' => $whatsappFrom,
                        'body' => $bodyText,
                    ],
                );
            } else
            {
                // Production: send with media and optional caption
                $message = $this->client->messages->create(
                    $whatsappTo,
                    [
                        'from' => $whatsappFrom,
                        'body' => $bodyText,
                        'mediaUrl' => [$publicUrl],
                    ],
                );
            }

            if ($message->sid)
            {
                Log::info('WhatsApp media message sent successfully', [
                    'message_sid' => $message->sid,
                    'to' => $phoneNumber,
                    'media_path' => $mediaPath,
                    'type' => $type,
                    'status' => $message->status,
                ]);

                // Save to conversation history
                $this->saveMediaMessageToHistory($phoneNumber, $message, $mediaPath, $type);

                return true;
            } else
            {
                Log::error('Failed to send WhatsApp media message: No message SID returned');

                return false;
            }
        } catch (\Exception $e)
        {
            Log::error('Error sending WhatsApp media: '.$e->getMessage(), [
                'phone_number' => $phoneNumber,
                'media_path' => $mediaPath,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Format phone number for WhatsApp
     */
    private function formatPhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Ensure it starts with country code
        if (strlen($cleanNumber) === 9 && substr($cleanNumber, 0, 1) === '9')
        {
            // Argentine mobile number, add 54
            $cleanNumber = '54'.$cleanNumber;
        } elseif (strlen($cleanNumber) === 10 && substr($cleanNumber, 0, 2) === '15')
        {
            // Argentine mobile with 15 prefix, replace with 54 9
            $cleanNumber = '54'.substr($cleanNumber, 2);
        }

        return $cleanNumber;
    }

    /**
     * Get MIME type for file extension
     */
    private function getMimeType($extension)
    {
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Save media message to conversation history
     */
    private function saveMediaMessageToHistory($phoneNumber, $twilioMessage, $mediaPath, $type)
    {
        try
        {
            $conversation = Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => preg_replace('/[^0-9]/', '', $this->config['whatsapp_from']),
                'to' => preg_replace('/[^0-9]/', '', $phoneNumber),
                'body' => '🔄 Tu código QR está listo! Escanéalo para acceder a revision alpha.',
                'status' => $twilioMessage->status ?? 'sent',
                'direction' => 'outbound',
                'team_id' => $this->team ? $this->team->id : null,
                'media' => [
                    [
                        'url' => url($mediaPath),
                        'content_type' => 'image/png',
                        'type' => $type,
                        'local_path' => $mediaPath,
                    ],
                ],
                'metadata' => [
                    'twilio_message_sid' => $twilioMessage->sid,
                    'qr_type' => $type,
                    'media_sent' => true,
                    'file_path' => $mediaPath,
                ],
            ]);

            Log::info('Media message saved to conversation history', [
                'conversation_id' => $conversation->id,
                'message_sid' => $twilioMessage->sid,
                'phone_number' => $phoneNumber,
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error saving media message to history: '.$e->getMessage());
        }
    }

    /**
     * Fallback methods for text-only responses
     */
    private function sendGenericQrTextOnly($phoneNumber)
    {
        $message = "🔄 Generando tu código QR...\n\n";
        $message .= "📱 *Código QR de Contacto*\n";
        $message .= "Este QR te llevará a nuestra página de contacto.\n\n";
        $message .= "🔗 *Enlace directo:*\n";
        $message .= "https://revisionalpha.com/contactenos?name=NOMBRE&email=EMAIL&phone=PHONE&message=FRASE_INSPIRE_LARAVEL\n\n";
        $message .= "💡 *Tip:* Personaliza el enlace reemplazando:\n";
        $message .= "• NOMBRE: Tu nombre completo\n";
        $message .= "• EMAIL: Tu correo electrónico\n";
        $message .= "• PHONE: Tu número de teléfono\n";
        $message .= "• FRASE_INSPIRE_LARAVEL: Tu mensaje personalizado\n\n";
        $message .= "🎯 *¿Necesitas ayuda?* Responde con 'ayuda' para más información.";

        $this->sendWhatsApp($phoneNumber, $message);
    }

    private function sendPersonalizedQrTextOnly($phoneNumber, $user)
    {
        $name = $user->name ?? 'Usuario';
        $email = $user->email ?? 'email@ejemplo.com';
        $phone = $user->phone ?? $phoneNumber;

        $personalMessage = "¡Hola {$name}! Estamos aquí para ayudarte con tus necesidades tecnológicas.";

        $personalizedUrl = 'https://revisionalpha.com/contactenos?'.http_build_query([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $personalMessage,
        ]);

        $message = "🔄 Generando tu código QR personalizado...\n\n";
        $message .= "👤 *Código QR Personalizado*\n";
        $message .= "Generado especialmente para: *{$name}*\n\n";
        $message .= "📱 *Datos incluidos:*\n";
        $message .= "• Nombre: {$name}\n";
        $message .= "• Email: {$email}\n";
        $message .= "• Teléfono: {$phone}\n";
        $message .= "• Mensaje: {$personalMessage}\n\n";
        $message .= "🔗 *Enlace personalizado:*\n";
        $message .= $personalizedUrl."\n\n";
        $message .= "💡 *¿Qué puedes hacer?*\n";
        $message .= "• Compartir este QR con colegas\n";
        $message .= "• Usarlo en presentaciones\n";
        $message .= "• Agregarlo a tu firma de email\n\n";
        $message .= "🎯 *¿Necesitas modificar algo?* Responde con 'modificar' para cambiar los datos.";

        $this->sendWhatsApp($phoneNumber, $message);
    }

    /**
     * Send WhatsApp message with interactive quick reply buttons
     */
    private function sendWhatsAppWithButtons($phoneNumber, $message, $buttons)
    {
        try
        {
            $formattedTo = 'whatsapp:'.$phoneNumber;
            $statusCallbackUrl = url(route('twilio.status'));

            // Format buttons for Twilio WhatsApp Interactive Messages
            $interactiveData = [
                'type' => 'button',
                'body' => [
                    'text' => $message,
                ],
                'action' => [
                    'buttons' => array_map(function ($button)
                    {
                        return [
                            'type' => 'reply',
                            'reply' => [
                                'id' => $button['reply']['id'],
                                'title' => substr($button['reply']['title'], 0, 20),  // WhatsApp limit: 20 chars
                            ],
                        ];
                    }, $buttons),
                ],
            ];

            // Try to send interactive message (requires Twilio configuration)
            try
            {
                $twilioMessage = $this->client->messages->create(
                    $formattedTo,
                    [
                        'from' => 'whatsapp:'.$this->config['whatsapp_from'],
                        'statusCallback' => $statusCallbackUrl,
                        'body' => json_encode($interactiveData),  // Send as JSON for now
                    ],
                );
            } catch (\Exception $interactiveError)
            {
                // Fallback: Send as regular message with numbered options
                Log::warning('Interactive buttons not supported, using text fallback');

                $fallbackMessage = $message."\n\n";
                foreach ($buttons as $index => $button)
                {
                    $fallbackMessage .= ($index + 1).'. '.$button['reply']['title']."\n";
                }
                $fallbackMessage .= "\nResponde con el número de tu opción.";

                $twilioMessage = $this->client->messages->create(
                    $formattedTo,
                    [
                        'from' => 'whatsapp:'.$this->config['whatsapp_from'],
                        'statusCallback' => $statusCallbackUrl,
                        'body' => $fallbackMessage,
                    ],
                );
            }

            // Save outbound message to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'from_number' => $this->formatPhoneNumber($this->config['whatsapp_from']),
                'to_number' => $this->formatPhoneNumber($phoneNumber),
                'direction' => 'outbound',
                'status' => $twilioMessage->status,
                'body' => $message,
                'channel' => 'whatsapp',
                'metadata' => json_encode(['buttons' => $buttons]),
            ]);

            Log::info('WhatsApp message with buttons sent', [
                'to' => $phoneNumber,
                'sid' => $twilioMessage->sid,
                'buttons_count' => count($buttons),
            ]);

            return $twilioMessage;
        } catch (\Exception $e)
        {
            Log::error('Error sending WhatsApp with buttons: '.$e->getMessage());

            // Fallback to regular message
            return $this->sendWhatsApp($phoneNumber, $message);
        }
    }

    /**
     * Process cart-related commands from WhatsApp messages
     */
    public function processCartCommands($phoneNumber, $message)
    {
        try
        {
            // DEBUG: Log phone number and message for troubleshooting
            Log::info('Cart command received', [
                'phone_number' => $phoneNumber,
                'message' => $message,
                'phone_length' => strlen($phoneNumber),
                'phone_format' => 'Raw from WhatsApp',
            ]);

            // Clean and normalize the message
            $normalizedMessage = strtolower(trim($message));

            // Find user and their team for product filtering
            $user = $this->getUserByPhone($phoneNumber);
            $teamId = 1;  // Default to team 1 for demo

            if ($user && ! isset($user->is_contact) && $user->currentTeam)
            {
                $teamId = $user->currentTeam->id;
            }

            // Set cart session for this phone number
            Cart::session($phoneNumber);

            // DEBUG: Log cart session and current contents
            $currentCartItems = Cart::getContent();
            Log::info('Cart session set', [
                'phone_number' => $phoneNumber,
                'cart_items_count' => $currentCartItems->count(),
                'cart_total' => Cart::getTotal(),
                'storage_key' => 'cart_'.$phoneNumber,
            ]);

            // Check for checkout confirmation commands (YES responses)
            if (in_array($normalizedMessage, ['si', 'sí', 'yes', 'confirmar', 'aceptar', 'proceder']))
            {
                return $this->confirmCheckout($phoneNumber, $teamId);
            }

            // Check for continue shopping commands (NO responses)
            if (in_array($normalizedMessage, ['no', 'nah', 'seguir comprando', 'continuar', 'agregar mas', 'cancelar']))
            {
                return $this->continueShoppingFromCheckout($phoneNumber);
            }

            // Check for add to cart commands (comprar, contratar)
            if (preg_match('/^(comprar|contratar|compra|contrata)\s+(.+)/i', $normalizedMessage, $matches))
            {
                $productName = trim($matches[2]);

                return $this->addToCart($phoneNumber, $productName, $teamId);
            }

            // Check for cart view commands
            if (in_array($normalizedMessage, ['carrito', 'ver carrito', 'mi carrito', 'cart']))
            {
                return $this->viewCart($phoneNumber, $teamId);
            }

            // Check for clear cart commands
            if (in_array($normalizedMessage, ['vaciar carrito', 'limpiar carrito', 'borrar carrito', 'clear cart']))
            {
                return $this->clearCart($phoneNumber);
            }

            // Check for checkout commands
            if (in_array($normalizedMessage, ['checkout', 'finalizar', 'finalizar compra', 'pagar', 'comprar todo']))
            {
                return $this->initiateCheckout($phoneNumber, $teamId);
            }

            return null;
        } catch (\Exception $e)
        {
            Log::error('Error processing cart commands: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Add product to cart
     */
    private function addToCart($phoneNumber, $productName, $teamId)
    {
        try
        {
            // Search for product by name
            $product = \App\Models\Product::where('team_id', $teamId)
                ->where('name', 'LIKE', "%{$productName}%")
                ->where('status', true)
                ->where('whatsapp_enabled', true)
                ->first();

            if (! $product)
            {
                $response = "❌ **Producto no encontrado**: '{$productName}'\n\n";
                $response .= "📋 Escribe 'productos' para ver nuestro catálogo completo\n";
                $response .= '💡 **Tip**: Usa el nombre exacto del producto';

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => 'Product not found'];
            }

            // Check if product is already in cart
            $cartItems = Cart::getContent();
            $existingItem = $cartItems->where('id', $product->id)->first();

            if ($existingItem)
            {
                // Update quantity
                Cart::update($product->id, [
                    'quantity' => [
                        'relative' => false,
                        'value' => $existingItem->quantity + 1,
                    ],
                ]);
                $quantity = $existingItem->quantity + 1;
            } else
            {
                // Add new item
                Cart::add([
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'quantity' => 1,
                    'attributes' => [
                        'team_id' => $teamId,
                        'currency_id' => $product->currency_id,
                        'description' => $product->description,
                        'category_name' => $product->category->name ?? '',
                    ],
                ]);
                $quantity = 1;
            }

            $currency = $product->currency ? $product->currency->symbol : '$';

            $response = "✅ **{$product->name}** agregado al carrito!\n\n";
            $response .= "💰 **Precio**: {$currency}".number_format($product->price, 2)."\n";
            $response .= "📦 **Cantidad**: {$quantity}\n";
            $response .= '🏷️ **Categoría**: '.($product->category->name ?? 'General')."\n\n";
            $response .= "🛒 **Total del carrito**: {$currency}".number_format(Cart::getTotal(), 2)."\n\n";
            $response .= "**Opciones:**\n";
            $response .= "• Escribe 'carrito' para ver todos tus productos\n";
            $response .= "• Escribe 'comprar [producto]' para agregar más\n";
            $response .= "• Escribe 'checkout' para finalizar tu compra";

            $this->sendWhatsApp($phoneNumber, $response);

            Log::info('Product added to cart', [
                'phone' => $phoneNumber,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'cart_total' => Cart::getTotal(),
            ]);

            return ['success' => true, 'message' => 'Product added to cart'];
        } catch (\Exception $e)
        {
            Log::error('Error adding to cart: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Error al agregar producto. Inténtalo nuevamente.');

            return ['success' => false, 'message' => 'Error adding to cart'];
        }
    }

    /**
     * View cart contents
     */
    private function viewCart($phoneNumber, $teamId)
    {
        try
        {
            $cartItems = Cart::getContent();

            if ($cartItems->isEmpty())
            {
                $response = "🛒 **Tu carrito está vacío**\n\n";
                $response .= "📋 Escribe 'productos' para ver nuestro catálogo\n";
                $response .= "💡 **Tip**: Usa 'comprar [producto]' para agregar items";

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => true, 'message' => $response];
            }

            $response = "🛒 **Tu Carrito de Compras**\n\n";

            foreach ($cartItems as $item)
            {
                $response .= "• **{$item->name}**\n";
                $response .= '  💰 $'.number_format($item->price, 2)." x {$item->quantity}\n";
                $response .= '  💵 Subtotal: $'.number_format($item->price * $item->quantity, 2)."\n";

                if (! empty($item->attributes->category_name))
                {
                    $response .= "  🏷️ {$item->attributes->category_name}\n";
                }
                $response .= "\n";
            }

            $response .= '💰 **TOTAL: $'.number_format(Cart::getTotal(), 2)."**\n";
            $response .= '📦 **Items**: '.Cart::getTotalQuantity()."\n\n";

            $response .= "**Opciones:**\n";
            $response .= "• Escribe 'checkout' para finalizar tu compra\n";
            $response .= "• Escribe 'comprar [producto]' para agregar más\n";
            $response .= "• Escribe 'vaciar carrito' para empezar de nuevo";

            $this->sendWhatsApp($phoneNumber, $response);

            Log::info('Cart viewed', [
                'phone' => $phoneNumber,
                'items_count' => $cartItems->count(),
                'total' => Cart::getTotal(),
            ]);

            return ['success' => true, 'message' => $response];
        } catch (\Exception $e)
        {
            Log::error('Error viewing cart: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Error al mostrar carrito. Inténtalo nuevamente.');

            return ['success' => false, 'message' => 'Error viewing cart'];
        }
    }

    /**
     * Clear cart
     */
    private function clearCart($phoneNumber)
    {
        try
        {
            Cart::clear();

            $response = "🗑️ **Carrito vaciado exitosamente**\n\n";
            $response .= "📋 Escribe 'productos' para ver nuestro catálogo\n";
            $response .= "💡 Usa 'comprar [producto]' para agregar nuevos items";

            $this->sendWhatsApp($phoneNumber, $response);

            Log::info('Cart cleared', ['phone' => $phoneNumber]);

            return ['success' => true, 'message' => 'Cart cleared'];
        } catch (\Exception $e)
        {
            Log::error('Error clearing cart: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Error al vaciar carrito. Inténtalo nuevamente.');

            return ['success' => false, 'message' => 'Error clearing cart'];
        }
    }

    /**
     * Initiate checkout process with confirmation
     */
    private function initiateCheckout($phoneNumber, $teamId)
    {
        try
        {
            $cartItems = Cart::getContent();

            if ($cartItems->isEmpty())
            {
                $response = "❌ **Tu carrito está vacío**\n\n";
                $response .= "📋 Escribe 'productos' para ver nuestro catálogo";

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => $response];
            }

            $total = Cart::getTotal();

            $response = "🛒 **Resumen de tu compra**\n\n";

            foreach ($cartItems as $item)
            {
                $response .= "• {$item->name} x{$item->quantity} - \$".number_format($item->price * $item->quantity, 2)."\n";
            }

            $response .= "\n💰 **TOTAL: \$".number_format($total, 2)."**\n";
            $response .= '📦 **Items**: '.Cart::getTotalQuantity()."\n\n";
            $response .= "❓ **¿Quieres confirmar tu compra?**\n\n";
            $response .= 'Responde *SÍ* para proceder o *NO* para seguir comprando.';

            $this->sendWhatsApp($phoneNumber, $response);

            // Log checkout initiation
            Log::info('Checkout initiated - awaiting confirmation', [
                'phone' => $phoneNumber,
                'items_count' => $cartItems->count(),
                'total' => $total,
                'items' => $cartItems->toArray(),
            ]);

            return ['success' => true, 'message' => $response];
        } catch (\Exception $e)
        {
            Log::error('Error initiating checkout: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Error al procesar checkout. Contacta soporte.');

            return ['success' => false, 'message' => 'Error in checkout'];
        }
    }

    /**
     * Confirm checkout and process final purchase
     */
    private function confirmCheckout($phoneNumber, $teamId)
    {
        try
        {
            $cartItems = Cart::getContent();

            if ($cartItems->isEmpty())
            {
                $response = "❌ **Tu carrito está vacío**\n\n";
                $response .= "📋 Escribe 'productos' para ver nuestro catálogo";

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => $response];
            }

            $total = Cart::getTotal();

            $response = "✅ **¡Compra Confirmada!**\n\n";
            $response .= "📋 **Resumen del pedido:**\n";

            foreach ($cartItems as $item)
            {
                $response .= "• {$item->name} x{$item->quantity} - \$".number_format($item->price * $item->quantity, 2)."\n";
            }

            $response .= "\n💰 **TOTAL: \$".number_format($total, 2)."**\n";
            $response .= '📦 **Items**: '.Cart::getTotalQuantity()."\n\n";

            $response .= "📧 **Próximos pasos:**\n";
            $response .= "• Te enviaremos un email con los detalles completos\n";
            $response .= "• Incluirá enlaces de pago seguros y opciones de entrega\n";
            $response .= '• Número de orden: #'.strtoupper(substr(md5($phoneNumber.time()), 0, 8))."\n\n";

            $response .= "💳 **El proceso continúa por email:**\n";
            $response .= "• Enlaces de pago seguros\n";
            $response .= "• Instrucciones detalladas\n";
            $response .= "• Confirmación de entrega\n\n";

            $response .= "📞 **¿Dudas? Contáctanos:**\n";
            $response .= "• WhatsApp: Responde aquí directamente\n";
            $response .= "• Web: https://revisionalpha.com/contactenos\n\n";

            $response .= "¡Gracias por confiar en nosotros! 🎉\n";
            $response .= '📬 Revisa tu email en los próximos minutos.';

            $this->sendWhatsApp($phoneNumber, $response);

            // Log successful checkout
            Log::info('Checkout confirmed and processed', [
                'phone' => $phoneNumber,
                'items_count' => $cartItems->count(),
                'total' => $total,
                'items' => $cartItems->toArray(),
                'order_id' => strtoupper(substr(md5($phoneNumber.time()), 0, 8)),
            ]);

            // Clear the cart after successful checkout
            Cart::clear();

            return ['success' => true, 'message' => $response];
        } catch (\Exception $e)
        {
            Log::error('Error confirming checkout: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Error al confirmar compra. Por favor inténtalo nuevamente.');

            return ['success' => false, 'message' => 'Error confirming checkout'];
        }
    }

    /**
     * Continue shopping from checkout
     */
    private function continueShoppingFromCheckout($phoneNumber)
    {
        try
        {
            $response = "🛍️ **¡Perfecto!**\n\n";
            $response .= "Puedes seguir agregando productos a tu carrito.\n\n";
            $response .= "📋 **Opciones disponibles:**\n";
            $response .= "• Escribe '*productos*' para ver el catálogo completo\n";
            $response .= "• Usa '*comprar [producto]*' para agregar items\n";
            $response .= "• Escribe '*carrito*' para ver tu carrito actual\n";
            $response .= "• Usa '*checkout*' cuando estés listo para finalizar\n\n";
            $response .= '💡 **Tip:** Tu carrito actual se mantiene guardado';

            $this->sendWhatsApp($phoneNumber, $response);

            Log::info('User chose to continue shopping', [
                'phone' => $phoneNumber,
                'cart_items_count' => Cart::getContent()->count(),
            ]);

            return ['success' => true, 'message' => $response];
        } catch (\Exception $e)
        {
            Log::error('Error processing continue shopping: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Error al procesar solicitud. Inténtalo nuevamente.');

            return ['success' => false, 'message' => 'Error processing continue shopping'];
        }
    }
}
