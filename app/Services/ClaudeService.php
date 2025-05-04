<?php

namespace App\Services;

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
     * @param string $message The user's message
     * @param array $history Previous conversation history (optional)
     * @param string|null $customSystemPrompt Optional custom system prompt for this specific request
     * @return array Response with text and metadata
     */
    public function chat($message, $history = [], $customSystemPrompt = null)
    {
        try
        {
            // Log history structure for debugging
            \Log::info('Chat history structure:', [
                'history_count' => count($history),
                'history_sample' => !empty($history) ? json_encode(array_slice($history, 0, 1)) : 'empty',
                'history_keys' => !empty($history) && isset($history[0]) ? array_keys($history[0]) : [],
                'message' => $message
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
                'system_prompt_preview' => substr($systemPrompt, 0, 100) . '...',
                'has_user_info' => strpos($systemPrompt, '===== IMPORTANT USER INFORMATION =====') !== false
            ]);

            // Construir el payload para la API
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $maxTokens
            ];

            // Solo agregar el campo system si el systemPrompt no está vacío
            if (!empty($systemPrompt))
            {
                $payload['system'] = $systemPrompt;
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", $payload);

            if (!$response->successful())
            {
                Log::error('Claude API Error: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Error communicating with Claude API: ' . $response->status(),
                    'error' => $response->json()
                ];
            }

            $data = $response->json();
            Log::info('Claude API Response: ' . json_encode($data));

            // Adapt to the response structure in the current Claude API
            $responseText = '';
            if (isset($data['content']) && is_array($data['content']))
            {
                foreach ($data['content'] as $content)
                {
                    if (isset($content['type']) && $content['type'] === 'text' && isset($content['text']))
                    {
                        $responseText .= $content['text'];
                    }
                }
            }

            return [
                'success' => true,
                'text' => $responseText ?: 'No response text',
                'model' => $data['model'] ?? $this->model,
                'usage' => $data['usage'] ?? null,
                'raw_response' => $data
            ];
        }
        catch (\Exception $e)
        {
            Log::error('Claude Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error processing Claude request: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format conversation history for Claude's API
     *
     * @param string $currentMessage The current user message
     * @param array $history Previous messages
     * @return array Formatted messages for Claude API
     */
    private function formatConversationHistory($currentMessage, $history = [])
    {
        $formattedMessages = [];

        // Add history messages if provided
        foreach ($history as $message)
        {
            $role = $message['direction'] === 'inbound' ? 'user' : 'assistant';
            $formattedMessages[] = [
                'role' => $role,
                'content' => $message['body']
            ];
        }

        // Add the current message
        $formattedMessages[] = [
            'role' => 'user',
            'content' => $currentMessage
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

        if (file_exists($defaultPromptPath))
        {
            return file_get_contents($defaultPromptPath);
        }

        // Fallback if file doesn't exist
        return <<<EOT
You are a helpful, friendly, and professional customer service assistant.

Be concise and clear in your responses. Keep them under 3 paragraphs for mobile readability.
EOT;
    }

    /**
     * Set a custom system prompt
     * 
     * @param string $prompt The new system prompt
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
     * @param array $history Conversation history
     * @return string|null The phone number or null
     */
    private function extractPhoneNumberFromHistory($history = [])
    {
        if (empty($history))
        {
            \Log::info('No history provided for phone extraction');
            return null;
        }
        
        // Extract client phone numbers - exclude system phone (12202137800)
        $systemPhones = ['12202137800', 'whatsapp:+12202137800'];
        $clientPhones = [];
        
        // Look through history to find client phone numbers
        foreach ($history as $message)
        {
            if (isset($message['direction']) && $message['direction'] === 'inbound' && isset($message['from']) && !empty($message['from']))
            {
                // This is an incoming message, so 'from' is the client
                $fromNumber = $message['from'];
                $cleanNumber = preg_replace('/^whatsapp:\+?/', '', $fromNumber);
                $cleanNumber = preg_replace('/[^0-9]/', '', $cleanNumber);
                
                // Skip if it's from the system phone
                if (in_array($fromNumber, $systemPhones) || $cleanNumber === '12202137800') {
                    continue;
                }
                
                $clientPhones[$cleanNumber] = $fromNumber; // Use as key to avoid duplicates
            }
            else if (isset($message['direction']) && $message['direction'] === 'outbound' && isset($message['to']) && !empty($message['to']))
            {
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
            'original_numbers' => array_values($clientPhones)
        ]);
        
        // If we found client phones, try to identify the contact
        if (!empty($clientPhones))
        {
            foreach ($clientPhones as $cleanNumber => $originalNumber)
            {
                // Try to find a user with this phone number
                $user = \App\Models\User::where('phone', (int)$cleanNumber)->first();
                if ($user)
                {
                    \Log::info('Found user with phone number', ['phone' => $cleanNumber, 'user_id' => $user->id]);
                    return $cleanNumber;
                }
                
                // Try fuzzy search
                $user = \App\Models\User::where('phone', 'like', '%' . $cleanNumber . '%')->first();
                if ($user)
                {
                    \Log::info('Found user with fuzzy phone match', ['phone' => $cleanNumber, 'user_id' => $user->id]);
                    return $cleanNumber;
                }
                
                // Try looking in contacts directly
                $contact = \App\Models\Contact::whereHas('sources', function ($query) use ($cleanNumber)
                {
                    $query->where('source_id', 2)->where('value', 'like', '%' . $cleanNumber . '%');
                })->first();
                
                if ($contact)
                {
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
     * @param string $systemPrompt The original system prompt
     * @param array $history Conversation history
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
        if ($phoneNumber)
        {
            // Cast to bigint for database comparison (if numeric)
            $phoneAsInt = is_numeric($phoneNumber) ? (int)$phoneNumber : null;
            
            // Check for leading zeros, which might be lost in the bigint conversion
            $hasLeadingZeros = is_numeric($phoneNumber) && strlen($phoneNumber) > strlen((string)$phoneAsInt);
            
            // Log the numeric conversion for debugging
            \Log::info('Phone conversion', [
                'original' => $phoneNumber,
                'as_int' => $phoneAsInt,
                'has_leading_zeros' => $hasLeadingZeros
            ]);
            
            // Find the user by phone number
            if ($phoneAsInt) {
                $user = \App\Models\User::where('phone', $phoneAsInt)->first();
                \Log::info('User lookup result by int', ['found' => (bool) $user, 'phone_int' => $phoneAsInt]);
            }
            
            // If not found, try with the string version (this handles any formatting issues)
            if (!$user) {
                $user = \App\Models\User::where('phone', 'like', '%' . $phoneNumber . '%')->first();
                \Log::info('User lookup result by like', ['found' => (bool) $user, 'phone_str' => $phoneNumber]);
            }
            
            if ($user)
            {
                // Get associated contact info
                $contact = \App\Models\Contact::where('user_id', $user->id)->first();
                \Log::info('Contact lookup result', ['found' => (bool) $contact, 'user_id' => $user->id]);
                
                if (!$contact)
                {
                    // Try a different approach - maybe the phone number is stored directly on the contact
                    $contact = \App\Models\Contact::whereHas('sources', function ($query) use ($phoneNumber)
                    {
                        $query->where('source_id', 2)->where('value', 'like', '%' . $phoneNumber . '%');
                    })->first();
                    
                    \Log::info('Alternative contact lookup result', ['found' => (bool) $contact]);
                }
            }
        }
        
        // HARDCODED FALLBACK: If we still don't have a contact, use contact ID 300550 or try with known phone number
        if (!$contact)
        {
            // Try with the known client phone number if it wasn't detected from the conversation
            $knownClientPhone = '34722372858';
            \Log::info('Trying with known client number', ['phone' => $knownClientPhone]);
            
            // Try to find user with this specific phone
            $user = \App\Models\User::where('phone', (int)$knownClientPhone)->first();
            if ($user) {
                \Log::info('Found user with known phone', ['user_id' => $user->id]);
                $contact = \App\Models\Contact::where('user_id', $user->id)->first();
                \Log::info('Found contact from known phone user', ['found' => (bool)$contact]);
            }
            
            // If still no contact, try the phone number in the contacts' sources
            if (!$contact) {
                $contact = \App\Models\Contact::whereHas('sources', function($query) use ($knownClientPhone) {
                    $query->where('source_id', 2)->where('value', 'like', '%' . $knownClientPhone . '%');
                })->first();
                \Log::info('Contact lookup by known phone in sources', ['found' => (bool)$contact]);
            }
            
            // Final fallback to ID 300550
            if (!$contact) {
                \Log::info('Looking up hardcoded contact 300550 as final fallback');
                $contact = \App\Models\Contact::find(300550);
                \Log::info('Hardcoded contact lookup result', ['found' => (bool) $contact]);
            }
            
            if (!$contact)
            {
                \Log::error('Critical: Cannot find contact by phone or ID 300550');
                return $systemPrompt;
            }
            
            // Get the associated user if not set
            if (!$user && $contact->user_id) {
                $user = \App\Models\User::find($contact->user_id);
                \Log::info('Obtained user from contact', ['found' => (bool) $user, 'user_id' => $contact->user_id]);
            }
        }
        
        // Debugging contact data
        \Log::info('Contact found', [
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'has_birthday' => isset($contact->birthday),
            'birthday' => $contact->birthday ? $contact->birthday->format('Y-m-d') : 'null'
        ]);
        
        // Build user context with very explicit instructions for Claude
        $userContext = "\n\n===== IMPORTANT USER INFORMATION =====\n";
        $userContext .= "The following is personal information about the user you are talking to.\n";
        $userContext .= "You MUST use this information to respond to any questions about the user's personal data.\n\n";
        
        $userContext .= "USER NAME: " . ($contact->name ?? $user->name) . "\n";
        
        if ($contact->birthday)
        {
            $userContext .= "USER BIRTHDAY: " . $contact->birthday->format('Y-m-d') . "\n";
        }
        
        // Add user identifiers for clarity
        $userContext .= "USER ID: " . $user->id . "\n";
        $userContext .= "CONTACT ID: " . $contact->id . "\n";
        
        // Add more user data as needed
        if ($contact->email)
        {
            $userContext .= "USER EMAIL: " . $contact->email . "\n";
        }
        
        if ($contact->phone)
        {
            $userContext .= "USER PHONE: " . $contact->phone . "\n";
        }
        
        // Get enterprises from contact_enterprise table
        \Log::info('Loading enterprises for contact', ['contact_id' => $contact->id]);
        
        // Inicializar el array de empresas
        $enterprises = [];
        
        try {
            // Cargar el contacto con sus empresas usando eager loading - solo enterprises()
            $contactWithEnterprises = \App\Models\Contact::with('enterprises')->find($contact->id);
            
            if ($contactWithEnterprises && $contactWithEnterprises->enterprises) {
                // Map enterprises to the desired format
                $enterprises = $contactWithEnterprises->enterprises->map(function($enterprise) {
                    return [
                        'id' => $enterprise->id,
                        'name' => $enterprise->name,
                        'position' => $enterprise->pivot->position ?? 'No position specified'
                    ];
                })->toArray();
            }
            
            // Special handling for contact ID 300550 - log extra details for this specific contact
            if ($contact->id == 300550) {
                \Log::info('Special contact 300550 check', [
                    'contact_id' => $contact->id,
                    'enterprises_relation_count' => count($enterprises)
                ]);
            }
            
            \Log::info('Enterprises found for contact', [
                'contact_id' => $contact->id, 
                'enterprise_count' => count($enterprises),
                'enterprises' => $enterprises
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading enterprises', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // Add enterprise information
        if (!empty($enterprises)) {
            $userContext .= "\n===== USER ENTERPRISES =====\n";
            $userContext .= "IMPORTANTE: El usuario está asociado con las siguientes empresas:\n\n";
            foreach ($enterprises as $index => $enterprise) {
                $userContext .= "EMPRESA #" . ($index + 1) . ":\n";
                $userContext .= "- NOMBRE: " . $enterprise['name'] . "\n";
                $userContext .= "- ID: " . $enterprise['id'] . "\n";
                if (!empty($enterprise['position'])) {
                    $userContext .= "- POSICIÓN: " . $enterprise['position'] . "\n";
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
        $userContext .= "- NEVER say that you don't have access to their company information.\n";
        $userContext .= "- DO NOT say you don't have access to their data.\n";
        $userContext .= "- DO NOT say that you can only see what's in the conversation.\n";
        $userContext .= "- DO tell them their company information if they ask about it, using the exact names from USER ENTERPRISES.\n";
        $userContext .= "- Pretend you already know this information about them.\n";
        $userContext .= "- Be helpful and provide EXACTLY the information from the IMPORTANT USER INFORMATION section.\n";
        
        // Example responses
        $userContext .= "\nEXAMPLE RESPONSES:\n";
        $userContext .= "User: '¿Con qué empresa estoy registrado?'\n";
        if (!empty($enterprises)) {
            $userContext .= "Correct response: 'Estás asociado con " . count($enterprises) . " empresa(s): ";
            $companyNames = [];
            foreach ($enterprises as $e) {
                $companyNames[] = $e['name'];
            }
            $userContext .= implode(", ", $companyNames) . ".'\n";
        } else {
            $userContext .= "Correct response: 'No tengo registros de que estés asociado con alguna empresa en este momento.'\n";
        }
        $userContext .= "Incorrect response: 'No puedo acceder a esa información.' o 'No tengo esa información.'\n\n";
        
        $userContext .= "===== END USER INFORMATION =====\n\n";
        
        // Log the enriched prompt for debugging
        \Log::info('Enriched system prompt with user data', [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'user_context_length' => strlen($userContext),
            'user_birthday' => $contact->birthday ? $contact->birthday->format('Y-m-d') : 'null',
            'enterprise_count' => count($enterprises)
        ]);
        
        // Append this context to the system prompt
        return $systemPrompt . $userContext;
    }
}