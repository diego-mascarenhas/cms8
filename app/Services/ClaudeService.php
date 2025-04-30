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
        $this->model = config('services.claude.model', 'claude-3-opus-20240229');
        $this->baseUrl = config('services.claude.base_url', 'https://api.anthropic.com/v1');
        $this->maxTokens = config('services.claude.max_tokens', 1000);
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
        try {
            // Format conversation history for Claude's messages format
            $messages = $this->formatConversationHistory($message, $history);
            
            // Use custom system prompt if provided, otherwise use default
            $systemPrompt = $customSystemPrompt ?? $this->systemPrompt;
            
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'model' => $this->model,
                'system' => $systemPrompt,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ]);

            if (!$response->successful()) {
                Log::error('Claude API Error: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Error communicating with Claude API: ' . $response->status(),
                    'error' => $response->json()
                ];
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'text' => $data['content'][0]['text'] ?? 'No response text',
                'model' => $data['model'] ?? $this->model,
                'usage' => $data['usage'] ?? null,
                'raw_response' => $data
            ];
        } catch (\Exception $e) {
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
        foreach ($history as $message) {
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
        return <<<EOT
You are a helpful, friendly, and professional customer service AI assistant for a company.

Guidelines:
1. Be concise and direct in your responses.
2. Maintain a friendly, professional tone.
3. If you don't know the answer to something, be honest about it.
4. Never share sensitive information like personal data or internal company details.
5. Responses should be helpful and informative.
6. Adapt your tone to match the customer's emotional state.
7. Focus on solving the customer's problem efficiently.
8. Keep responses under 4 paragraphs unless more detail is necessary.

For WhatsApp conversations, keep your responses even more concise since they're being read on mobile devices.
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
} 