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

    'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),

    'api_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1'),

    'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),

    'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),

    'timeout' => env('ANTHROPIC_TIMEOUT', 30),

];
