<?php

namespace App\Services;

use App\Mail\IncomingMessageNotification;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;
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
            Log::info("Incoming {$channel} message from {$cleanFrom}: {$body}");

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
                Log::info("Email notification sent to {$notificationEmail} for message {$messageSid}");
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

                // Check if user is asking about products
                $productResponse = $this->processProductCommands($cleanFrom, $body);
                if ($productResponse) {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'product_processed' => true]);
                }

                // Check if user sent "DEMO" command
                $demoResponse = $this->processDemoCommand($cleanFrom, $body);
                if ($demoResponse) {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'demo_processed' => true]);
                }

                // Check if user is requesting QR code
                $qrResponse = $this->processQrCommand($cleanFrom, $body);
                if ($qrResponse) {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'qr_processed' => true]);
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

                        Log::info("Auto AI response sent to {$cleanFrom}: ".\Illuminate\Support\Str::limit($aiMessage, 100));
                    } else {
                        Log::warning('Failed to get AI response: '.($claudeResponse['message'] ?? 'Unknown error'));
                    }

                    // Analyze sentiment of the incoming message
                    $this->analyzeSentiment($cleanFrom, $body);

                } catch (\Exception $e) {
                    Log::error('Error in auto AI response: '.$e->getMessage());
                }
            }

            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id]);
        } catch (\Exception $e) {
            Log::error('Error processing incoming message: '.$e->getMessage());

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

            if (! $user) {
                $user = \App\Models\User::where('phone', 'like', '%'.$phoneNumber.'%')->first();
            }

            if (! $user) {
                Log::info('No user found for sentiment analysis', ['phone' => $phoneNumber]);

                return;
            }

            // Find associated contact
            $contact = \App\Models\Contact::where('user_id', $user->id)->first();
            if (! $contact) {
                Log::info('No contact found for sentiment analysis', ['user_id' => $user->id]);

                return;
            }

            // Analyze message for emotional indicators
            $sentiment = $this->detectSentiment($messageBody);

            if ($sentiment) {
                // Create sentiment history entry
                \App\Models\ContactSentimentHistory::create([
                    'contact_id' => $contact->id,
                    'sentiment_id' => $sentiment['id'],
                    'notes' => 'Análisis automático de WhatsApp: '.$sentiment['reason'],
                ]);

                Log::info('Sentiment detected and recorded', [
                    'contact_id' => $contact->id,
                    'sentiment' => $sentiment['name'],
                    'message' => substr($messageBody, 0, 100),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in sentiment analysis: '.$e->getMessage());
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
            'demanda', 'denuncia', 'abogado', 'fraude',
        ];

        // Negative indicators (sentiment_id = 2)
        $negativeKeywords = [
            'molesto', 'enfadado', 'problema', 'falla', 'error', 'mal',
            'no funciona', 'deficiente', 'lento', 'caro', 'insatisfecho',
            'decepcionado', 'preocupado', 'disgustado',
        ];

        // Very positive indicators (sentiment_id = 5)
        $veryPositiveKeywords = [
            'excelente', 'fantástico', 'perfecto', 'increíble', 'maravilloso',
            'espectacular', 'genial', 'amor', 'amo', 'feliz', 'encantado',
            'satisfecho', 'recomiendo', 'recomendaré', '10/10', 'cinco estrellas',
        ];

        // Positive indicators (sentiment_id = 4)
        $positiveKeywords = [
            'bien', 'bueno', 'gracias', 'perfecto', 'ok', 'vale', 'correcto',
            'funciona', 'rápido', 'eficiente', 'útil', 'contento',
        ];

        // Check for very negative sentiment
        foreach ($veryNegativeKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 1,
                    'name' => 'Muy Negativo',
                    'reason' => "Detectada palabra clave: '$keyword'",
                ];
            }
        }

        // Check for negative sentiment
        foreach ($negativeKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 2,
                    'name' => 'Negativo',
                    'reason' => "Detectada palabra clave: '$keyword'",
                ];
            }
        }

        // Check for very positive sentiment
        foreach ($veryPositiveKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 5,
                    'name' => 'Muy Positivo',
                    'reason' => "Detectada palabra clave: '$keyword'",
                ];
            }
        }

        // Check for positive sentiment
        foreach ($positiveKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return [
                    'id' => 4,
                    'name' => 'Positivo',
                    'reason' => "Detectada palabra clave: '$keyword'",
                ];
            }
        }

        // Additional patterns for negative sentiment
        if (preg_match('/no\s+(funciona|sirve|me\s+gusta|está\s+bien)/i', $message)) {
            return [
                'id' => 2,
                'name' => 'Negativo',
                'reason' => 'Detectado patrón negativo',
            ];
        }

        // Additional patterns for very negative sentiment
        if (preg_match('/es\s+una\s+(mierda|basura|estafa)/i', $message)) {
            return [
                'id' => 1,
                'name' => 'Muy Negativo',
                'reason' => 'Detectado lenguaje muy negativo',
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
            if (! $user || isset($user->is_contact)) {
                // User not found or is just a contact, skip service processing
                return null;
            }

            // Get the user's contact for enterprises
            $contact = Contact::where('user_id', $user->id)->first();
            if (! $contact) {
                return null;
            }

            // Check if message contains service-related keywords
            $serviceKeywords = [
                'servicio', 'service', 'hosting', 'dominio', 'domain', 'web',
                'desarrollo', 'development', 'mantenimiento', 'maintenance',
                'soporte', 'support', 'backup', 'ssl', 'certificado',
                'renovar', 'renew', 'vence', 'expires', 'caducidad',
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

            if (! $containsServiceKeyword && ! $isServiceReport) {
                return null;
            }

            // Get user's enterprises to check existing services
            $enterprises = $contact->enterprises()->get();
            $responseMessage = '';

            if ($enterprises->isEmpty()) {
                $responseMessage = "Veo que estás preguntando sobre servicios. Actualmente no tienes empresas registradas en nuestro sistema.\n\n";
                $responseMessage .= "Para consultar sobre nuevos servicios o registrar tu empresa, puedes:\n";
                $responseMessage .= "• Contactarnos a través de: https://revisionalpha.com/contactenos\n";
                $responseMessage .= '• O déjanos más detalles aquí y te ayudaremos.';
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
                                $responseMessage .= "   Precio: {$currency}".number_format($service->price, 2)."\n";
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
                                } elseif ($daysUntilExpiry < 0) {
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

        } catch (\Exception $e) {
            Log::error('Error processing service commands: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Process product-related commands from WhatsApp messages
     */
    public function processProductCommands($phoneNumber, $message)
    {
        try {
            // Clean and normalize the message
            $normalizedMessage = strtolower(trim($message));

            // Check if message contains product-related keywords
            $productKeywords = [
                'productos', 'servicios', 'catalogo', 'precios', 'hosting', 'dominio',
                'ssl', 'backup', 'desarrollo', 'app', 'consultoria', 'soporte',
            ];

            $containsProductKeyword = false;
            foreach ($productKeywords as $keyword) {
                if (strpos($normalizedMessage, $keyword) !== false) {
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

            if (! $containsProductKeyword && ! $isProductCommand) {
                return null;
            }

            // Send product catalog
            $this->sendProductCatalog($phoneNumber);

            // Log the product inquiry
            Log::info('Product inquiry processed', [
                'phone' => $phoneNumber,
                'message_preview' => substr($message, 0, 50),
            ]);

            return ['success' => true, 'message' => 'Product catalog sent'];

        } catch (\Exception $e) {
            Log::error('Error processing product commands: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Send product catalog via WhatsApp
     */
    private function sendProductCatalog($phoneNumber)
    {
        try {
            // Find user and their team for product filtering
            $user = $this->getUserByPhone($phoneNumber);
            $teamId = 1; // Default to team 1 for demo

            if ($user && ! isset($user->is_contact) && $user->currentTeam) {
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

            if ($products->isEmpty()) {
                $message = "📦 *Catálogo de Productos*\n\n";
                $message .= "Actualmente no hay productos disponibles.\n\n";
                $message .= '📞 Contacta a soporte: https://revisionalpha.com/contactenos';

                $this->sendWhatsApp($phoneNumber, $message);

                return;
            }

            $message = "🛍️ *Catálogo de Productos y Servicios*\n\n";

            // Group products by category
            $productsByCategory = $products->groupBy('category.name');

            foreach ($productsByCategory as $categoryName => $categoryProducts) {
                $message .= "📂 *{$categoryName}*\n";

                foreach ($categoryProducts as $product) {
                    $currency = $product->currency ? $product->currency->symbol : '$';
                    $message .= "• *{$product->name}*\n";
                    $message .= "  💰 {$currency}".number_format($product->price, 2)."\n";
                    $message .= '  📝 '.\Illuminate\Support\Str::limit($product->description, 80)."\n\n";
                }
            }

            $message .= "💡 *Para contratar:*\n";
            $message .= "• Escribe: *contratar [nombre del producto]*\n";
            $message .= "• O contacta soporte: https://revisionalpha.com/contactenos\n\n";
            $message .= '🛒 *Tu carrito:* Escribe *carrito* para ver tus productos seleccionados';

            $this->sendWhatsApp($phoneNumber, $message);

        } catch (\Exception $e) {
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
        }
    }

    /**
     * Process DEMO command for automatic user registration
     */
    public function processDemoCommand($phoneNumber, $message)
    {
        try {
            $normalizedMessage = trim($message);

            // Check if message is exactly "DEMO" (case sensitive)
            if ($normalizedMessage === 'DEMO') {
                // Check if user already exists
                $existingUser = $this->getUserByPhone($phoneNumber);
                if ($existingUser && ! isset($existingUser->is_contact)) {
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
            if ($demoState) {
                return $this->handleDemoRegistrationStep($phoneNumber, $message, $demoState);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error processing demo command: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Handle demo registration steps
     */
    private function handleDemoRegistrationStep($phoneNumber, $message, $state)
    {
        try {
            switch ($state) {
                case 'awaiting_name':
                    if (strlen(trim($message)) < 2) {
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
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->sendWhatsApp($phoneNumber, '❌ Por favor, ingresa un email válido (ejemplo: tu@email.com):');

                        return ['success' => false, 'message' => 'Invalid email'];
                    }

                    // Check if email already exists
                    $existingUser = \App\Models\User::where('email', $email)->first();
                    if ($existingUser) {
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

        } catch (\Exception $e) {
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
        try {
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
                'status_id' => 1, // Active
                'creator_id' => 1, // System user
            ]);

            // Associate contact with Idoneo Technologies (ID: 2)
            $idoneoTech = \App\Models\Enterprise::find(2);
            if ($idoneoTech) {
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

        } catch (\Exception $e) {
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
        \Illuminate\Support\Facades\Cache::put("demo_state_{$phoneNumber}", $state, 600); // 10 minutes
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
        \Illuminate\Support\Facades\Cache::put("demo_data_{$phoneNumber}", $data, 600); // 10 minutes
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

        if (!in_array(trim($message), $qrKeywords)) {
            return false;
        }

        try {
            // Get user information
            $user = $this->getUserByPhone($phoneNumber);

            if (!$user) {
                // If no user found, send generic QR
                $this->sendGenericQr($phoneNumber);
                return true;
            }

            // Generate personalized QR with user data
            $this->sendPersonalizedQr($phoneNumber, $user);
            return true;

        } catch (\Exception $e) {
            Log::error('Error processing QR command: ' . $e->getMessage());
            $this->sendWhatsApp($phoneNumber, '❌ Hubo un error generando tu código QR. Por favor, intenta más tarde.');
            return true;
        }
    }

    /**
     * Send generic QR code for unknown users
     */
    private function sendGenericQr($phoneNumber)
    {
        try {
            // Generate QR code image
            $qrImagePath = $this->generateQrCodeImage(
                "https://revisionalpha.com/contactenos?name=NOMBRE&email=EMAIL&phone=PHONE&message=FRASE_INSPIRE_LARAVEL",
                'generic_qr'
            );

            if ($qrImagePath) {
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
            } else {
                // Fallback to text-only if image generation fails
                $this->sendGenericQrTextOnly($phoneNumber);
            }

        } catch (\Exception $e) {
            Log::error('Error generating generic QR: ' . $e->getMessage());
            $this->sendGenericQrTextOnly($phoneNumber);
        }
    }

    /**
     * Send personalized QR code for known users
     */
    private function sendPersonalizedQr($phoneNumber, $user)
    {
        try {
            // Prepare user data
            $name = $user->name ?? 'Usuario';
            $email = $user->email ?? 'email@ejemplo.com';
            $phone = $user->phone ?? $phoneNumber;

            // Create personalized message
            $personalMessage = "¡Hola {$name}! Estamos aquí para ayudarte con tus necesidades tecnológicas.";

            // Generate personalized URL
            $personalizedUrl = "https://revisionalpha.com/contactenos?" . http_build_query([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'message' => $personalMessage
            ]);

            // Generate QR code image
            $qrImagePath = $this->generateQrCodeImage($personalizedUrl, 'personalized_qr');

            if ($qrImagePath) {
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
                $message .= $personalizedUrl . "\n\n";
                $message .= "💡 *¿Qué puedes hacer?*\n";
                $message .= "• Compartir este QR con colegas\n";
                $message .= "• Usarlo en presentaciones\n";
                $message .= "• Agregarlo a tu firma de email\n\n";
                $message .= "🎯 *¿Necesitas modificar algo?* Responde con 'modificar' para cambiar los datos.";

                $this->sendWhatsApp($phoneNumber, $message);
            } else {
                // Fallback to text-only if image generation fails
                $this->sendPersonalizedQrTextOnly($phoneNumber, $user);
            }

        } catch (\Exception $e) {
            Log::error('Error generating personalized QR: ' . $e->getMessage());
            $this->sendPersonalizedQrTextOnly($phoneNumber, $user);
        }
    }

    /**
     * Generate QR code image using chillerlan/php-qrcode
     */
    private function generateQrCodeImage($data, $filename)
    {
        try {
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
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Generate unique filename
            $uniqueFilename = $filename . '_' . time() . '_' . uniqid() . '.png';
            $fullPath = $storagePath . '/' . $uniqueFilename;

            // Save binary PNG to file
            file_put_contents($fullPath, $pngBinary);

            // Return the public URL path
            return 'storage/qr-codes/' . $uniqueFilename;

        } catch (\Exception $e) {
            Log::error('Error generating QR code image: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp message with media attachment using Twilio API
     */
    private function sendWhatsAppWithMedia($phoneNumber, $mediaPath, $type)
    {
        try {
            $fullMediaPath = public_path($mediaPath);

            if (!file_exists($fullMediaPath)) {
                Log::error("Media file not found: {$fullMediaPath}");
                return false;
            }

            // Format phone number for WhatsApp
            $whatsappTo = 'whatsapp:' . $this->formatPhoneNumber($phoneNumber);
            $whatsappFrom = $this->whatsappFromNumber;

            // Get file info
            $fileSize = filesize($fullMediaPath);
            $fileExtension = pathinfo($fullMediaPath, PATHINFO_EXTENSION);
            $mimeType = $this->getMimeType($fileExtension);

            // Check file size (Twilio limit: 16MB for media)
            if ($fileSize > 16 * 1024 * 1024) {
                Log::error("File too large for Twilio: {$fileSize} bytes");
                return false;
            }

            // Get the full public URL for the media
            $publicUrl = url($mediaPath);

            // For local development, we'll use a placeholder or skip media
            if (strpos($publicUrl, 'localhost') !== false || strpos($publicUrl, '.test') !== false) {
                Log::info("Local environment detected, skipping media send for: {$publicUrl}");
                // In local environment, we'll just send the text message
                $message = $this->client->messages->create(
                    $whatsappTo,
                    [
                        'from' => $whatsappFrom,
                        'body' => "🔄 Tu código QR está listo! Escanéalo para acceder a revision alpha.\n\n🔗 Enlace directo: {$publicUrl}"
                    ]
                );
            } else {
                // Production environment - send with media
                $message = $this->client->messages->create(
                    $whatsappTo,
                    [
                        'from' => $whatsappFrom,
                        'body' => "🔄 Tu código QR está listo! Escanéalo para acceder a revision alpha.",
                        'mediaUrl' => [$publicUrl]
                    ]
                );
            }

            if ($message->sid) {
                Log::info("WhatsApp media message sent successfully", [
                    'message_sid' => $message->sid,
                    'to' => $phoneNumber,
                    'media_path' => $mediaPath,
                    'type' => $type,
                    'status' => $message->status
                ]);

                // Save to conversation history
                $this->saveMediaMessageToHistory($phoneNumber, $message, $mediaPath, $type);

                return true;
            } else {
                Log::error("Failed to send WhatsApp media message: No message SID returned");
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp media: ' . $e->getMessage(), [
                'phone_number' => $phoneNumber,
                'media_path' => $mediaPath,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        if (strlen($cleanNumber) === 9 && substr($cleanNumber, 0, 1) === '9') {
            // Argentine mobile number, add 54
            $cleanNumber = '54' . $cleanNumber;
        } elseif (strlen($cleanNumber) === 10 && substr($cleanNumber, 0, 2) === '15') {
            // Argentine mobile with 15 prefix, replace with 54 9
            $cleanNumber = '54' . substr($cleanNumber, 2);
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
        try {
            $conversation = Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => config('services.twilio.whatsapp_from'),
                'to' => $phoneNumber,
                'body' => "🔄 Tu código QR está listo! Escanéalo para acceder a revision alpha.",
                'status' => $twilioMessage->status ?? 'sent',
                'direction' => 'outbound',
                'media' => [
                    [
                        'url' => url($mediaPath),
                        'content_type' => 'image/png',
                        'type' => $type,
                        'local_path' => $mediaPath
                    ]
                ],
                'metadata' => [
                    'twilio_message_sid' => $twilioMessage->sid,
                    'qr_type' => $type,
                    'media_sent' => true,
                    'file_path' => $mediaPath
                ],
            ]);

            Log::info("Media message saved to conversation history", [
                'conversation_id' => $conversation->id,
                'message_sid' => $twilioMessage->sid,
                'phone_number' => $phoneNumber
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving media message to history: ' . $e->getMessage());
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

        $personalizedUrl = "https://revisionalpha.com/contactenos?" . http_build_query([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $personalMessage
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
        $message .= $personalizedUrl . "\n\n";
        $message .= "💡 *¿Qué puedes hacer?*\n";
        $message .= "• Compartir este QR con colegas\n";
        $message .= "• Usarlo en presentaciones\n";
        $message .= "• Agregarlo a tu firma de email\n\n";
        $message .= "🎯 *¿Necesitas modificar algo?* Responde con 'modificar' para cambiar los datos.";

        $this->sendWhatsApp($phoneNumber, $message);
    }
}
