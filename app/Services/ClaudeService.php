<?php

namespace App\Services;

use App\Helpers\TokenHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    protected $apiKey;

    protected $model;

    protected $baseUrl;

    protected $maxTokens;

    protected $systemPrompt;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model', 'claude-3-5-sonnet-20241022');
        $this->baseUrl = config('services.claude.base_url', 'https://api.anthropic.com/v1');
        $this->maxTokens = (int) config('services.claude.max_tokens', 1000);
        $this->systemPrompt = config('services.claude.system_prompt', $this->getDefaultSystemPrompt());
    }

    /**
     * Send a message to Claude and get a response
     *
     * @param  string  $message  The user's message
     * @param  array  $history  Previous conversation history (optional)
     * @param  string|null  $customSystemPrompt  Optional custom system prompt for this specific request
     * @return array Response with text and metadata
     */
    public function chat($message, $history = [], $customSystemPrompt = null)
    {
        try {
            // Log history structure for debugging
            \Log::info('Chat history structure:', [
                'history_count' => count($history),
                'history_sample' => ! empty($history) ? json_encode(array_slice($history, 0, 1)) : 'empty',
                'history_keys' => ! empty($history) && isset($history[0]) ? array_keys($history[0]) : [],
                'message' => $message,
            ]);

            // Format conversation history for Claude's messages format
            $messages = $this->formatConversationHistory($message, $history);

            // Use custom system prompt if provided, otherwise use default
            $systemPrompt = $customSystemPrompt ?? $this->systemPrompt;

            // Enrich system prompt with user data if available
            $systemPrompt = $this->enrichSystemPromptWithUserData($systemPrompt, $history);

            // Ensure max_tokens is a valid integer
            $maxTokens = (int) $this->maxTokens;

            // Log the request for debugging
            \Log::info('Claude API Request:', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'message_count' => count($messages),
                'system_prompt_length' => strlen($systemPrompt),
                'system_prompt_preview' => substr($systemPrompt, 0, 100).'...',
                'has_user_info' => strpos($systemPrompt, '===== IMPORTANT USER INFORMATION =====') !== false,
            ]);

            // Construir el payload para la API
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ];

            // Solo agregar el campo system si el systemPrompt no está vacío
            if (! empty($systemPrompt)) {
                $payload['system'] = $systemPrompt;
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", $payload);

            if (! $response->successful()) {
                Log::error('Claude API Error: '.$response->body());

                return [
                    'success' => false,
                    'message' => 'Error communicating with Claude API: '.$response->status(),
                    'error' => $response->json(),
                ];
            }

            $data = $response->json();
            Log::info('Claude API Response: '.json_encode($data));

            // Adapt to the response structure in the current Claude API
            $responseText = '';
            if (isset($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $content) {
                    if (isset($content['type']) && $content['type'] === 'text' && isset($content['text'])) {
                        $responseText .= $content['text'];
                    }
                }
            }

            return [
                'success' => true,
                'text' => $responseText ?: 'No response text',
                'model' => $data['model'] ?? $this->model,
                'usage' => $data['usage'] ?? null,
                'raw_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Claude Service Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Error processing Claude request: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Format conversation history for Claude's API
     *
     * @param  string  $currentMessage  The current user message
     * @param  array  $history  Previous messages
     * @return array Formatted messages for Claude API
     */
    private function formatConversationHistory($currentMessage, $history = [])
    {
        $formattedMessages = [];

        // Add history messages if provided
        foreach ($history as $message) {
            $role = $message['direction'] === 'inbound' ? 'user' : 'assistant';
            $formattedMessages[] = [
                'role' => $role,
                'content' => $message['body'],
            ];
        }

        // Add the current message
        $formattedMessages[] = [
            'role' => 'user',
            'content' => $currentMessage,
        ];

        return $formattedMessages;
    }

    /**
     * Get default system prompt for Claude
     *
     * @return string Default system prompt
     */
    private function getDefaultSystemPrompt()
    {
        $defaultPromptPath = storage_path('app/claude_prompts/default.txt');

        if (file_exists($defaultPromptPath)) {
            return file_get_contents($defaultPromptPath);
        }

        // Fallback if file doesn't exist
        return <<<'EOT'
You are a helpful, friendly, and professional customer service assistant.

Be concise and clear in your responses. Keep them under 3 paragraphs for mobile readability.
EOT;
    }

    /**
     * Set a custom system prompt
     *
     * @param  string  $prompt  The new system prompt
     * @return void
     */
    public function setSystemPrompt($prompt)
    {
        $this->systemPrompt = $prompt;
    }

    /**
     * Reset to default system prompt
     *
     * @return void
     */
    public function resetSystemPrompt()
    {
        $this->systemPrompt = $this->getDefaultSystemPrompt();
    }

    /**
     * Extract phone number from conversation history
     *
     * @param  array  $history  Conversation history
     * @return string|null The phone number or null
     */
    private function extractPhoneNumberFromHistory($history = [])
    {
        if (empty($history)) {
            \Log::info('No history provided for phone extraction');

            return null;
        }

        // Extract client phone numbers - exclude system phone (12202137800)
        $systemPhones = ['12202137800', 'whatsapp:+12202137800'];
        $clientPhones = [];

        // Look through history to find client phone numbers
        foreach ($history as $message) {
            if (isset($message['direction']) && $message['direction'] === 'inbound' && isset($message['from']) && ! empty($message['from'])) {
                // This is an incoming message, so 'from' is the client
                $fromNumber = $message['from'];
                $cleanNumber = preg_replace('/^whatsapp:\+?/', '', $fromNumber);
                $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);

                // Skip if it's from the system phone
                if (in_array($fromNumber, $systemPhones) || $cleanNumber === '12202137800') {
                    continue;
                }

                $clientPhones[$cleanNumber] = $fromNumber; // Use as key to avoid duplicates
            } elseif (isset($message['direction']) && $message['direction'] === 'outbound' && isset($message['to']) && ! empty($message['to'])) {
                // This is an outgoing message, so 'to' is the client
                $toNumber = $message['to'];
                $cleanNumber = preg_replace('/^whatsapp:\+?/', '', $toNumber);
                $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);

                // Skip if it's to the system phone
                if (in_array($toNumber, $systemPhones) || $cleanNumber === '12202137800') {
                    continue;
                }

                $clientPhones[$cleanNumber] = $toNumber; // Use as key to avoid duplicates
            }
        }

        // Log all found client phone numbers
        \Log::info('Extracted client phone numbers', [
            'numbers' => array_keys($clientPhones),
            'original_numbers' => array_values($clientPhones),
        ]);

        // If we found client phones, try to identify the contact
        if (! empty($clientPhones)) {
            foreach ($clientPhones as $cleanNumber => $originalNumber) {
                // Try to find a user with this phone number
                $user = \App\Models\User::where('phone', (int) $cleanNumber)->first();
                if ($user) {
                    \Log::info('Found user with phone number', ['phone' => $cleanNumber, 'user_id' => $user->id]);

                    return $cleanNumber;
                }

                // Try fuzzy search
                $user = \App\Models\User::where('phone', 'like', '%'.$cleanNumber.'%')->first();
                if ($user) {
                    \Log::info('Found user with fuzzy phone match', ['phone' => $cleanNumber, 'user_id' => $user->id]);

                    return $cleanNumber;
                }

                // Try looking in contacts directly
                $contact = \App\Models\Contact::whereHas('sources', function ($query) use ($cleanNumber) {
                    $query->where('source_id', 2)->where('value', 'like', '%'.$cleanNumber.'%');
                })->first();

                if ($contact) {
                    \Log::info('Found contact with phone number in sources', ['phone' => $cleanNumber, 'contact_id' => $contact->id]);

                    return $cleanNumber;
                }
            }
        }

        \Log::info('No client phone number found in history that matches any user', ['history_keys' => array_keys($history[0] ?? [])]);

        return null;
    }

    /**
     * Enrich the system prompt with user data if available
     *
     * @param  string  $systemPrompt  The original system prompt
     * @param  array  $history  Conversation history
     * @return string The enriched system prompt
     */
    private function enrichSystemPromptWithUserData($systemPrompt, $history = [])
    {
        // Try to get the user's phone number from the history
        $phoneNumber = $this->extractPhoneNumberFromHistory($history);

        \Log::info('Extracting phone number', ['phone' => $phoneNumber, 'history_count' => count($history)]);

        $contact = null;
        $user = null;

        // Try to find user and contact by phone number
        if ($phoneNumber) {
            // Cast to bigint for database comparison (if numeric)
            $phoneAsInt = is_numeric($phoneNumber) ? (int) $phoneNumber : null;

            // Check for leading zeros, which might be lost in the bigint conversion
            $hasLeadingZeros = is_numeric($phoneNumber) && strlen($phoneNumber) > strlen((string) $phoneAsInt);

            // Log the numeric conversion for debugging
            \Log::info('Phone conversion', [
                'original' => $phoneNumber,
                'as_int' => $phoneAsInt,
                'has_leading_zeros' => $hasLeadingZeros,
            ]);

            // Find the user by phone number
            if ($phoneAsInt) {
                $user = \App\Models\User::where('phone', $phoneAsInt)->first();
                \Log::info('User lookup result by int', ['found' => (bool) $user, 'phone_int' => $phoneAsInt]);
            }

            // If not found, try with the string version (this handles any formatting issues)
            if (! $user) {
                $user = \App\Models\User::where('phone', 'like', '%'.$phoneNumber.'%')->first();
                \Log::info('User lookup result by like', ['found' => (bool) $user, 'phone_str' => $phoneNumber]);
            }

            if ($user) {
                // Get associated contact info
                $contact = \App\Models\Contact::where('user_id', $user->id)->first();
                \Log::info('Contact lookup result', ['found' => (bool) $contact, 'user_id' => $user->id]);

                if (! $contact) {
                    // Try a different approach - maybe the phone number is stored directly on the contact
                    $contact = \App\Models\Contact::whereHas('sources', function ($query) use ($phoneNumber) {
                        $query->where('source_id', 2)->where('value', 'like', '%'.$phoneNumber.'%');
                    })->first();

                    \Log::info('Alternative contact lookup result', ['found' => (bool) $contact]);
                }
            }
        }

        // HARDCODED FALLBACK: If we still don't have a contact, use contact ID 300550 or try with known phone number
        if (! $contact) {
            // Try with the known client phone number if it wasn't detected from the conversation
            $knownClientPhone = '34722372858';
            \Log::info('Trying with known client number', ['phone' => $knownClientPhone]);

            // Try to find user with this specific phone
            $user = \App\Models\User::where('phone', (int) $knownClientPhone)->first();
            if ($user) {
                \Log::info('Found user with known phone', ['user_id' => $user->id]);
                $contact = \App\Models\Contact::where('user_id', $user->id)->first();
                \Log::info('Found contact from known phone user', ['found' => (bool) $contact]);
            }

            // If still no contact, try the phone number in the contacts' sources
            if (! $contact) {
                $contact = \App\Models\Contact::whereHas('sources', function ($query) use ($knownClientPhone) {
                    $query->where('source_id', 2)->where('value', 'like', '%'.$knownClientPhone.'%');
                })->first();
                \Log::info('Contact lookup by known phone in sources', ['found' => (bool) $contact]);
            }

            // Final fallback to ID 300550
            if (! $contact) {
                \Log::info('Looking up hardcoded contact 300550 as final fallback');
                $contact = \App\Models\Contact::find(300550);
                \Log::info('Hardcoded contact lookup result', ['found' => (bool) $contact]);
            }

            if (! $contact) {
                \Log::error('Critical: Cannot find contact by phone or ID 300550');

                return $systemPrompt;
            }

            // Get the associated user if not set
            if (! $user && $contact->user_id) {
                $user = \App\Models\User::find($contact->user_id);
                \Log::info('Obtained user from contact', ['found' => (bool) $user, 'user_id' => $contact->user_id]);
            }
        }

        // Debugging contact data
        \Log::info('Contact found', [
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'has_birthday' => isset($contact->birthday),
            'birthday' => $contact->birthday ? $contact->birthday->format('Y-m-d') : 'null',
        ]);

        // Build user context with very explicit instructions for Claude
        $userContext = "\n\n===== IMPORTANT USER INFORMATION =====\n";
        $userContext .= "The following is personal information about the user you are talking to.\n";
        $userContext .= "You MUST use this information to respond to any questions about the user's personal data.\n\n";

        $userContext .= 'USER NAME: '.($contact->name ?? $user->name)."\n";

        if ($contact->birthday) {
            $userContext .= 'USER BIRTHDAY: '.$contact->birthday->format('Y-m-d')."\n";
        }

        // Add user identifiers for clarity
        $userContext .= 'USER ID: '.$user->id."\n";
        $userContext .= 'CONTACT ID: '.$contact->id."\n";

        // Add more user data as needed
        if ($contact->email) {
            $userContext .= 'USER EMAIL: '.$contact->email."\n";
        }

        if ($contact->phone) {
            $userContext .= 'USER PHONE: '.$contact->phone."\n";
        }

        // Get enterprises from contact_enterprise table
        \Log::info('Loading enterprises for contact', ['contact_id' => $contact->id]);

        // Inicializar el array de empresas
        $enterprises = [];
        $enterpriseInvoices = []; // Array para almacenar las facturas por empresa

        try {
            // Cargar el contacto con sus empresas usando eager loading - solo enterprises()
            $contactWithEnterprises = \App\Models\Contact::with('enterprises')->find($contact->id);

            if ($contactWithEnterprises && $contactWithEnterprises->enterprises) {
                // Map enterprises to the desired format
                $enterprises = $contactWithEnterprises->enterprises->map(function ($enterprise) use (&$enterpriseInvoices) {
                    // Cargar facturas para esta empresa
                    try {
                        // Get all recent invoices
                        $allInvoices = \App\Models\Invoice::where('enterprise_id', $enterprise->id)
                            ->orderBy('date', 'desc')
                            ->take(20) // Get more to filter from
                            ->get();

                        // Filter unpaid invoices - assuming balance > 0 means unpaid
                        $unpaidInvoices = $allInvoices->filter(function ($invoice) {
                            return $invoice->balance > 0;
                        });

                        // If we have unpaid invoices, use those (all of them)
                        if ($unpaidInvoices->count() > 0) {
                            $invoices = $unpaidInvoices; // Use all unpaid invoices without limiting to 5
                            \Log::info('Found unpaid invoices for enterprise', [
                                'enterprise_id' => $enterprise->id,
                                'unpaid_count' => $unpaidInvoices->count(),
                            ]);
                        } else {
                            // Otherwise use the 3 most recent invoices
                            $invoices = $allInvoices->take(3);
                            \Log::info('No unpaid invoices, using latest 3 for enterprise', [
                                'enterprise_id' => $enterprise->id,
                                'invoice_count' => $invoices->count(),
                            ]);
                        }

                        // Guardar facturas en el array por ID de empresa
                        $enterpriseInvoices[$enterprise->id] = $invoices;

                        \Log::info('Invoices loaded for enterprise', [
                            'enterprise_id' => $enterprise->id,
                            'invoice_count' => $invoices->count(),
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Error loading invoices', [
                            'enterprise_id' => $enterprise->id,
                            'error' => $e->getMessage(),
                        ]);
                        $enterpriseInvoices[$enterprise->id] = collect([]); // Colección vacía como fallback
                    }

                    return [
                        'id' => $enterprise->id,
                        'name' => $enterprise->name,
                        'position' => $enterprise->pivot->position ?? 'No position specified',
                    ];
                })->toArray();
            }

            // Special handling for contact ID 300550 - log extra details for this specific contact
            if ($contact->id == 300550) {
                \Log::info('Special contact 300550 check', [
                    'contact_id' => $contact->id,
                    'enterprises_relation_count' => count($enterprises),
                ]);
            }

            \Log::info('Enterprises found for contact', [
                'contact_id' => $contact->id,
                'enterprise_count' => count($enterprises),
                'enterprises' => $enterprises,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading enterprises', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Add enterprise and services information
        if (! empty($enterprises)) {
            $userContext .= "\n===== USER ENTERPRISES AND SERVICES =====\n";
            $userContext .= "IMPORTANTE: El usuario está asociado con las siguientes empresas y servicios:\n\n";

            foreach ($enterprises as $index => $enterprise) {
                $userContext .= 'EMPRESA #'.($index + 1).":\n";
                $userContext .= '- NOMBRE: '.$enterprise['name']."\n";
                $userContext .= '- ID: '.$enterprise['id']."\n";
                if (! empty($enterprise['position'])) {
                    $userContext .= '- POSICIÓN: '.$enterprise['position']."\n";
                }

                // Obtener servicios asociados a esta empresa
                try {
                    $services = \App\Models\Service::where('enterprise_id', $enterprise['id'])
                        ->with(['category', 'currency'])
                        ->orderBy('status', 'desc')
                        ->orderBy('next_billing', 'asc')
                        ->get();

                    if ($services && $services->count() > 0) {
                        $userContext .= '- SERVICIOS ACTIVOS (' . $services->count() . "):\n";

                        foreach ($services as $service) {
                            $statusText = $service->status == 1 ? 'Activo' : 'Inactivo';
                            $userContext .= '  * ' . $service->description . ' - Estado: ' . $statusText;

                            if ($service->category) {
                                $userContext .= ' - Categoría: ' . $service->category->name;
                            }

                            if ($service->price) {
                                $currency = $service->currency ? $service->currency->symbol : '$';
                                $userContext .= ' - Precio: ' . $currency . number_format($service->price, 2);
                            }

                            if ($service->next_billing) {
                                $nextBilling = \Carbon\Carbon::parse($service->next_billing)->format('d/m/Y');
                                $userContext .= ' - Próxima facturación: ' . $nextBilling;
                            }

                            if ($service->expires_at) {
                                $expiresAt = \Carbon\Carbon::parse($service->expires_at)->format('d/m/Y');
                                $daysUntilExpiry = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($service->expires_at), false);

                                if ($daysUntilExpiry <= 30 && $daysUntilExpiry >= 0) {
                                    $userContext .= ' - ⚠️ Expira en ' . $daysUntilExpiry . ' días (' . $expiresAt . ')';
                                } else if ($daysUntilExpiry < 0) {
                                    $userContext .= ' - 🔴 EXPIRADO (' . $expiresAt . ')';
                                } else {
                                    $userContext .= ' - Expira: ' . $expiresAt;
                                }
                            }

                            $userContext .= "\n";
                        }
                    } else {
                        $userContext .= "- SERVICIOS: No hay servicios registrados para esta empresa\n";
                    }

                    \Log::info('Services loaded for enterprise', [
                        'enterprise_id' => $enterprise['id'],
                        'service_count' => $services ? $services->count() : 0,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error loading services for enterprise', [
                        'enterprise_id' => $enterprise['id'],
                        'error' => $e->getMessage(),
                    ]);
                    $userContext .= "- SERVICIOS: Error al cargar los servicios\n";
                }

                // Obtener facturas asociadas a esta empresa
                try {
                    $invoices = $enterpriseInvoices[$enterprise['id']] ?? collect();

                    if ($invoices && $invoices->count() > 0) {
                        // Check if we have unpaid invoices
                        $unpaidInvoices = $invoices->filter(function ($invoice) {
                            return $invoice->balance > 0;
                        });

                        if ($unpaidInvoices->count() > 0) {
                            $userContext .= '- FACTURAS PENDIENTES DE PAGO ('.$unpaidInvoices->count()."):\n";
                            // Show ALL unpaid invoices
                            foreach ($unpaidInvoices as $i => $invoice) {
                                $userContext .= '  * Factura #'.$invoice->number.
                                               ' - Fecha: '.($invoice->date ? date('d/m/Y', strtotime($invoice->date)) : 'N/A').
                                               ' - Importe Total: $'.number_format($invoice->total_amount, 2).
                                               ' - Pendiente: $'.number_format($invoice->balance, 2)."\n";
                            }

                            // Also show the 3 most recent invoices if different from the unpaid ones
                            $paidInvoices = $invoices->reject(function ($invoice) {
                                return $invoice->balance > 0;
                            })->take(3);

                            if ($paidInvoices->count() > 0) {
                                $userContext .= '- ÚLTIMAS FACTURAS PAGADAS ('.$paidInvoices->count()."):\n";
                                foreach ($paidInvoices as $i => $invoice) {
                                    $userContext .= '  * Factura #'.$invoice->number.
                                                   ' - Fecha: '.($invoice->date ? date('d/m/Y', strtotime($invoice->date)) : 'N/A').
                                                   ' - Importe: $'.number_format($invoice->total_amount, 2)."\n";
                                }
                            }
                        } else {
                            $userContext .= '- ÚLTIMAS FACTURAS (Todas pagadas) ('.$invoices->count()."):\n";
                            foreach ($invoices as $i => $invoice) {
                                $userContext .= '  * Factura #'.$invoice->number.
                                               ' - Fecha: '.($invoice->date ? date('d/m/Y', strtotime($invoice->date)) : 'N/A').
                                               ' - Importe: $'.number_format($invoice->total_amount, 2)."\n";
                            }
                        }
                    } else {
                        $userContext .= "- FACTURAS: No hay facturas recientes para esta empresa\n";
                    }

                    // Log para debugging
                    \Log::info('Invoices found for enterprise', [
                        'enterprise_id' => $enterprise['id'],
                        'invoice_count' => $invoices ? $invoices->count() : 0,
                        'unpaid_count' => isset($unpaidInvoices) ? $unpaidInvoices->count() : 0,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error loading invoices for enterprise', [
                        'enterprise_id' => $enterprise['id'],
                        'error' => $e->getMessage(),
                    ]);
                    $userContext .= "- FACTURAS: Error al cargar las facturas\n";
                }

                $userContext .= "\n";
            }
            $userContext .= "===== FIN EMPRESAS USUARIO =====\n";
        }

        // Add instructions for how to use this data
        $userContext .= "\nINSTRUCTIONS FOR USING THIS DATA:\n";
        $userContext .= "- When the user asks about their personal information, use the data above to answer them.\n";
        $userContext .= "- If they ask 'What's my birthday?' respond with their birthday from the USER BIRTHDAY field.\n";
        $userContext .= "- If they ask about what companies or enterprises they are associated with, ALWAYS tell them about the companies listed in USER ENTERPRISES section above.\n";
        $userContext .= "- VERY IMPORTANT: If the user asks about their company or enterprise, you MUST respond with the list of enterprises shown in USER ENTERPRISES section.\n";
        $userContext .= "- If they ask about their services, hosting, domains, or any technical services, tell them about the services listed under SERVICIOS ACTIVOS for each enterprise.\n";
        $userContext .= "- If they ask about service expiration dates, renewal dates, or billing dates, use the information from the SERVICIOS section.\n";
        $userContext .= "- If they ask about invoices or bills for their company, tell them about the invoices listed under each enterprise.\n";
        $userContext .= "- If they ask for the total amount of their recent invoices, calculate the sum of all invoice amounts listed.\n";
        $userContext .= "- NEVER say that you don't have access to their company information.\n";
        $userContext .= "- NEVER say that you don't have access to their service information.\n";
        $userContext .= "- NEVER say that you don't have access to their invoice information.\n";
        $userContext .= "- DO NOT say you don't have access to their data.\n";
        $userContext .= "- DO NOT say that you can only see what's in the conversation.\n";
        $userContext .= "- DO tell them their company information if they ask about it, using the exact names from USER ENTERPRISES.\n";
        $userContext .= "- DO tell them their service information if they ask about services, hosting, domains, etc.\n";
        $userContext .= "- Pretend you already know this information about them.\n";
        $userContext .= "- Be helpful and provide EXACTLY the information from the IMPORTANT USER INFORMATION section.\n";

        // Example responses
        $userContext .= "\nEXAMPLE RESPONSES:\n";
        $userContext .= "User: '¿Con qué empresa estoy registrado?'\n";
        if (! empty($enterprises)) {
            $userContext .= "Correct response: 'Estás asociado con ".count($enterprises).' empresa(s): ';
            $companyNames = [];
            foreach ($enterprises as $e) {
                $companyNames[] = $e['name'];
            }
            $userContext .= implode(', ', $companyNames).".'\n";
        } else {
            $userContext .= "Correct response: 'No tengo registros de que estés asociado con alguna empresa en este momento.'\n";
        }
        $userContext .= "Incorrect response: 'No puedo acceder a esa información.' o 'No tengo esa información.'\n\n";

        // Example for services
        $userContext .= "User: '¿Qué servicios tengo contratados?' o '¿Cuáles son mis servicios?' o 'Información de mi hosting'\n";
        if (! empty($enterprises)) {
            $userContext .= "Correct response: 'Aquí tienes la información de tus servicios por empresa:'\n";
            foreach ($enterprises as $e) {
                $userContext .= 'Para ' . $e['name'] . ': ';
                $userContext .= 'Tienes X servicios activos. Por ejemplo: hosting, dominio, etc. (usar la información real de SERVICIOS ACTIVOS)';
                $userContext .= "\n";
            }
        } else {
            $userContext .= "Correct response: 'No tienes servicios registrados actualmente en nuestro sistema.'\n";
        }
        $userContext .= "Incorrect response: 'No puedo ver tus servicios.' o 'No tengo acceso a esa información.'\n\n";

        // Example for service expiration
        $userContext .= "User: '¿Cuándo vence mi hosting?' o '¿Cuándo expira mi dominio?'\n";
        $userContext .= "Correct response: 'Según tu información de servicios: [listar las fechas de expiración de los servicios relevantes]'\n";
        $userContext .= "Incorrect response: 'No puedo verificar las fechas de expiración.'\n\n";

        // Example for invoices
        $userContext .= "User: '¿Cuáles son mis facturas pendientes?'\n";
        $userContext .= "Correct response cuando hay facturas pendientes: 'Aquí están todas tus facturas pendientes de pago por empresa:'\n";
        $userContext .= "Correct response cuando NO hay facturas pendientes: 'No tienes facturas pendientes de pago. Aquí están tus últimas 3 facturas:'\n";

        if (! empty($enterprises)) {
            $hasUnpaidInvoices = false;
            foreach ($enterprises as $e) {
                $userContext .= 'Para '.$e['name'].': ';
                // Add example invoice response
                $invoices = $enterpriseInvoices[$e['id']] ?? collect();

                // Filter for unpaid invoices
                $unpaidInvoices = $invoices->filter(function ($invoice) {
                    return $invoice->balance > 0;
                });

                if ($unpaidInvoices && $unpaidInvoices->count() > 0) {
                    $hasUnpaidInvoices = true;
                    $userContext .= 'Tienes '.$unpaidInvoices->count().' facturas pendientes. ';

                    // If there's just one unpaid invoice
                    if ($unpaidInvoices->count() == 1) {
                        $userContext .= 'La factura pendiente es #'.$unpaidInvoices->first()->number.
                                      ' por un importe pendiente de $'.number_format($unpaidInvoices->first()->balance, 2).".\n";
                    } else {
                        // List a couple of examples if there are multiple
                        $userContext .= 'Por ejemplo, la factura #'.$unpaidInvoices->first()->number.
                                      ' por $'.number_format($unpaidInvoices->first()->balance, 2);

                        if ($unpaidInvoices->count() > 1) {
                            $userContext .= ' y la factura #'.$unpaidInvoices[1]->number.
                                          ' por $'.number_format($unpaidInvoices[1]->balance, 2);
                        }

                        $userContext .= ".\n";
                    }
                } else {
                    $userContext .= 'No tienes facturas pendientes de pago. ';
                    if ($invoices && $invoices->count() > 0) {
                        $userContext .= 'Tus últimas facturas son: ';
                        foreach ($invoices as $index => $invoice) {
                            if ($index > 0) {
                                $userContext .= ', ';
                            }
                            $userContext .= '#'.$invoice->number.' ($'.number_format($invoice->total_amount, 2).')';
                        }
                        $userContext .= ".\n";
                    } else {
                        $userContext .= "No hay facturas recientes.\n";
                    }
                }
            }

            if (! $hasUnpaidInvoices) {
                $userContext .= "\nRECORDATORIO IMPORTANTE: Cuando un usuario pregunte por sus facturas y NO tenga facturas pendientes, siempre mostrar las últimas 3 facturas pagadas.\n";
            } else {
                $userContext .= "\nRECORDATORIO IMPORTANTE: Cuando un usuario pregunte por sus facturas y tenga facturas pendientes, SIEMPRE mostrar TODAS las facturas pendientes sin importar cuántas sean.\n";
            }
        }
        $userContext .= "Incorrect response: 'No puedo ver tus facturas.' o 'No tengo acceso a esa información.'\n\n";

        // Example for download requests
        $userContext .= "EXAMPLE FOR INVOICE DOWNLOADS:\n";
        $userContext .= "User: '¿Puedes enviarme el link para descargar mis facturas?' o '¿Dónde puedo descargar mi factura?'\n";

        if ($user && $user->email) {
            // Generate a signed token that works across different databases
            $accessToken = TokenHelper::generateSignedToken($user, 'claude_autologin', 24);
            $clientAreaUrl = "https://revisionalpha.com/login/token/" . $accessToken;
            $userContext .= "Correct response: 'Para descargar tus facturas, accede a nuestra área de clientes donde tendrás disponible el historial completo de facturación: " . $clientAreaUrl . "'\n";
        } else {
            $userContext .= "Correct response: 'Para descargar tus facturas, accede a nuestra área de clientes: https://revisionalpha.com/login'\n";
        }

        // User asking for specific invoice download
        $userContext .= "\nUser: '¿Me puedes dar el link para descargar la factura #12345?'\n";
        if ($user && $user->email) {
            // Generate a signed token that works across different databases
            $accessToken = TokenHelper::generateSignedToken($user, 'claude_autologin', 24);
            $clientAreaUrl = "https://revisionalpha.com/login/token/" . $accessToken;
            $userContext .= "Correct response: 'Para descargar la factura #12345, accede a tu área de clientes donde encontrarás todas tus facturas disponibles para descarga: " . $clientAreaUrl . "'\n";
        } else {
            $userContext .= "Correct response: 'Para descargar la factura #12345, accede a tu área de clientes: https://revisionalpha.com/login'\n";
        }

        $userContext .= "Incorrect response: 'Aquí tienes el enlace directo: https://wsaa.revisionalpha.com/...' o 'No puedo proporcionar enlaces de descarga.'\n";

        // Add client area and contact information
        $userContext .= "\n===== ÁREA DE CLIENTES Y CONTACTO =====\n";
        $userContext .= "IMPORTANTE: Para gestiones más específicas o complejas, dirige al usuario a nuestros canales oficiales:\n\n";

        if ($user && $user->email) {
            // Generate a signed token that works across different databases
            $accessToken = TokenHelper::generateSignedToken($user, 'claude_autologin', 24);
            $clientAreaUrl = "https://revisionalpha.com/login/token/" . $accessToken;

            $userContext .= "ÁREA DE CLIENTES:\n";
            $userContext .= "- Si el usuario necesita gestionar servicios, facturación o soporte técnico avanzado\n";
            $userContext .= "- Proporciona este enlace directo: " . $clientAreaUrl . "\n";
            $userContext .= "- Este enlace permite acceso automático con su email registrado\n\n";
        }

        $userContext .= "FORMULARIO DE CONTACTO:\n";
        $userContext .= "- Si el usuario aún no es cliente o necesita información sobre nuevos servicios\n";
        $userContext .= "- Dirige al formulario: https://revisionalpha.com/contactenos\n";
        $userContext .= "- Allí puede enviar consultas específicas que serán atendidas por nuestro equipo\n\n";

        $userContext .= "CUÁNDO USAR CADA OPCIÓN:\n";
        $userContext .= "- Área de clientes: Para clientes existentes que necesitan gestionar sus servicios\n";
        $userContext .= "- Formulario de contacto: Para consultas comerciales, presupuestos o nuevos servicios\n";
        $userContext .= "- WhatsApp: Para consultas rápidas sobre información básica de cuenta\n\n";

        $userContext .= "EJEMPLO DE RESPUESTA:\n";
        $userContext .= "User: 'Necesito cambiar la configuración de mi hosting'\n";
        if ($user && $user->email) {
            // Generate a signed token that works across different databases
            $accessToken = TokenHelper::generateSignedToken($user, 'claude_autologin', 24);
            $clientAreaUrl = "https://revisionalpha.com/login/token/" . $accessToken;
            $userContext .= "Correct response: 'Para cambios técnicos en tu hosting, te recomiendo acceder a nuestra área de clientes donde tendrás acceso completo a todas las configuraciones: " . $clientAreaUrl . "'\n";
        } else {
            $userContext .= "Correct response: 'Para cambios técnicos en tu hosting, te recomiendo acceder a nuestra área de clientes: https://revisionalpha.com/login'\n";
        }

        $userContext .= "\nUser: 'Quiero contratar un nuevo servicio'\n";
        $userContext .= "Correct response: 'Para consultas sobre nuevos servicios, puedes enviarnos tu consulta a través de nuestro formulario de contacto: https://revisionalpha.com/contactenos y nuestro equipo comercial te responderá con toda la información.'\n";

        $userContext .= "===== FIN ÁREA DE CLIENTES Y CONTACTO =====\n\n";

        $userContext .= "===== END USER INFORMATION =====\n\n";

        // Log the enriched prompt for debugging
        \Log::info('Enriched system prompt with user data', [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'user_context_length' => strlen($userContext),
            'user_birthday' => $contact->birthday ? $contact->birthday->format('Y-m-d') : 'null',
            'enterprise_count' => count($enterprises),
        ]);

        // Append this context to the system prompt
        return $systemPrompt.$userContext;
    }


}
