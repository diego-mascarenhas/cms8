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
        $this->maxTokens = (int)config('services.claude.max_tokens', 1000);
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
            
            // Ensure max_tokens is a valid integer
            $maxTokens = (int)$this->maxTokens;
            
            // Log the request for debugging
            \Log::info('Claude API Request:', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'message_count' => count($messages)
            ]);
            
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'model' => $this->model,
                'system' => $systemPrompt,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
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
            Log::info('Claude API Response: ' . json_encode($data));
            
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
} 