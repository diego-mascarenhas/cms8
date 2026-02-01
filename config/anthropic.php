<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Anthropic Claude AI API integration.
    | You can get your API key from https://console.anthropic.com/
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => (function () {
        $m = env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929');
        // Map old/invalid model names to valid ones
        $modelMap = [
            'claude-3-5-sonnet-20241022' => 'claude-sonnet-4-5-20250929',
            'claude-3-5-sonnet-latest' => 'claude-sonnet-4-5-20250929',
        ];
        return $modelMap[$m] ?? $m;
    })(),

    'api_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),

    'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),

    'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),

    'timeout' => env('ANTHROPIC_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | System prompt (optional)
    |--------------------------------------------------------------------------
    |
    | Custom system prompt for the assistant. If empty, default from
    | storage/app/claude_prompts/default.txt is used.
    |
    */
    'system_prompt' => env('ANTHROPIC_SYSTEM_PROMPT'),

];
