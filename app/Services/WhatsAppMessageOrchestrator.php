<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Helpers\WhatsAppCartSessionKey;
use App\Helpers\WhatsAppOutboundText;
use App\Mail\IncomingMessageNotification;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Store;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppContactSheetImportService;
use App\Services\WhatsApp\WhatsAppInvoiceSheetImportService;
use App\Services\WhatsApp\WhatsAppTaskSheetImportService;
use App\Support\NewUserWelcomeEmailNotifier;
use Carbon\Carbon;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QROptions;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class WhatsAppMessageOrchestrator implements WhatsAppGateway
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

        return new static($team);
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

        // Only try without-country-code for Spanish numbers (34 + 9 digits) to avoid false matches
        if (strlen($cleanNumber) === 11 && str_starts_with($cleanNumber, '34'))
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
     * Contact row for this team's WhatsApp number (phone digits or phone source), for per-contact preferences.
     * Must match {@see UserResolverService::findContactByNormalizedPhone} rules so we resolve the same contact
     * the user sees in CRM (e.g. DB phone stored as 9 digits, WhatsApp inbound as 34 + 9 digits).
     */
    private function findTeamContactIdByPhoneDigits(string $cleanDigits, ?int $contactTeamId = null): ?int
    {
        $teamId = $contactTeamId ?? $this->team?->id;
        if ($cleanDigits === '' || $teamId === null)
        {
            return null;
        }

        $id = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('phone', $cleanDigits)
            ->value('id');

        return $id !== null ? (int) $id : null;
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
    private function persistWhatsAppExchangeToAgentContext(string $phone, string $userMessage, string $assistantMessage, array $replyResponse = [], ?int $contextTeamId = null): void
    {
        try
        {
            $userResolver = app(UserResolverService::class);
            $contextService = app(AgentConversationContextService::class);
            $teamId = $contextTeamId ?? ($this->team ? (int) $this->team->id : null);
            $contextUser = $userResolver->resolveUserForConversation($phone, null, $teamId);
            if ($contextUser !== null)
            {
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
                    (bool) ($replyResponse['assistant_flow_routing_key_specified'] ?? false),
                    $replyResponse['assistant_flow_routing_key'] ?? null,
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
            $statusCallbackUrl = $this->team ? $this->team->getTwilioStatusCallbackUrl() : url(route('whatsapp.status'));

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

    /**
     * Cache flag: user was asked "¿Confirmar compra?" after checkout — only then treat SÍ/NO as cart commands.
     */
    private function checkoutPendingCacheKey(string $phoneNumber): string
    {
        $digits = WhatsAppCartSessionKey::fromPhone($phoneNumber);

        return 'whatsapp_checkout_pending:'.$digits;
    }

    private function forgetCheckoutPending(string $phoneNumber): void
    {
        Cache::forget($this->checkoutPendingCacheKey($phoneNumber));
    }

    private function rememberCheckoutPending(string $phoneNumber): void
    {
        Cache::put($this->checkoutPendingCacheKey($phoneNumber), true, now()->addMinutes(45));
    }

    private function hasCheckoutPending(string $phoneNumber): bool
    {
        return (bool) Cache::get($this->checkoutPendingCacheKey($phoneNumber));
    }

    /**
     * Team that owns the WhatsApp inbox (webhook context), not the customer's default team.
     */
    private function resolveCartTeamId(string $phoneNumber): int
    {
        if ($this->team)
        {
            return (int) $this->team->id;
        }

        $user = $this->getUserByPhone($phoneNumber);
        if ($user && ! isset($user->is_contact) && $user->currentTeam)
        {
            return (int) $user->currentTeam->id;
        }

        return 1;
    }

    /**
     * Normalize short replies (e.g. "SÍ!", "si.") for keyword matching.
     */
    private function normalizeWhatsAppCommandText(string $message): string
    {
        $t = mb_strtolower(trim($message));
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t, " \t\n\r\0\x0B!?.¡¿");
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
        $message = WhatsAppOutboundText::sanitize((string) $message);

        if (config('whatsapp.driver') === 'local' && app()->bound(WhatsAppGateway::class))
        {
            $sender = $this->getSender();
            if ($sender !== $this)
            {
                return $sender->sendMessage($to, $message, $metadata, $userId);
            }

            if ($this->team !== null && $this->team->getWhatsAppServiceBaseUrl() !== '')
            {
                $localGateway = new LocalWhatsAppGateway(
                    $this->team->getWhatsAppServiceBaseUrl(),
                    config('whatsapp.local.webhook_secret'),
                    $this->team->id,
                );

                return $localGateway->sendMessage($to, $message, $metadata, $userId);
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
            $statusCallbackUrl = $this->team ? $this->team->getTwilioStatusCallbackUrl() : url(route('whatsapp.status'));

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
            $resolvedInboundTeamId = Team::resolveInboundWebhookTeamId($this->team?->id, $cleanTo);
            Log::info('WhatsApp inbound resolved context', [
                'message_sid' => $messageSid,
                'channel_hint_from' => $from,
                'channel_hint_to' => $to,
                'from_digits' => $cleanFrom,
                'to_digits' => $cleanTo,
                'route_team_id' => $this->team?->id,
                'resolved_team_id' => $resolvedInboundTeamId,
            ]);

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

            if ($channel === 'whatsapp' && config('whatsapp.driver') === 'local')
            {
                if ($this->team === null || $cleanTo === '' || strlen($cleanTo) < 8)
                {
                    Log::warning('Incoming WhatsApp message ignored: unresolved local route', [
                        'message_sid' => $messageSid,
                        'from' => $cleanFrom,
                        'to' => $cleanTo,
                        'team_id' => $this->team?->id,
                    ]);

                    return response()->json(['status' => 'ignored', 'reason' => 'unresolved_route'], 202);
                }
            }

            if ($channel === 'whatsapp' && $this->team !== null)
            {
                $isBlacklistedSender = app(TeamInboundAssistantPolicy::class)
                    ->isBlacklistedWhatsAppPhone($this->team, $cleanFrom);
                if ($isBlacklistedSender)
                {
                    Log::info('Incoming WhatsApp message ignored: blacklisted sender', [
                        'message_sid' => $messageSid,
                        'from' => $cleanFrom,
                        'to' => $cleanTo,
                        'team_id' => $this->team->id,
                    ]);

                    return response()->json(['status' => 'ignored', 'reason' => 'blacklisted_sender'], 200);
                }
            }

            if ($channel === 'whatsapp' && $resolvedInboundTeamId !== null && strlen($cleanFrom) >= 8)
            {
                \App\Models\Prospect::captureFromWhatsApp($cleanFrom, $resolvedInboundTeamId);
            }

            if (! empty($messageSid))
            {
                $existingConversation = Conversation::query()
                    ->where('message_sid', (string) $messageSid)
                    ->first();
                if ($existingConversation !== null)
                {
                    Log::info('Duplicate incoming message ignored', [
                        'message_sid' => $messageSid,
                        'conversation_id' => $existingConversation->id,
                        'from' => $cleanFrom,
                        'to' => $cleanTo,
                        'team_id' => $resolvedInboundTeamId,
                    ]);

                    return response()->json([
                        'status' => 'success',
                        'conversation_id' => $existingConversation->id,
                        'duplicate' => true,
                    ]);
                }
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
                    if (is_string($mediaUrl) && trim($mediaUrl) !== '')
                    {
                        $media[] = [
                            'url' => trim($mediaUrl),
                            'content_type' => is_string($contentType) && trim($contentType) !== '' ? trim($contentType) : 'application/octet-stream',
                        ];
                    }
                }
            }

            if ($media === [])
            {
                // Defensive fallback for non-standard providers that send MediaUrlN without NumMedia.
                foreach (range(0, 9) as $i)
                {
                    $mediaUrl = $request->input("MediaUrl{$i}");
                    if (! is_string($mediaUrl) || trim($mediaUrl) === '')
                    {
                        continue;
                    }
                    $contentType = $request->input("MediaContentType{$i}");
                    $media[] = [
                        'url' => trim($mediaUrl),
                        'content_type' => is_string($contentType) && trim($contentType) !== '' ? trim($contentType) : 'application/octet-stream',
                    ];
                }
            }
            Log::info('WhatsApp inbound media detection', [
                'message_sid' => $messageSid,
                'num_media_input' => $numMedia,
                'media_detected_count' => count($media),
                'media_urls' => array_values(array_filter(array_map(static fn ($item) => $item['url'] ?? null, $media))),
            ]);

            $isMediaPlaceholderBody = preg_match('/^\s*\[(image|imagen|photo|foto|document|documento|video|sticker)\]\s*$/iu', (string) $body) === 1;
            if ($channel === 'whatsapp' && $media === [] && $isMediaPlaceholderBody)
            {
                Log::warning('WhatsApp inbound media placeholder without retrievable media URL', [
                    'message_sid' => $messageSid,
                    'team_id' => $resolvedInboundTeamId,
                    'body' => (string) $body,
                    'payload_keys' => array_keys($request->all()),
                ]);

                try
                {
                    $this->sendWhatsApp(
                        $cleanFrom,
                        'Recibí un adjunto, pero no llegó el enlace del archivo para procesarlo. Reenviámelo como foto/documento para poder cargarlo automáticamente.',
                    );
                } catch (\Throwable $e)
                {
                    Log::warning('WhatsApp missing-media notice send failed', [
                        'message_sid' => $messageSid,
                        'to' => $cleanFrom,
                        'error' => $e->getMessage(),
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'missing_media_url' => true,
                ]);
            }

            // Save incoming message to database (idempotent by message_sid when provided).
            $conversationPayload = [
                'channel' => $channel,
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'body' => $body,
                'status' => 'received',
                'direction' => 'inbound',
                'media' => ! empty($media) ? $media : null,
                'metadata' => $request->except(['_token']),
            ];

            if ($resolvedInboundTeamId !== null)
            {
                $conversationPayload['team_id'] = $resolvedInboundTeamId;
            }

            try
            {
                if (! empty($messageSid))
                {
                    $conversation = Conversation::firstOrCreate(
                        ['message_sid' => (string) $messageSid],
                        $conversationPayload,
                    );
                } else
                {
                    $conversation = Conversation::create(array_merge(
                        $conversationPayload,
                        ['message_sid' => 'wa_'.uniqid('', true)],
                    ));
                }
            } catch (QueryException $queryException)
            {
                if ((string) $queryException->getCode() !== '23000' || empty($messageSid))
                {
                    throw $queryException;
                }

                $conversation = Conversation::query()
                    ->where('message_sid', (string) $messageSid)
                    ->first();

                if ($conversation === null)
                {
                    throw $queryException;
                }

                Log::warning('Recovered duplicate inbound insert race', [
                    'message_sid' => $messageSid,
                    'conversation_id' => $conversation->id,
                ]);
            }

            if (! empty($media))
            {
                $ingestions = [];
                try
                {
                    $ingestions = app(DocumentIngestionService::class)->ingestFromConversationMedia(
                        $conversation,
                        'WhatsApp',
                        ! empty($messageSid) ? (string) $messageSid : null,
                        $resolvedInboundTeamId,
                    );
                    Log::info('WhatsApp document ingestion completed', [
                        'message_sid' => $messageSid,
                        'conversation_id' => $conversation->id,
                        'team_id' => $resolvedInboundTeamId,
                        'ingestions_count' => count($ingestions),
                        'ingestion_ids' => array_values(array_filter(array_map(
                            static fn ($item) => is_object($item) ? (int) ($item->id ?? 0) : 0,
                            $ingestions,
                        ))),
                    ]);
                } catch (\Throwable $e)
                {
                    Log::warning('Document ingestion failed for inbound media', [
                        'conversation_id' => $conversation->id,
                        'message_sid' => $messageSid,
                        'team_id' => $resolvedInboundTeamId,
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($channel === 'whatsapp' && $this->inboundAssistantMayAutoReply($cleanFrom, $cleanTo))
                {
                    try
                    {
                        $this->sendWhatsApp(
                            $cleanFrom,
                            $this->buildDocumentIngestionWhatsAppReply($ingestions),
                        );
                    } catch (\Throwable $e)
                    {
                        Log::warning('Document ingestion acknowledgement send failed', [
                            'conversation_id' => $conversation->id,
                            'to' => $cleanFrom,
                            'team_id' => $resolvedInboundTeamId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'conversation_id' => $conversation->id,
                    'document_ingestion' => true,
                    'auto_ai_skipped' => 'document_ingestion_pending',
                ]);
            }

            Log::info('WhatsApp inbound without media, continuing text pipeline', [
                'message_sid' => $messageSid,
                'conversation_id' => $conversation->id ?? null,
                'team_id' => $resolvedInboundTeamId,
                'body_preview' => mb_substr((string) $body, 0, 120),
            ]);

            if ($channel === 'whatsapp' && $this->team)
            {
                $waProfileName = $request->input('WaProfileName');
                $waProfileName = is_string($waProfileName) ? $waProfileName : null;
                if ($waProfileName === null || $waProfileName === '')
                {
                    $fallbackProfile = $request->input('ProfileName');
                    $waProfileName = is_string($fallbackProfile) && $fallbackProfile !== '' ? $fallbackProfile : null;
                }
                app(UserResolverService::class)->linkPhoneToContactInTeam($this->team->id, $cleanFrom, $waProfileName);

                $teamId = (int) $this->team->id;
                $sheetUser = app(UserResolverService::class)->resolveUserForConversation($cleanFrom, null, $teamId);
                $sheetReply = app(WhatsAppInvoiceSheetImportService::class)->tryHandle((string) $body, $sheetUser, $teamId)
                    ?? app(WhatsAppContactSheetImportService::class)->tryHandle((string) $body, $sheetUser, $teamId)
                    ?? app(WhatsAppTaskSheetImportService::class)->tryHandle((string) $body, $sheetUser, $teamId);
                if ($sheetReply !== null)
                {
                    $this->sendWhatsApp($cleanFrom, $sheetReply);

                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'sheet_import' => true]);
                }
            }

            $chatController = null;
            $shouldHandleRegistration = false;
            if ($channel === 'whatsapp' && $this->team)
            {
                $chatController = app(\App\Http\Controllers\ChatController::class);
                $shouldHandleRegistration = $chatController->shouldHandleWhatsAppRegistration($cleanFrom, $this->team);
            }

            // Send automatic greeting if it's WhatsApp and first message of the day; persist to agent context
            if ($channel == 'whatsapp' && ! $shouldHandleRegistration && $this->inboundAssistantMayAutoReply($cleanFrom, $cleanTo))
            {
                $greetingSent = $this->sendAutoGreeting($cleanFrom);
                if ($greetingSent !== null)
                {
                    $greetingContextTeamId = Team::resolveInboundWebhookTeamId($this->team?->id, $cleanTo);
                    $this->persistWhatsAppExchangeToAgentContext($cleanFrom, $body, $greetingSent, [], $greetingContextTeamId);
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

            // Automatic AI response: team global setting is master; contact opt-out and blacklist also block.
            $shouldProcessAutoAi = $channel === 'whatsapp' && $this->inboundAssistantMayAutoReply($cleanFrom, $cleanTo);

            if ($shouldHandleRegistration && $chatController !== null)
            {
                $registrationTeam = $this->team;
                if ($registrationTeam === null && $resolvedInboundTeamId !== null)
                {
                    $registrationTeam = Team::find($resolvedInboundTeamId);
                }

                $registrationResponse = $chatController->processRegistration(
                    $cleanFrom,
                    (string) $body,
                    $this->getSender() !== $this ? $this->getSender() : null,
                    $registrationTeam,
                );

                if ($registrationResponse !== null)
                {
                    return response()->json([
                        'status' => ($registrationResponse['success'] ?? true) ? 'success' : 'error',
                        'conversation_id' => $conversation->id,
                        'registration' => (bool) ($registrationResponse['success'] ?? true),
                        'registration_handoff' => (bool) ($registrationResponse['handoff'] ?? false),
                    ]);
                }
            }

            // Check if this is part of a registration process
            if ($channel == 'whatsapp')
            {
                if ($shouldProcessAutoAi)
                {
                    // Detect user intent first, then route to the most relevant flow.
                    // This prevents false positives (for example: "agregar cita..." should not go to cart).
                    $detectedIntent = $this->detectWhatsAppIntent((string) $body);

                    if ($detectedIntent === 'cart')
                    {
                        $cartResponse = $this->processCartCommands($cleanFrom, $body);
                        if ($cartResponse)
                        {
                            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'cart_processed' => true]);
                        }
                    }

                    if ($detectedIntent === 'product')
                    {
                        $productResponse = $this->processProductCommands($cleanFrom, $body);
                        if ($productResponse)
                        {
                            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'product_processed' => true]);
                        }
                    }

                    if ($detectedIntent === 'service')
                    {
                        $serviceResponse = $this->processServiceCommands($cleanFrom, $body);
                        if ($serviceResponse)
                        {
                            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'service_processed' => true]);
                        }
                    }

                    if ($detectedIntent === 'demo')
                    {
                        $demoResponse = $this->processDemoCommand($cleanFrom, $body);
                        if ($demoResponse)
                        {
                            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'demo_processed' => true]);
                        }
                    }

                    if ($detectedIntent === 'qr')
                    {
                        $qrResponse = $this->processQrCommand($cleanFrom, $body);
                        if ($qrResponse)
                        {
                            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'qr_processed' => true]);
                        }
                    }
                }
            }
            if ($shouldProcessAutoAi)
            {
                try
                {
                    if (trim((string) $body) === '')
                    {
                        // Empty inbound messages (common for some Baileys event types) should not trigger AI.
                        return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'auto_ai_skipped' => 'empty_body']);
                    }

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
                    $assistantTeamId = Team::resolveInboundWebhookTeamId($this->team?->id, $cleanTo);
                    $withTools = $assistantTeamId !== null;
                    $contextUser = app(UserResolverService::class)->resolveUserForConversation(
                        $cleanFrom,
                        null,
                        $assistantTeamId,
                    );
                    $contextContactId = null;
                    if ($assistantTeamId !== null)
                    {
                        if ($contextUser !== null)
                        {
                            $contextContactId = Contact::withoutGlobalScopes()
                                ->where('user_id', $contextUser->id)
                                ->where('team_id', $assistantTeamId)
                                ->value('id');
                        }
                        if ($contextContactId === null)
                        {
                            $contextContactId = app(UserResolverService::class)
                                ->findContactInTeamByPhone((int) $assistantTeamId, $cleanFrom)
                                ?->id;
                        }
                    }

                    if ($contextUser !== null && $assistantTeamId !== null && trim((string) $body) !== '')
                    {
                        $waSlash = app(AdminProactiveOutreachSlashDispatcher::class)->tryWhatsAppInbound(
                            (string) $body,
                            $contextUser,
                            (int) $assistantTeamId,
                        );
                        if ($waSlash !== null)
                        {
                            $this->sendWhatsApp($cleanFrom, $waSlash['whatsapp_reply']);

                            return response()->json([
                                'status' => 'success',
                                'conversation_id' => $conversation->id,
                                'auto_ai' => 'admin_proactive_outreach_slash',
                            ]);
                        }

                        $waInsightSlash = app(PerformanceInsightSlashDispatcher::class)->tryWhatsAppInbound(
                            (string) $body,
                            $contextUser,
                            (int) $assistantTeamId,
                        );
                        if ($waInsightSlash !== null)
                        {
                            $this->sendWhatsApp($cleanFrom, $waInsightSlash['whatsapp_reply']);

                            return response()->json([
                                'status' => 'success',
                                'conversation_id' => $conversation->id,
                                'auto_ai' => 'performance_insight_slash',
                            ]);
                        }
                    }

                    if ($assistantTeamId !== null && trim((string) $body) !== '')
                    {
                        $assistantTeam = Team::withoutGlobalScopes()->find((int) $assistantTeamId);
                        if ($assistantTeam !== null)
                        {
                            $onboardingReply = app(SystemOnboardingWhatsAppService::class)->tryHandleInbound(
                                $assistantTeam,
                                $cleanFrom,
                                (string) $body,
                            );
                            if ($onboardingReply !== null)
                            {
                                $mediaPath = (string) ($onboardingReply['media_path'] ?? '');
                                if ($mediaPath !== '')
                                {
                                    try
                                    {
                                        $this->sendMedia($cleanFrom, $mediaPath, null);
                                    } catch (\Throwable $e)
                                    {
                                        Log::warning('System onboarding inbound media send failed', [
                                            'team_id' => $assistantTeamId,
                                            'from' => $cleanFrom,
                                            'path' => $mediaPath,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }

                                $replyText = trim((string) ($onboardingReply['message'] ?? ''));
                                if ($replyText !== '')
                                {
                                    $this->sendWhatsApp($cleanFrom, $replyText);
                                }

                                return response()->json([
                                    'status' => 'success',
                                    'conversation_id' => $conversation->id,
                                    'auto_ai' => 'system_onboarding_interactive',
                                ]);
                            }
                        }
                    }

                    $forcedFlowRoutingKey = $this->resolveForcedFlowRoutingKeyForWhatsApp($history, (string) $body);
                    $flowAppendix = null;
                    $flowSession = null;
                    if ($assistantTeamId !== null)
                    {
                        $automationRunner = app(\App\Services\AssistantAutomationRunner::class);
                        $automationSlug = $automationRunner->resolveSlugFromMessage(
                            (string) $body,
                            (int) $assistantTeamId,
                            \App\Models\Automation::CHANNEL_WHATSAPP,
                        );
                        $flowContext = $automationRunner->resolveFlowContext(
                            (int) $assistantTeamId,
                            \App\Models\Automation::CHANNEL_WHATSAPP,
                            (string) $body,
                            'wa:'.(string) $cleanFrom,
                            $automationSlug,
                            null,
                            $forcedFlowRoutingKey,
                        );
                        if (! empty($flowContext['completed']))
                        {
                            // Fall through with a short completion style via forced null and appendix
                            $forcedFlowRoutingKey = null;
                            $flowAppendix = __('El usuario completó el embudo conversacional. Agradecé brevemente y ofrecé ayuda adicional.');
                        } else
                        {
                            $forcedFlowRoutingKey = $flowContext['prompt_key'] ?? $forcedFlowRoutingKey;
                            $flowAppendix = $flowContext['appendix'] ?? null;
                            $flowSession = $flowContext['session'] ?? null;
                        }
                    }
                    $replyResponse = $replyService->getReply(
                        $body,
                        $history,
                        $assistantTeamId,
                        $withTools,
                        $contextUser?->id,
                        $cleanFrom,
                        $forcedFlowRoutingKey,
                        $contextContactId !== null ? (int) $contextContactId : null,
                        false,
                        \App\Services\Assistant\AssistantActorContextService::CHANNEL_WHATSAPP,
                        false,
                        $flowAppendix,
                    );

                    if ($flowSession !== null)
                    {
                        app(\App\Services\AssistantAutomationRunner::class)->markFlowAwaitingReply($flowSession);
                    }

                    $toolResults = is_array($replyResponse['tool_results'] ?? null) ? $replyResponse['tool_results'] : [];

                    $agentHistory = ($contextUser !== null && $assistantTeamId !== null)
                        ? app(AgentConversationContextService::class)->getHistoryForPrompt(
                            $contextUser->id,
                            AgentConversationContextService::DEFAULT_HISTORY_LIMIT,
                            (int) $assistantTeamId,
                        )
                        : [];
                    $statusHistory = $agentHistory !== [] ? $agentHistory : $this->conversationRowsToPromptHistory($history);
                    $serverTaskApply = ($contextUser !== null && $assistantTeamId !== null)
                        ? app(\App\Services\Assistant\AssistantInboundTaskStatusService::class)->tryApplyFromUserMessage(
                            $contextUser,
                            (int) $assistantTeamId,
                            (string) $body,
                            $statusHistory,
                            $toolResults,
                        )
                        : null;

                    if ($serverTaskApply !== null)
                    {
                        $toolResults[] = $serverTaskApply['tool_result'];
                        $replyResponse['tool_results'] = $toolResults;
                    }

                    $serverContactApply = ($contextUser !== null && $assistantTeamId !== null)
                        ? app(\App\Services\Assistant\AssistantInboundContactCreationService::class)->tryApplyFromUserMessage(
                            $contextUser,
                            (int) $assistantTeamId,
                            (string) $body,
                            $toolResults,
                        )
                        : null;

                    if ($serverContactApply !== null)
                    {
                        $toolResults[] = $serverContactApply['tool_result'];
                        $replyResponse['tool_results'] = $toolResults;
                    }

                    if ($replyResponse['success'] ?? false)
                    {
                        $aiMessage = $replyResponse['text'] ?? '';
                        if ($serverTaskApply !== null)
                        {
                            $task = Task::withoutGlobalScopes()->find($serverTaskApply['update']['task_id']);
                            $status = TaskStatus::query()->find($serverTaskApply['update']['status_id']);
                            $title = $task?->title ?? 'Tarea';
                            $label = $status?->translated_name ?? $serverTaskApply['update']['status_name'];
                            $aiMessage = '✅ Listo. La tarea "'.$title.'" quedó en '.$label.'.';
                        } elseif ($serverContactApply !== null)
                        {
                            $aiMessage = $serverContactApply['whatsapp_reply'];
                        } else
                        {
                            $aiMessage = app(\App\Services\Assistant\AssistantInboundContactCreationService::class)
                                ->applyContactOnlyReplyIfApplicable((string) $body, $toolResults, (string) $aiMessage);
                        }

                        if (trim((string) $aiMessage) !== '')
                        {
                            $this->sendWhatsApp($cleanFrom, $aiMessage);
                            $this->persistWhatsAppExchangeToAgentContext($cleanFrom, $body, $aiMessage, $replyResponse, $assistantTeamId);

                            Log::info("Auto AI response sent to {$cleanFrom}: ".\Illuminate\Support\Str::limit($aiMessage, 100));
                            $this->maybeSendCollectionPaymentLinksFollowUp(
                                $cleanFrom,
                                $assistantTeamId,
                                $contextContactId !== null ? (int) $contextContactId : null,
                                $forcedFlowRoutingKey,
                                (string) $body,
                                (string) $aiMessage,
                            );
                        } else
                        {
                            $this->sendWhatsApp($cleanFrom, 'Recibi tu mensaje, pero no pude generar respuesta en este intento. Enviamelo de nuevo por favor.');
                            Log::warning('Auto AI response was empty', ['from' => $cleanFrom, 'team_id' => $assistantTeamId]);
                        }
                    } else
                    {
                        Log::warning('Failed to get AI response: '.($replyResponse['message'] ?? 'Unknown error'));
                        $this->sendWhatsApp($cleanFrom, 'Recibi tu mensaje, pero tuve un problema temporal para responder. Enviamelo de nuevo por favor.');
                    }
                } catch (\Throwable $e)
                {
                    Log::error('Error in auto AI response: '.$e->getMessage(), [
                        'exception' => $e,
                    ]);
                    try
                    {
                        $this->sendWhatsApp($cleanFrom, 'Recibi tu mensaje, pero tuve un problema temporal para responder. Enviamelo de nuevo por favor.');
                    } catch (\Throwable $sendError)
                    {
                        Log::error('Fallback WhatsApp send after AI error failed: '.$sendError->getMessage(), [
                            'from' => $cleanFrom,
                        ]);
                    }
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

    private function inboundAssistantMayAutoReply(string $fromPhone, ?string $toPhone = null): bool
    {
        if ($this->team === null)
        {
            return false;
        }

        $assistantTeamId = Team::resolveInboundWebhookTeamId($this->team->id, $toPhone);
        $inboundUser = app(UserResolverService::class)->resolveUserForConversation(
            $fromPhone,
            null,
            $assistantTeamId,
        );

        return app(TeamInboundAssistantPolicy::class)->allowsWhatsAppAutoReply(
            $this->team,
            $inboundUser,
            $assistantTeamId,
            $fromPhone,
        );
    }

    /**
     * @param  array<int, mixed>  $ingestions
     */
    private function buildDocumentIngestionWhatsAppReply(array $ingestions): string
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
            $createdRecordUrl = $this->resolveCreatedRecordUrl($ingestion);
            $createdInvoiceNumber = $this->resolveCreatedInvoiceNumber($ingestion);
            if ($createdInvoiceNumber !== null)
            {
                $lines[] = '   - Factura creada: '.$createdInvoiceNumber;
                if ($createdRecordUrl !== null)
                {
                    $lines[] = '   - Link: '.$createdRecordUrl;
                }
            } elseif ($createdRecordUrl !== null)
            {
                $lines[] = '   - Registro creado: '.$createdRecordUrl;
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

    private function resolveCreatedRecordUrl(object $ingestion): ?string
    {
        $entityType = (string) ($ingestion->entity_type ?? '');
        $entityId = (int) ($ingestion->entity_id ?? 0);
        if ($entityType === '' || $entityId <= 0)
        {
            return null;
        }

        if ($entityType === Contact::class)
        {
            return route('contact.show', $entityId);
        }

        if ($entityType === \App\Models\Invoice::class)
        {
            return route('invoice.show', $entityId);
        }

        if ($entityType === \App\Models\Payment::class)
        {
            return route('payments.show', $entityId);
        }

        return null;
    }

    private function resolveCreatedInvoiceNumber(object $ingestion): ?string
    {
        $entityType = (string) ($ingestion->entity_type ?? '');
        $entityId = (int) ($ingestion->entity_id ?? 0);
        if ($entityType !== Invoice::class || $entityId <= 0)
        {
            return null;
        }

        $invoiceNumber = Invoice::withoutGlobalScopes()
            ->whereKey($entityId)
            ->value('number');

        if (! is_string($invoiceNumber) || trim($invoiceNumber) === '')
        {
            return null;
        }

        return trim($invoiceNumber);
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
            $statusCallbackUrl = $this->team ? $this->team->getTwilioStatusCallbackUrl() : url(route('whatsapp.status'));

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
     * Process service-related commands from WhatsApp messages
     */
    public function processServiceCommands($phoneNumber, $message)
    {
        try
        {
            // Clean and normalize the message
            $normalizedMessage = strtolower(trim($message));

            // Skip if this is a cart command (comprar, contratar, etc.)
            if (preg_match('/^(comprar|contratar|compra|contrata|carrito|checkout|finalizar|pagar|cerrar|vaciar|quitar|eliminar|sacar|restar|borrar|agregar|añadir)/i', $normalizedMessage))
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
            // Clean and normalize the message (ASCII so "catálogo" matches "catalogo")
            $normalizedMessage = strtolower(trim(\Illuminate\Support\Str::ascii($message)));

            // Cart-remove phrases (handled in processCartCommands first); never send catalog for these.
            if (preg_match('/^(quitar|eliminar|sacar|restar|borrar)\s+/iu', $normalizedMessage) === 1)
            {
                return null;
            }

            // Add-to-cart phrases (agregar/añadir); "carrito" in "al carrito" must not trigger catalog alone.
            if (preg_match('/^(agregar|añadir)\s+/iu', $normalizedMessage) === 1)
            {
                return null;
            }

            // Only trigger catalog flow on explicit commerce intents.
            // Avoid generic "servicio/soporte/app" matches that can hijack other flows (e.g. billing follow-ups).
            // Do NOT use "precio"/"precios" alone: phrases like "¿es precio final?", "¿incluye IVA?" match \bprecio\b
            // and would dump the full catalog instead of letting the assistant answer.
            $productKeywords = [
                'producto',
                'productos',
                'catalogo',
                'comprar',
                'carrito',
                'checkout',
                'pedido',
                'pedidos',
            ];

            $containsProductKeyword = false;
            foreach ($productKeywords as $keyword)
            {
                if (preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $normalizedMessage) === 1)
                {
                    $containsProductKeyword = true;
                    break;
                }
            }

            $isProductCommand = $containsProductKeyword;

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
            $teamId = $this->resolveCartTeamId($phoneNumber);

            // Get active products that are WhatsApp enabled for the specific team
            $products = \App\Models\Product::withoutGlobalScope('team')
                ->where('team_id', $teamId)
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
                    $codeLine = $product->code ? "  🏷️ Código: *{$product->code}*\n" : '';
                    $message .= "• *{$product->name}*\n";
                    $message .= $codeLine;
                    $message .= "  💰 {$currency}".number_format($product->currentSellingPrice(), 2)."\n";
                    $message .= '  📝 '.\Illuminate\Support\Str::limit(strip_tags((string) $product->description), 80)."\n\n";
                }
            }

            $message .= "🛒 *Cómo comprar por aquí:*\n";
            $message .= "• Escribí *carrito* para ver todos tus productos\n";
            $message .= "• *Comprar* más el nombre o código del producto, o *agregar* la cantidad y el producto (ej. agregar 2 yerbas)\n";
            $message .= "• *Quitar* cantidad y producto, o *quitar todo* el producto — sacar del carrito\n";
            $message .= "• *Finalizar* — cerrar el pedido (luego te pediré *SÍ*)\n";
            $message .= "• También podés preguntarme por un producto y te guío paso a paso.\n\n";
            $message .= '📞 Soporte: https://revisionalpha.com/contactenos';

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

            NewUserWelcomeEmailNotifier::queue($user, Team::find(1));

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
            $bodyText = $caption ?? ($isQrType ? '🔄 ¡Tu código QR está listo! Escanéalo para acceder a revision alpha.' : '🎤 Mensaje de voz');
            $bodyText = WhatsAppOutboundText::sanitize($bodyText);

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
                'body' => '🔄 ¡Tu código QR está listo! Escanéalo para acceder a revision alpha.',
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
            $statusCallbackUrl = url(route('whatsapp.status'));

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

            $normalizedMessage = $this->normalizeWhatsAppCommandText($message);
            $teamId = $this->resolveCartTeamId($phoneNumber);

            $cartSessionKey = WhatsAppCartSessionKey::fromPhone($phoneNumber);
            if ($cartSessionKey === '')
            {
                return null;
            }

            // Same key as assistant tool add_to_whatsapp_cart (Spanish 9 vs 34+9 digits).
            Cart::session($cartSessionKey);

            // DEBUG: Log cart session and current contents
            $currentCartItems = Cart::getContent();
            Log::info('Cart session set', [
                'phone_number' => $phoneNumber,
                'cart_session_key' => $cartSessionKey,
                'cart_items_count' => $currentCartItems->count(),
                'cart_total' => Cart::getTotal(),
                'storage_key' => $cartSessionKey.'_cart_items',
            ]);

            $affirmativeCheckout = ['si', 'sí', 'yes', 'confirmar', 'aceptar', 'proceder'];
            $negativeCheckout = ['no', 'nah', 'seguir comprando', 'continuar', 'agregar mas', 'cancelar'];

            // Only treat SÍ/NO as checkout answers after we sent "¿Quieres confirmar tu compra?"
            if (in_array($normalizedMessage, $affirmativeCheckout, true))
            {
                if ($this->hasCheckoutPending($phoneNumber))
                {
                    $this->forgetCheckoutPending($phoneNumber);

                    return $this->confirmCheckout($phoneNumber, $teamId);
                }

                return null;
            }

            if (in_array($normalizedMessage, $negativeCheckout, true))
            {
                if ($this->hasCheckoutPending($phoneNumber))
                {
                    $this->forgetCheckoutPending($phoneNumber);

                    return $this->continueShoppingFromCheckout($phoneNumber);
                }

                return null;
            }

            // Remove from cart: quitar todo X | quitar N X | quitar X
            if (preg_match('/^(quitar|eliminar|sacar|borrar)\s+(todo|todos)\s+(.+)$/iu', $normalizedMessage, $removeAllMatch))
            {
                $needle = $this->sanitizeRemoveProductNeedle($removeAllMatch[3]);
                if ($needle !== '')
                {
                    return $this->removeFromCart($phoneNumber, $needle, $teamId, null);
                }
            }

            if (preg_match('/^(quitar|eliminar|sacar|restar|borrar)\s+(?:(\d+)\s+)?(.+)$/iu', $normalizedMessage, $removeMatch))
            {
                $rest = trim($removeMatch[3]);
                if (! preg_match('/^(todo|todos)\s+/iu', $rest))
                {
                    $qty = isset($removeMatch[2]) && $removeMatch[2] !== '' ? max(1, (int) $removeMatch[2]) : 1;
                    $needle = $this->sanitizeRemoveProductNeedle($rest);
                    if ($needle !== '')
                    {
                        return $this->removeFromCart($phoneNumber, $needle, $teamId, $qty);
                    }
                }
            }

            // agregar|añadir + cantidad + nombre… (ej. "agregar 3 vestidos iguales al carrito")
            if (preg_match('/^(agregar|añadir)\s+(\d+)\s+(.+)$/iu', $normalizedMessage, $agregarQtyMatch))
            {
                $name = $this->sanitizeAgregarProductName($agregarQtyMatch[3]);
                if ($name !== '')
                {
                    $qty = max(1, min(500, (int) $agregarQtyMatch[2]));

                    return $this->addToCart($phoneNumber, $name, $teamId, $qty);
                }
            }

            // agregar nombre al carrito (cantidad 1)
            if (preg_match('/^(agregar|añadir)\s+(.+?)\s+al\s+carrito\s*$/iu', $normalizedMessage, $agregarAlMatch))
            {
                $name = $this->sanitizeAgregarProductName($agregarAlMatch[2]);
                if ($name !== '')
                {
                    return $this->addToCart($phoneNumber, $name, $teamId, 1);
                }
            }

            // agregar nombre (cantidad 1, sin "al carrito")
            if (preg_match('/^(agregar|añadir)\s+(.+)$/iu', $normalizedMessage, $agregarMatch))
            {
                $rest = trim($agregarMatch[2]);
                if ($rest !== '' && ! preg_match('/^\d+\s/', $rest))
                {
                    return $this->addToCart($phoneNumber, $rest, $teamId, 1);
                }
            }

            // Check for add to cart commands (comprar, contratar)
            if (preg_match('/^(comprar|contratar|compra|contrata)\s+(.+)/i', $normalizedMessage, $matches))
            {
                $productName = trim($matches[2]);

                return $this->addToCart($phoneNumber, $productName, $teamId, 1);
            }

            // Check for cart view commands
            if (in_array($normalizedMessage, ['carrito', 'ver carrito', 'mi carrito', 'cart']))
            {
                return $this->viewCart($phoneNumber, $teamId);
            }

            // Check for clear cart commands
            if (in_array($normalizedMessage, ['vaciar carrito', 'limpiar carrito', 'borrar carrito', 'clear cart'], true))
            {
                $this->forgetCheckoutPending($phoneNumber);

                return $this->clearCart($phoneNumber);
            }

            // Check for checkout commands ("checkout" English kept for compatibility; user-facing copy uses "finalizar")
            $checkoutTriggers = [
                'checkout',
                'finalizar',
                'finalizar compra',
                'pagar',
                'comprar todo',
                'cerrar pedido',
                'cerrar el pedido',
                'terminar compra',
                'confirmar pedido',
            ];
            if (in_array($normalizedMessage, $checkoutTriggers, true))
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

    private function sanitizeRemoveProductNeedle(string $raw): string
    {
        $t = trim($raw);
        $t = preg_replace('/\s+del\s+carrito\s*$/iu', '', $t) ?? $t;
        $t = preg_replace('/^\s*(?:el|la|los|las)\s+/iu', '', $t) ?? $t;

        return trim($t);
    }

    private function sanitizeAgregarProductName(string $raw): string
    {
        $t = trim($raw);
        $t = preg_replace('/\s+iguales?\s+al\s+carrito\s*$/iu', '', $t) ?? $t;
        $t = preg_replace('/\s+al\s+carrito\s*$/iu', '', $t) ?? $t;
        $t = preg_replace('/\s+iguales?\s*$/iu', '', $t) ?? $t;
        $t = preg_replace('/^\s*(?:el|la|los|las)\s+/iu', '', $t) ?? $t;

        return trim($t);
    }

    /**
     * Detect the most likely inbound intent to route the message into a specific flow.
     *
     * @return 'assistant'|'cart'|'product'|'service'|'demo'|'qr'
     */
    private function detectWhatsAppIntent(string $message): string
    {
        $normalized = mb_strtolower(trim(\Illuminate\Support\Str::ascii($message)));

        if ($normalized === '')
        {
            return 'assistant';
        }

        $demoPattern = '/^(demo|crear demo|cuenta demo)\b/u';
        if (preg_match($demoPattern, $normalized) === 1)
        {
            return 'demo';
        }

        $qrPattern = '/\b(qr|codigo qr|codigo de qr|codigo)\b/u';
        if (preg_match($qrPattern, $normalized) === 1)
        {
            return 'qr';
        }

        $servicePattern = '/\b(servicio|servicios|soporte|asistencia|tecnico|tecnica)\b/u';
        if (preg_match($servicePattern, $normalized) === 1)
        {
            return 'service';
        }

        $cartExact = [
            'carrito',
            'ver carrito',
            'mi carrito',
            'cart',
            'vaciar carrito',
            'limpiar carrito',
            'borrar carrito',
            'clear cart',
            'checkout',
            'finalizar',
            'finalizar compra',
            'pagar',
            'comprar todo',
            'cerrar pedido',
            'cerrar el pedido',
            'terminar compra',
            'confirmar pedido',
            'si',
            'sí',
            'yes',
            'no',
            'nah',
            'seguir comprando',
            'continuar',
            'agregar mas',
            'cancelar',
        ];
        if (in_array($normalized, $cartExact, true))
        {
            return 'cart';
        }

        if (preg_match('/^(quitar|eliminar|sacar|restar|borrar)\s+/u', $normalized) === 1)
        {
            return 'cart';
        }

        if (preg_match('/^(comprar|contratar|compra|contrata)\s+.+/u', $normalized) === 1)
        {
            return 'cart';
        }

        if (preg_match('/^(agregar|anadir)\s+\d+\s+.+/u', $normalized) === 1)
        {
            return 'cart';
        }

        if (preg_match('/^(agregar|anadir)\s+.+\s+al\s+carrito\s*$/u', $normalized) === 1)
        {
            return 'cart';
        }

        $productPattern = '/\b(producto|productos|catalogo|pedido|pedidos)\b/u';
        if (preg_match($productPattern, $normalized) === 1)
        {
            return 'product';
        }

        return 'assistant';
    }

    /**
     * @return list<string>
     */
    private function productSearchNeedleVariants(string $needle): array
    {
        $needle = trim($needle);
        if ($needle === '')
        {
            return [];
        }

        $variants = [$needle];
        if (mb_strlen($needle) >= 5 && preg_match('/[^s]s$/iu', $needle))
        {
            $variants[] = mb_substr($needle, 0, -1);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function productForCartNeedle(int $teamId, string $needle): ?\App\Models\Product
    {
        $variants = $this->productSearchNeedleVariants($needle);
        foreach ($variants as $v)
        {
            $v = trim($v);
            if ($v === '')
            {
                continue;
            }

            $product = \App\Models\Product::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('status', true)
                ->where('whatsapp_enabled', true)
                ->where(function ($q) use ($v)
                {
                    $q->where('name', 'LIKE', '%'.$v.'%')
                        ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($v)]);
                })
                ->first();
            if ($product)
            {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function findCartItemsMatchingNeedle(int $teamId, string $needle): \Illuminate\Support\Collection
    {
        $needle = trim($needle);
        if ($needle === '')
        {
            return collect();
        }

        $product = $this->productForCartNeedle($teamId, $needle);

        if ($product)
        {
            $item = Cart::getContent()->first(function ($row) use ($product)
            {
                return (int) $row->id === (int) $product->id;
            });
            if ($item)
            {
                return collect([$item]);
            }
        }

        $matches = collect();
        foreach (Cart::getContent() as $item)
        {
            if (mb_stripos((string) $item->name, $needle) !== false)
            {
                $matches->push($item);
            }
        }

        return $matches->unique('id')->values();
    }

    /**
     * @param  ?int  $removeQuantity  null = remove the line entirely; positive = subtract units
     */
    private function removeFromCart(string $phoneNumber, string $productNeedle, int $teamId, ?int $removeQuantity): array
    {
        try
        {
            $items = $this->findCartItemsMatchingNeedle($teamId, $productNeedle);

            if ($items->isEmpty())
            {
                $response = "❌ **No encontré *{$productNeedle}* en tu carrito.**\n\n";
                $response .= "📋 Escribí *carrito* para ver lo que tenés o *productos* para el catálogo.\n";
                $response .= '💡 *Quitar* cantidad y nombre, o *quitar todo* el nombre — sacás unidades o el ítem entero';

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => 'Cart item not found'];
            }

            if ($items->count() > 1)
            {
                $names = $items->pluck('name')->unique()->implode(', ');
                $response = "🔎 **Varios productos coinciden:** {$names}\n\n";
                $response .= 'Escribí el nombre o código más completo, o *carrito* para ver el detalle.';

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => 'Ambiguous cart match'];
            }

            $item = $items->first();
            $itemId = $item->id;
            $currentQty = (int) $item->quantity;
            $removeAll = $removeQuantity === null;
            $toRemove = $removeAll ? $currentQty : min($removeQuantity, $currentQty);

            if ($toRemove <= 0)
            {
                $response = '❌ Cantidad inválida. Probá de nuevo, por ejemplo *quitar 2 yerba* o *quitar todo pan*.';

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => 'Invalid quantity'];
            }

            $newQty = $currentQty - $toRemove;

            if ($newQty <= 0)
            {
                Cart::remove($itemId);
            } else
            {
                Cart::update($itemId, [
                    'quantity' => [
                        'relative' => false,
                        'value' => $newQty,
                    ],
                ]);
            }

            $this->forgetCheckoutPending($phoneNumber);

            $currency = '$';
            $product = \App\Models\Product::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', $itemId)->first();
            if ($product && $product->currency)
            {
                $currency = $product->currency->symbol;
            }

            $removedPhrase = $removeAll || $newQty <= 0
                ? 'Sacamos *'.$item->name.'* del carrito.'
                : "Quitamos *{$toRemove}* de *{$item->name}* (quedan *{$newQty}*).";

            $response = "✅ {$removedPhrase}\n\n";
            $response .= '🛒 **Total del carrito**: '.$currency.number_format(Cart::getTotal(), 2)."\n";
            $response .= '📦 **Ítems**: '.Cart::getTotalQuantity()."\n\n";
            $response .= "**Opciones:**\n";
            $response .= "• Escribí *carrito* para ver todos tus productos\n";
            $response .= "• *Comprar* más el producto, o *agregar* cantidad y producto para sumar más\n";
            $response .= "• *Quitar* cantidad y producto o *quitar todo* el producto — sacar del carrito\n";
            $response .= '• *Finalizar* — cerrar el pedido (luego te pediré *SÍ*)';

            $this->sendWhatsApp($phoneNumber, $response);

            Log::info('Cart item removed or reduced', [
                'phone' => $phoneNumber,
                'item_id' => $itemId,
                'removed' => $toRemove,
                'new_qty' => max(0, $newQty),
            ]);

            return ['success' => true, 'message' => 'Cart updated'];
        } catch (\Exception $e)
        {
            Log::error('Error removing from cart: '.$e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ No pudimos actualizar el carrito. Probá de nuevo.');

            return ['success' => false, 'message' => 'Error removing from cart'];
        }
    }

    /**
     * Add product to cart
     */
    private function addToCart($phoneNumber, $productName, $teamId, int $addQuantity = 1)
    {
        try
        {
            $needle = trim($productName);
            $addQuantity = max(1, min(500, $addQuantity));
            $product = $this->productForCartNeedle($teamId, $needle);

            if (! $product)
            {
                $response = "❌ **Producto no encontrado**: '{$productName}'\n\n";
                $response .= "📋 Escribe 'productos' para ver nuestro catálogo completo\n";
                $response .= '💡 **Tip**: *comprar* más el nombre, *agregar* cantidad y nombre (ej. agregar 3 panes), o *agregar yerba al carrito*';

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => 'Product not found'];
            }

            $cartItems = Cart::getContent();
            $existingItem = $cartItems->where('id', $product->id)->first();

            if ($existingItem)
            {
                Cart::update($product->id, [
                    'quantity' => [
                        'relative' => false,
                        'value' => $existingItem->quantity + $addQuantity,
                    ],
                ]);
                $quantity = $existingItem->quantity + $addQuantity;
            } else
            {
                Cart::add([
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->currentSellingPrice(),
                    'quantity' => $addQuantity,
                    'attributes' => [
                        'team_id' => $teamId,
                        'store_id' => $product->store_id,
                        'currency_id' => $product->currency_id,
                        'description' => $product->description,
                        'category_name' => $product->category->name ?? '',
                    ],
                ]);
                $quantity = $addQuantity;
            }

            $currency = $product->currency ? $product->currency->symbol : '$';

            $addedPhrase = $addQuantity > 1
                ? "Se agregaron *{$addQuantity}* unidades de **{$product->name}**"
                : "**{$product->name}** agregado al carrito";

            $response = "✅ {$addedPhrase}\n\n";
            $response .= "💰 **Precio unitario**: {$currency}".number_format($product->currentSellingPrice(), 2)."\n";
            $response .= "📦 **Cantidad en carrito**: {$quantity}\n";
            $response .= '🏷️ **Categoría**: '.($product->category->name ?? 'General')."\n\n";
            $response .= "🛒 **Total del carrito**: {$currency}".number_format(Cart::getTotal(), 2)."\n\n";
            $response .= "**Opciones:**\n";
            $response .= "• Escribí *carrito* para ver todos tus productos\n";
            $response .= "• *Comprar* más el producto, o *agregar* la cantidad y el producto para sumar más\n";
            $response .= "• *Quitar* cantidad y producto o *quitar todo* el producto — sacar del carrito\n";
            $response .= '• *Finalizar* — cerrar el pedido (luego te pediré *SÍ*)';

            $this->sendWhatsApp($phoneNumber, $response);

            Log::info('Product added to cart', [
                'phone' => $phoneNumber,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'added' => $addQuantity,
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
                $response .= "📋 *comprar* o *agregar* cantidad y nombre para sumar | *productos* catálogo | preguntame por un producto.\n";

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

            $response .= "**Siguiente paso:**\n";
            $response .= "• *finalizar* — total y confirmación con *SÍ*\n";
            $response .= "• *Comprar* más el producto o *agregar* cantidad y producto — sumar ítems\n";
            $response .= "• *Quitar* cantidad y producto o *quitar todo* el nombre — sacar unidades o el ítem\n";
            $response .= '• *vaciar carrito* — empezar de cero';

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
            $cartSessionKey = WhatsAppCartSessionKey::fromPhone($phoneNumber);
            if ($cartSessionKey !== '')
            {
                Cart::session($cartSessionKey);
            }
            Cart::clear();

            $response = "🗑️ **Carrito vaciado exitosamente**\n\n";
            $response .= "📋 Escribe 'productos' para ver nuestro catálogo\n";
            $response .= '💡 Usá *comprar* más el nombre del producto para sumar ítems';

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
                $this->forgetCheckoutPending($phoneNumber);
                $response = "❌ **Tu carrito está vacío**\n\n";
                $response .= '📋 *comprar* más un producto | *productos* para el catálogo';

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

            $checkoutStore = $this->resolveStoreForWhatsAppCart($teamId, $cartItems);
            $response .= $this->formatStoreCheckoutOptionsForWhatsApp($checkoutStore);

            $response .= "❓ **¿Confirmamos el pedido?**\n\n";
            $response .= "Responde *SÍ* solo ahora para confirmar este pedido, o *NO* para seguir agregando productos.\n";
            $response .= '(Si no ves este mensaje como último paso, escribe *carrito* o *finalizar* de nuevo.)';

            $this->sendWhatsApp($phoneNumber, $response);
            $this->rememberCheckoutPending($phoneNumber);

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
            $this->forgetCheckoutPending($phoneNumber);
            $this->sendWhatsApp($phoneNumber, '❌ Error al finalizar la compra. Contactá soporte.');

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
                $this->forgetCheckoutPending($phoneNumber);
                $response = "❌ **Tu carrito está vacío**\n\n";
                $response .= '📋 Escribí *productos* o preguntá por un producto; para sumar: *comprar* más el nombre o código del producto.';

                $this->sendWhatsApp($phoneNumber, $response);

                return ['success' => false, 'message' => $response];
            }

            $total = Cart::getTotal();
            $cleanDigits = preg_replace('/[^0-9]/', '', (string) $phoneNumber);

            $checkoutStore = $this->resolveStoreForWhatsAppCart($teamId, $cartItems);
            $checkoutSnapshot = $this->buildCheckoutSnapshotForOrder($checkoutStore);

            try
            {
                $order = app(WhatsAppCheckoutOrderService::class)->createFromWhatsAppCart(
                    $teamId,
                    $cleanDigits,
                    $cartItems,
                    (float) $total,
                    $checkoutStore?->id,
                    $checkoutSnapshot,
                );
            } catch (\Throwable $e)
            {
                Log::error('WhatsApp checkout order create failed: '.$e->getMessage(), [
                    'exception' => $e,
                    'team_id' => $teamId,
                    'phone' => $cleanDigits,
                ]);
                $this->forgetCheckoutPending($phoneNumber);
                $this->sendWhatsApp($phoneNumber, '❌ No pudimos registrar tu pedido. Probá *finalizar* de nuevo o escribinos.');

                return ['success' => false, 'message' => 'Order create failed'];
            }

            $response = "✅ **¡Compra confirmada!**\n\n";
            $response .= "📋 **Resumen del pedido:**\n";

            foreach ($cartItems as $item)
            {
                $response .= "• {$item->name} x{$item->quantity} - \$".number_format($item->price * $item->quantity, 2)."\n";
            }

            $response .= "\n💰 **TOTAL: \$".number_format($total, 2)."**\n";
            $response .= '📦 **Items**: '.Cart::getTotalQuantity()."\n\n";

            $response .= "🧾 **Tu pedido quedó registrado**\n";
            $response .= '• **Nº de orden:** #'.$order->order_number."\n";
            if ($checkoutStore)
            {
                $response .= '• **Sucursal:** '.$checkoutStore->name."\n";
            }
            $response .= "• Guardá ese número por si necesitás seguimiento por acá.\n\n";

            $response .= "📞 **¿Dudas?**\n";
            $response .= "• Respondé en este chat\n";
            $response .= "• Web: https://revisionalpha.com/contactenos\n\n";

            $response .= '¡Gracias por tu compra! 🎉';

            $this->sendWhatsApp($phoneNumber, $response);

            Log::info('Checkout confirmed and order created', [
                'phone' => $phoneNumber,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'items_count' => $cartItems->count(),
                'total' => $total,
            ]);

            Cart::clear();
            $this->forgetCheckoutPending($phoneNumber);

            return ['success' => true, 'message' => $response];
        } catch (\Exception $e)
        {
            Log::error('Error confirming checkout: '.$e->getMessage());
            $this->forgetCheckoutPending($phoneNumber);
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
            $response = "🛍️ **Seguimos con tu compra**\n\n";
            $response .= "Tu carrito sigue igual. Podés sumar más productos o revisar el total.\n\n";
            $response .= "📋 **Pasos:**\n";
            $response .= "• *Comprar* el nombre o código, o *agregar* cantidad y nombre — agregar al carrito\n";
            $response .= "• *carrito* — ver ítems y subtotales\n";
            $response .= "• *Quitar* cantidad y producto o *quitar todo* el nombre — sacar del carrito\n";
            $response .= "• *finalizar* — pedir confirmación del pedido\n";
            $response .= "• *productos* — catálogo completo\n\n";
            $response .= '💡 Decime qué producto querés y te ayudo a agregarlo.';

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

    /**
     * Branch used for checkout copy and {@see Order::store_id} (single store or main when ambiguous / empty).
     *
     * @param  \Illuminate\Support\Collection|\Darryldecode\Cart\CartCollection  $cartItems
     */
    private function resolveStoreForWhatsAppCart(int $teamId, $cartItems): ?Store
    {
        $storeIds = [];
        foreach ($cartItems as $item)
        {
            $attrs = $this->normalizeCartItemAttributesForCheckout($item->attributes ?? null);
            $sid = isset($attrs['store_id']) && $attrs['store_id'] !== null && $attrs['store_id'] !== ''
                ? (int) $attrs['store_id']
                : null;
            if ($sid <= 0 && (int) $item->id > 0)
            {
                $pid = (int) $item->id;
                $sid = (int) (Product::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('id', $pid)
                    ->value('store_id') ?? 0);
            }
            if ($sid > 0)
            {
                $storeIds[] = $sid;
            }
        }
        $storeIds = array_values(array_unique($storeIds));

        if ($storeIds === [])
        {
            return Store::ensureMainStoreForTeam($teamId);
        }

        if (count($storeIds) === 1)
        {
            $store = Store::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('id', $storeIds[0])
                ->first();

            return $store ?? Store::ensureMainStoreForTeam($teamId);
        }

        return Store::ensureMainStoreForTeam($teamId);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCartItemAttributesForCheckout(mixed $attributes): array
    {
        if ($attributes === null)
        {
            return [];
        }
        if (is_array($attributes))
        {
            return $attributes;
        }
        if (is_object($attributes))
        {
            $decoded = json_decode(json_encode($attributes), true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function formatStoreCheckoutOptionsForWhatsApp(?Store $store): string
    {
        if (! $store)
        {
            return '';
        }
        $block = "\n🏪 **{$store->name}**\n";
        $block .= '**'.__('Medios de pago aceptados').":**\n";
        foreach ($store->enabledCheckoutPaymentMethods() as $key)
        {
            $labels = Store::checkoutPaymentMethodLabels();
            $block .= '• '.($labels[$key] ?? $key)."\n";
        }
        $block .= '**'.__('Formas de entrega').":**\n";
        foreach ($store->enabledCheckoutFulfillmentTypes() as $key)
        {
            $labels = Store::checkoutFulfillmentLabels();
            $block .= '• '.($labels[$key] ?? $key)."\n";
        }
        $block .= "\n";

        return $block;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutSnapshotForOrder(?Store $store): array
    {
        if (! $store)
        {
            return [];
        }

        return [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'payment_methods' => $store->enabledCheckoutPaymentMethods(),
            'payment_method_labels' => array_map(
                static fn (string $k): string => (string) (Store::checkoutPaymentMethodLabels()[$k] ?? $k),
                $store->enabledCheckoutPaymentMethods(),
            ),
            'fulfillment_types' => $store->enabledCheckoutFulfillmentTypes(),
            'fulfillment_labels' => array_map(
                static fn (string $k): string => (string) (Store::checkoutFulfillmentLabels()[$k] ?? $k),
                $store->enabledCheckoutFulfillmentTypes(),
            ),
        ];
    }

    /**
     * Keep collections context on follow-up turns so payment links are preserved.
     *
     * @param  array<int, array<string, mixed>>  $history
     */
    private function resolveForcedFlowRoutingKeyForWhatsApp(array $history, string $incomingBody): ?string
    {
        $incoming = mb_strtolower(trim($incomingBody));
        if ($incoming === '')
        {
            return null;
        }

        $isCollectionsQuestion = (bool) preg_match(
            '/\b(saldo|factura|facturas|deuda|deudor|impaga|impagas|pago|pagos|abonar|abono|link|links|stripe)\b/u',
            $incoming,
        );
        if (! $isCollectionsQuestion)
        {
            return null;
        }

        $recent = array_slice($history, -8);
        foreach ($recent as $item)
        {
            if (($item['direction'] ?? null) !== 'outbound')
            {
                continue;
            }
            $body = mb_strtolower((string) ($item['body'] ?? ''));
            $looksLikeCollectionsMessage = str_contains($body, 'factura')
                || str_contains($body, 'saldo')
                || str_contains($body, 'impag')
                || str_contains($body, 'stripe')
                || str_contains($body, 'link de pago')
                || str_contains($body, 'hosted_invoice_url')
                || (bool) preg_match('/\b\d{4}-\d{4}\b/u', $body);

            if ($looksLikeCollectionsMessage)
            {
                return 'invoices:collections';
            }
        }

        return null;
    }

    private function maybeSendCollectionPaymentLinksFollowUp(
        string $phone,
        ?int $teamId,
        ?int $contactId,
        ?string $forcedFlowRoutingKey,
        string $incomingBody,
        string $assistantMessage,
    ): void {
        if ($teamId === null || $contactId === null || $forcedFlowRoutingKey !== 'invoices:collections')
        {
            return;
        }

        $incoming = mb_strtolower(trim($incomingBody));
        $assistant = mb_strtolower($assistantMessage);

        $asksForDetails = (bool) preg_match('/\b(saldo|factura|facturas|deuda|monto|importe|pago|pagar|link|links|abonar)\b/u', $incoming);
        if (! $asksForDetails)
        {
            return;
        }

        // Avoid duplicate links if model already included them.
        if (str_contains($assistant, 'http://') || str_contains($assistant, 'https://') || str_contains($assistant, 'link de pago'))
        {
            return;
        }

        $linksMessage = app(CollectionAssistantContextService::class)->paymentLinksMessageForContact($contactId, $teamId);
        if (! is_string($linksMessage) || trim($linksMessage) === '')
        {
            return;
        }

        try
        {
            $this->sendWhatsApp($phone, $linksMessage);
            Log::info('Collection follow-up links sent', [
                'phone' => $phone,
                'team_id' => $teamId,
                'contact_id' => $contactId,
            ]);
        } catch (\Throwable $e)
        {
            Log::warning('Collection follow-up links send failed', [
                'phone' => $phone,
                'team_id' => $teamId,
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{direction: string, body: string}>
     */
    private function conversationRowsToPromptHistory(array $rows): array
    {
        $out = [];
        foreach ($rows as $row)
        {
            $direction = ($row['direction'] ?? '') === 'inbound' ? 'inbound' : 'outbound';
            $body = trim((string) ($row['body'] ?? ''));
            if ($body === '')
            {
                continue;
            }
            $out[] = [
                'direction' => $direction,
                'body' => $body,
            ];
        }

        return $out;
    }
}
