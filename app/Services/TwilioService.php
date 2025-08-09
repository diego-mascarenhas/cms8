<?php

namespace App\Services;

use App\Mail\IncomingMessageNotification;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class TwilioService
{
    protected $client;

    protected $smsFromNumber;

    protected $whatsappFromNumber;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->smsFromNumber = config('services.twilio.from');
        $this->whatsappFromNumber = 'whatsapp:'.config('services.twilio.whatsapp_from', '+14155238886');

        $this->client = new Client($sid, $token);
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
        if ($user) {
            return $user;
        }

        // Try without country code if not found
        if (strlen($cleanNumber) > 9) {
            $withoutCountryCode = substr($cleanNumber, -9);
            $user = User::where('phone', $withoutCountryCode)->first();
            if ($user) {
                return $user;
            }
        }

        // If no user found directly, try to find through contact relationship
        $contact = Contact::whereHas('sources', function ($query) use ($cleanNumber) {
            $query->where('source_id', 2) // Phone source
                ->where('value', $cleanNumber);
        })->first();

        if ($contact && $contact->user) {
            return $contact->user;
        }

        // If still no user found, try to get contact name
        if ($contact) {
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
     * Send automatic greeting if it's the first message of the day
     */
    private function sendAutoGreeting($phoneNumber)
    {
        // Check if it's the first message today
        if (! $this->isFirstMessageToday($phoneNumber)) {
            return false;
        }

        // Get user information
        $user = $this->getUserByPhone($phoneNumber);

        if ($user && ! empty($user->name)) {
            $greeting = "¡Hola {$user->name}! 👋";

            try {
                // Send the greeting
                $this->sendWhatsApp($phoneNumber, $greeting);

                Log::info("Auto greeting sent to {$phoneNumber}: {$greeting}");

                return true;
            } catch (\Exception $e) {
                Log::error("Failed to send auto greeting to {$phoneNumber}: ".$e->getMessage());

                return false;
            }
        }

        return false;
    }

    public function sendSms($to, $message)
    {
        try {
            // Get the full URL for the status callback
            $statusCallbackUrl = url(route('twilio.status'));

            $twilioMessage = $this->client->messages->create(
                $to,
                [
                    'from' => $this->smsFromNumber,
                    'body' => $message,
                    'statusCallback' => $statusCallbackUrl,
                ],
            );

            // Save outbound SMS to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'sms',
                'from' => $this->smsFromNumber,
                'to' => $to,
                'body' => $message,
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => auth()->id(),
                'metadata' => [
                    'twilio_response' => [
                        'sid' => $twilioMessage->sid,
                        'status' => $twilioMessage->status,
                        'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                    ],
                ],
            ]);

            return $twilioMessage;
        } catch (\Exception $e) {
            Log::error('Twilio SMS Error: '.$e->getMessage());
            throw $e;
        }
    }

    public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
    {
        try {
            // Format the numbers with whatsapp: prefix for Twilio
            $formattedTo = 'whatsapp:'.$to;

            // Get the full URL for the status callback
            $statusCallbackUrl = url(route('twilio.status'));

            $twilioMessage = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $this->whatsappFromNumber,
                    'body' => $message,
                    'statusCallback' => $statusCallbackUrl,
                ],
            );

            // Clean phone numbers before saving to database
            $cleanFrom = preg_replace('/[^0-9]/', '', $this->whatsappFromNumber);
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
            if ($metadata) {
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
                'metadata' => $messageMetadata,
            ]);

            return $twilioMessage;
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp Error: '.$e->getMessage());
            throw $e;
        }
    }

    public function processIncomingMessage($request)
    {
        try {
            $messageSid = $request->input('MessageSid');
            $from = $request->input('From');
            $to = $request->input('To');
            $body = $request->input('Body');
            $numMedia = (int) $request->input('NumMedia', 0);

            // Clean phone numbers by removing whatsapp: prefix and non-numeric characters
            $cleanFrom = preg_replace('/[^0-9]/', '', $from);
            $cleanTo = preg_replace('/[^0-9]/', '', $to);

            // Determine the channel type
            $channel = 'sms';
            if (strpos($from, 'whatsapp:') !== false || strpos($to, 'whatsapp:') !== false) {
                $channel = 'whatsapp';
            }

            // Log the incoming message
            \Log::info("Incoming {$channel} message from {$cleanFrom}: {$body}");

            // Process media if present
            $media = [];
            if ($numMedia > 0) {
                for ($i = 0; $i < $numMedia; $i++) {
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

            // Send automatic greeting if it's WhatsApp and first message of the day
            if ($channel == 'whatsapp') {
                $this->sendAutoGreeting($cleanFrom);
            }

            // Send email notification for new message
            $notificationEmail = config('services.notifications.email');
            if ($notificationEmail) {
                Mail::to($notificationEmail)->send(new IncomingMessageNotification($conversation));
                \Log::info("Email notification sent to {$notificationEmail} for message {$messageSid}");
            }

            // Check if this is part of a registration process
            if ($channel == 'whatsapp') {
                $chatController = app(\App\Http\Controllers\ChatController::class);
                $registrationResponse = $chatController->processRegistration($cleanFrom, $body);

                // If this was a registration step, we've already handled it
                if ($registrationResponse) {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'registration' => true]);
                }

                // Check if user is trying to report service information
                $serviceResponse = $this->processServiceCommands($cleanFrom, $body);
                if ($serviceResponse) {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'service_processed' => true]);
                }
            }

            // Automatic AI response using Claude
            if (config('services.claude.auto_respond', false) && $channel == 'whatsapp') {
                try {
                    // Get recent chat history for context
                    $history = Conversation::where('channel', 'whatsapp')
                        ->where(function ($query) use ($cleanFrom) {
                            $query->where('from', $cleanFrom)
                                ->orWhere('to', $cleanFrom);
                        })
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get()
                        ->sortBy('created_at')
                        ->values()
                        ->toArray();

                    // Process with Claude
                    $claudeService = app(\App\Services\ClaudeService::class);
                    $claudeResponse = $claudeService->chat($body, $history);

                    // If Claude responded successfully, send the response
                    if ($claudeResponse['success']) {
                        $aiMessage = $claudeResponse['text'];

                        // Send the AI message
                        $this->sendWhatsApp($cleanFrom, $aiMessage);

                        \Log::info("Auto AI response sent to {$cleanFrom}: ".\Illuminate\Support\Str::limit($aiMessage, 100));
                    } else {
                        \Log::warning('Failed to get AI response: '.($claudeResponse['message'] ?? 'Unknown error'));
                    }

                    // Analyze sentiment of the incoming message
                    $this->analyzeSentiment($cleanFrom, $body);

                } catch (\Exception $e) {
                    \Log::error('Error in auto AI response: '.$e->getMessage());
                }
            }

            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id]);
        } catch (\Exception $e) {
            \Log::error('Error processing incoming message: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
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
        try {
            // Format the numbers with whatsapp: prefix
            $formattedTo = 'whatsapp:'.$to;

            // Get the full URL for the status callback
            $statusCallbackUrl = url(route('twilio.status'));

            // Prepare template parameters
            $contentSid = null;
            $contentVariables = null;

            if (! empty($parameters)) {
                $contentVariables = json_encode(['1' => $parameters]);
            }

            // Create message with template
            $twilioMessage = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $this->whatsappFromNumber,
                    'statusCallback' => $statusCallbackUrl,
                    'contentSid' => $templateName,
                    'contentVariables' => $contentVariables,
                ],
            );

            // Save outbound message to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => $this->whatsappFromNumber,
                'to' => $formattedTo,
                'body' => "Template: {$templateName}",
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => auth()->id(),
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
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp Template Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze sentiment of incoming message and create sentiment history if emotion is detected
     */
    private function analyzeSentiment($phoneNumber, $messageBody)
    {
        try {
            // Find user by phone number
            $phoneAsInt = is_numeric($phoneNumber) ? (int) $phoneNumber : null;
            $user = null;

            if ($phoneAsInt) {
                $user = \App\Models\User::where('phone', $phoneAsInt)->first();
            }

            if (!$user) {
                $user = \App\Models\User::where('phone', 'like', '%'.$phoneNumber.'%')->first();
            }

            if (!$user) {
                \Log::info('No user found for sentiment analysis', ['phone' => $phoneNumber]);
                return;
            }

            // Find associated contact
            $contact = \App\Models\Contact::where('user_id', $user->id)->first();
            if (!$contact) {
                \Log::info('No contact found for sentiment analysis', ['user_id' => $user->id]);
                return;
            }

            // Analyze message for emotional indicators
            $sentiment = $this->detectSentiment($messageBody);

            if ($sentiment) {
                // Create sentiment history entry
                \App\Models\ContactSentimentHistory::create([
                    'contact_id' => $contact->id,
                    'sentiment_id' => $sentiment['id'],
                    'notes' => 'Análisis automático de WhatsApp: ' . $sentiment['reason']
                ]);

                \Log::info('Sentiment detected and recorded', [
                    'contact_id' => $contact->id,
                    'sentiment' => $sentiment['name'],
                    'message' => substr($messageBody, 0, 100)
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error in sentiment analysis: ' . $e->getMessage());
        }
    }

    /**
     * Detect sentiment from message content
     * Returns array with sentiment info or null if no strong emotion detected
     */
    private function detectSentiment($message)
    {
        $message = strtolower($message);

        // Very negative indicators (sentiment_id = 1)
        $veryNegativeKeywords = [
            'terrible', 'horrible', 'pésimo', 'malísimo', 'odio', 'detesto',
            'furioso', 'indignado', 'inaceptable', 'vergonzoso', 'estafa',
            'robo', 'ladrones', 'cancelar', 'cancelaré', 'nunca más',
            'demanda', 'denuncia', 'abogado', 'fraude'
        ];

        // Negative indicators (sentiment_id = 2)
        $negativeKeywords = [
            'molesto', 'enfadado', 'problema', 'falla', 'error', 'mal',
            'no funciona', 'deficiente', 'lento', 'caro', 'insatisfecho',
            'decepcionado', 'preocupado', 'disgustado'
        ];

        // Very positive indicators (sentiment_id = 5)
        $veryPositiveKeywords = [
            'excelente', 'fantástico', 'perfecto', 'increíble', 'maravilloso',
            'espectacular', 'genial', 'amor', 'amo', 'feliz', 'encantado',
            'satisfecho', 'recomiendo', 'recomendaré', '10/10', 'cinco estrellas'
        ];

        // Positive indicators (sentiment_id = 4)
        $positiveKeywords = [
            'bien', 'bueno', 'gracias', 'perfecto', 'ok', 'vale', 'correcto',
            'funciona', 'rápido', 'eficiente', 'útil', 'contento'
        ];

        // Check for very negative sentiment
        foreach ($veryNegativeKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 1,
                    'name' => 'Muy Negativo',
                    'reason' => "Detectada palabra clave: '$keyword'"
                ];
            }
        }

        // Check for negative sentiment
        foreach ($negativeKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 2,
                    'name' => 'Negativo',
                    'reason' => "Detectada palabra clave: '$keyword'"
                ];
            }
        }

        // Check for very positive sentiment
        foreach ($veryPositiveKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 5,
                    'name' => 'Muy Positivo',
                    'reason' => "Detectada palabra clave: '$keyword'"
                ];
            }
        }

        // Check for positive sentiment
        foreach ($positiveKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 4,
                    'name' => 'Positivo',
                    'reason' => "Detectada palabra clave: '$keyword'"
                ];
            }
        }

        // Additional patterns for negative sentiment
        if (preg_match('/no\s+(funciona|sirve|me\s+gusta|está\s+bien)/i', $message)) {
            return [
                'id' => 2,
                'name' => 'Negativo',
                'reason' => 'Detectado patrón negativo'
            ];
        }

        // Additional patterns for very negative sentiment
        if (preg_match('/es\s+una\s+(mierda|basura|estafa)/i', $message)) {
            return [
                'id' => 1,
                'name' => 'Muy Negativo',
                'reason' => 'Detectado lenguaje muy negativo'
            ];
        }

        // No strong emotion detected
        return null;
    }

    /**
     * Process service-related commands from WhatsApp messages
     */
    public function processServiceCommands($phoneNumber, $message)
    {
        try {
            // Clean and normalize the message
            $normalizedMessage = strtolower(trim($message));

            // Find user by phone number
            $user = $this->getUserByPhone($phoneNumber);
            if (!$user || isset($user->is_contact)) {
                // User not found or is just a contact, skip service processing
                return null;
            }

            // Get the user's contact for enterprises
            $contact = Contact::where('user_id', $user->id)->first();
            if (!$contact) {
                return null;
            }

            // Check if message contains service-related keywords
            $serviceKeywords = [
                'servicio', 'service', 'hosting', 'dominio', 'domain', 'web',
                'desarrollo', 'development', 'mantenimiento', 'maintenance',
                'soporte', 'support', 'backup', 'ssl', 'certificado',
                'renovar', 'renew', 'vence', 'expires', 'caducidad'
            ];

            $containsServiceKeyword = false;
            foreach ($serviceKeywords as $keyword) {
                if (strpos($normalizedMessage, $keyword) !== false) {
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

            if (!$containsServiceKeyword && !$isServiceReport) {
                return null;
            }

            // Get user's enterprises to check existing services
            $enterprises = $contact->enterprises()->get();
            $responseMessage = '';

            if ($enterprises->isEmpty()) {
                $responseMessage = "Veo que estás preguntando sobre servicios. Actualmente no tienes empresas registradas en nuestro sistema.\n\n";
                $responseMessage .= "Para consultar sobre nuevos servicios o registrar tu empresa, puedes:\n";
                $responseMessage .= "• Contactarnos a través de: https://revisionalpha.com/contactenos\n";
                $responseMessage .= "• O déjanos más detalles aquí y te ayudaremos.";
            } else {
                $responseMessage = "📋 *Información de tus servicios:*\n\n";

                foreach ($enterprises as $enterprise) {
                    $services = Service::where('enterprise_id', $enterprise->id)
                        ->with(['category', 'currency'])
                        ->orderBy('status', 'desc')
                        ->orderBy('next_billing', 'asc')
                        ->get();

                    $responseMessage .= "🏢 *{$enterprise->name}*\n";

                    if ($services->isEmpty()) {
                        $responseMessage .= "• No hay servicios registrados actualmente\n\n";
                    } else {
                        foreach ($services as $service) {
                            $statusEmoji = $service->status == 1 ? '✅' : '⚠️';
                            $responseMessage .= "{$statusEmoji} *{$service->description}*\n";

                            if ($service->category) {
                                $responseMessage .= "   Categoría: {$service->category->name}\n";
                            }

                            if ($service->price) {
                                $currency = $service->currency ? $service->currency->symbol : '$';
                                $responseMessage .= "   Precio: {$currency}" . number_format($service->price, 2) . "\n";
                            }

                            if ($service->next_billing) {
                                $nextBilling = \Carbon\Carbon::parse($service->next_billing)->format('d/m/Y');
                                $responseMessage .= "   Próxima facturación: {$nextBilling}\n";
                            }

                            if ($service->expires_at) {
                                $expiresAt = \Carbon\Carbon::parse($service->expires_at)->format('d/m/Y');
                                $daysUntilExpiry = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($service->expires_at), false);

                                if ($daysUntilExpiry <= 30 && $daysUntilExpiry >= 0) {
                                    $responseMessage .= "   ⚠️ Expira: {$expiresAt} (en {$daysUntilExpiry} días)\n";
                                } else if ($daysUntilExpiry < 0) {
                                    $responseMessage .= "   🔴 Expiró: {$expiresAt}\n";
                                } else {
                                    $responseMessage .= "   Expira: {$expiresAt}\n";
                                }
                            }

                            $responseMessage .= "\n";
                        }
                    }
                }

                // Add helpful information
                $responseMessage .= "💡 *¿Necesitas ayuda?*\n";

                if ($user->email) {
                    $accessToken = base64_encode($user->email . '|' . time());
                    $clientAreaUrl = "https://revisionalpha.com/login/token/" . $accessToken;
                    $responseMessage .= "• Área de clientes: {$clientAreaUrl}\n";
                }

                $responseMessage .= "• Soporte: https://revisionalpha.com/contactenos\n";
                $responseMessage .= "• O puedes escribirme aquí para consultas rápidas 😊";
            }

            // Send the response
            $this->sendWhatsApp($phoneNumber, $responseMessage);

            // Log the service inquiry
            \Log::info("Service inquiry processed for user", [
                'phone' => $phoneNumber,
                'user_id' => $user->id,
                'enterprises_count' => $enterprises->count(),
                'message_preview' => substr($message, 0, 50)
            ]);

            return ['success' => true, 'message' => 'Service information sent'];

        } catch (\Exception $e) {
            \Log::error('Error processing service commands: ' . $e->getMessage());
            return null;
        }
    }
}
