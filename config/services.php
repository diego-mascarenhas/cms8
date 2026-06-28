<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    'mailbaby' => [
        'api_key' => env('MAILBABY_API_KEY'),
        'api_url' => env('MAILBABY_API_URL', 'https://api.mailbaby.net'),
        'webhook_secret' => env('MAILBABY_WEBHOOK_SECRET'),
        'enabled' => env('MAILBABY_ENABLED', false),
    ],

    'email' => [
        'provider' => env('EMAIL_PROVIDER', 'smtp'), // mailbaby | smtp
        'fallback_to_smtp' => env('EMAIL_FALLBACK_TO_SMTP', true),

        // Email sending delay configuration
        'delay' => [
            'base_minutes' => (int) env('EMAIL_DELAY_BASE_MINUTES', 1), // Minutes between each email
            'random_seconds' => (int) env('EMAIL_DELAY_RANDOM_SECONDS', 60), // Random 0-X seconds added
        ],

        // Campaign processing limits (~20 emails/minute configuration)
        'processing' => [
            'deliveries_per_campaign_run' => (int) env('EMAIL_DELIVERIES_PER_CAMPAIGN_RUN', 30), // Max deliveries created per campaign per run (every 5 minutes)
            'deliveries_per_send_run' => (int) env('EMAIL_DELIVERIES_PER_SEND_RUN', 20), // Max deliveries sent per run (every 1 minute = ~20 emails/minute)
        ],
    ],

    'currencyfreaks' => [
        'api_key' => env('CURRENCYFREAKS_API_KEY'),
        'base_currency' => env('CURRENCYFREAKS_BASE_CURRENCY', 'USD'),
        'target_currencies' => env('CURRENCYFREAKS_TARGET_CURRENCIES', 'ARS,EUR'),
    ],

    'bcra' => [
        'timeout_seconds' => (int) env('BCRA_API_TIMEOUT_SECONDS', 45),
    ],

    'frankfurter' => [
        'base_url' => env('FRANKFURTER_BASE_URL', 'https://api.frankfurter.dev'),
        'timeout_seconds' => (int) env('FRANKFURTER_TIMEOUT_SECONDS', 45),
    ],

    'mcp' => [
        'enabled' => env('MCP_ENABLED', false),
        'endpoint' => env('MCP_ENDPOINT', 'http://localhost:3000/mcp'),
    ],

    'woocommerce' => [
        'url' => env('WOOCOMMERCE_URL'),
        'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY'),
        'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET'),
    ],

    'google' => [
        'places_api_key' => env('GOOGLE_PLACES_API_KEY', ''),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // OAuth callback: {APP_URL}/integrations/google/callback, or full URI via GOOGLE_OAUTH_REDIRECT_URI.
        'redirect' => env(
            'GOOGLE_OAUTH_REDIRECT_URI',
            rtrim((string) config('app.url'), '/').'/integrations/google/callback',
        ),
        'oauth_scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_OAUTH_SCOPES', 'openid,email,profile,https://www.googleapis.com/auth/contacts,https://www.googleapis.com/auth/calendar.events'))))),
    ],

    'webdav' => [
        'base_url' => env('WEBDAV_BASE_URL', 'https://webdav.test'),
        'api_token' => env('WEBDAV_API_TOKEN'),
    ],

    'apollo' => [
        'api_key' => env('APOLLO_API_KEY', ''),
    ],

    // Same env names as Mobile: TEAM_TOKEN, API_BASE_URL, LANDING_PROMPT_NAME
    'landing_widget' => [
        'api_url' => env('API_BASE_URL', env('APP_URL', 'https://humano.test')),
        'team_token' => env('TEAM_TOKEN', ''),
        'prompt_name' => env('LANDING_PROMPT_NAME', 'landing'),
        'success_url' => env('LANDING_SUCCESS_URL', ''),
    ],

    'prospect_search' => [
        'team_id' => env('PROSPECT_SEARCH_TEAM_ID'),
        // URL of the Prospection frontend (React app). The email link must point here so users land on the frontend.
        'access_base_url' => env('PROSPECT_ACCESS_BASE_URL'),
        // Stripe Price ID for the export product (one-time payment). Used in subscription page and by the Prospection frontend via API.
        'export_price_id' => env('PROSPECT_EXPORT_STRIPE_PRICE_ID'),
        'export_name' => env('PROSPECT_EXPORT_NAME', 'Prospection'),
        'export_description' => env('PROSPECT_EXPORT_DESCRIPTION', 'Crédito para la búsqueda de prospectos para que puedas transformarlos en clientes.'),
        'export_credits' => (int) env('PROSPECT_EXPORT_CREDITS', 100),
    ],

];
