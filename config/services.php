<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_SMS_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'default_template' => env('TWILIO_DEFAULT_TEMPLATE', 'customer_support'),
    ],

    'notifications' => [
        'email' => env('NOTIFICATION_EMAIL'),
    ],

    'ovh' => [
        'endpoint' => env('OVH_API_ENDPOINT', 'https://eu.api.ovh.com/1.0'),
        'app_key' => env('OVH_APP_KEY'),
        'app_secret' => env('OVH_APP_SECRET'),
        'consumer_key' => env('OVH_CONSUMER_KEY'),
        'enterprise_id' => env('OVH_ENTERPRISE_ID', 1),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022'),
        'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
        'max_tokens' => (int) env('CLAUDE_MAX_TOKENS', 1000),
        'auto_respond' => env('CLAUDE_AUTO_RESPOND', false),
        'system_prompt' => env('CLAUDE_SYSTEM_PROMPT'),
    ],

];
