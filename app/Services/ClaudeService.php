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
                'message_count' => count($messages)
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
        
        // Look through history for the "from" field which should have the phone number
        foreach ($history as $message)
        {
            if (isset($message['from']) && !empty($message['from']))
            {
                // Clean the phone number (remove whatsapp: prefix if present)
                $originalPhone = $message['from'];
                $phoneNumber = preg_replace('/^whatsapp:\+?/', '', $originalPhone);
                
                // Remove any non-numeric characters
                $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
                
                \Log::info('Extracted phone number', [
                    'original' => $originalPhone, 
                    'cleaned' => $phoneNumber,
                    'as_bigint' => (is_numeric($phoneNumber) ? (int)$phoneNumber : 'not numeric')
                ]);
                
                return $phoneNumber;
            }
        }
        
        \Log::info('No phone number found in history', ['history_keys' => array_keys($history[0] ?? [])]);
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
        
        if (!$phoneNumber)
        {
            return $systemPrompt;
        }
        
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
        $user = null;
        
        if ($phoneAsInt) {
            $user = \App\Models\User::where('phone', $phoneAsInt)->first();
            \Log::info('User lookup result by int', ['found' => (bool) $user, 'phone_int' => $phoneAsInt]);
        }
        
        // If not found, try with the string version (this handles any formatting issues)
        if (!$user) {
            $user = \App\Models\User::where('phone', 'like', '%' . $phoneNumber . '%')->first();
            \Log::info('User lookup result by like', ['found' => (bool) $user, 'phone_str' => $phoneNumber]);
        }
        
        if (!$user)
        {
            return $systemPrompt;
        }
        
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
            
            if (!$contact)
            {
                return $systemPrompt;
            }
        }
        
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
        
        // Add enterprise info if available
        if ($contact->enterprises && $contact->enterprises->count() > 0)
        {
            $userContext .= "USER ENTERPRISES: " . $contact->enterprises->pluck('name')->implode(', ') . "\n";
        }
        
        // Add instructions for how to use this data
        $userContext .= "\nINSTRUCTIONS FOR USING THIS DATA:\n";
        $userContext .= "- When the user asks about their personal information, use the data above to answer them.\n";
        $userContext .= "- If they ask 'What's my birthday?' respond with their birthday from the USER BIRTHDAY field.\n";
        $userContext .= "- DO NOT say you don't have access to their data.\n";
        $userContext .= "- DO NOT say that you can only see what's in the conversation.\n";
        $userContext .= "- Pretend you already know this information about them.\n";
        $userContext .= "- Be helpful and provide EXACTLY the information from the IMPORTANT USER INFORMATION section.\n";
        $userContext .= "===== END USER INFORMATION =====\n\n";
        
        // Log the enriched prompt for debugging
        \Log::info('Enriched system prompt with user data', [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'user_context_length' => strlen($userContext),
            'user_birthday' => $contact->birthday ? $contact->birthday->format('Y-m-d') : 'null'
        ]);
        
        // Append this context to the system prompt
        return $systemPrompt . $userContext;
    }
}