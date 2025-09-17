<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Provider
    |--------------------------------------------------------------------------
    |
    | The email provider to use for sending campaigns. Options:
    | smtp, mailgun, sendgrid, mailbaby
    |
    */

    'email_provider' => env('EMAILER_PROVIDER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Fallback to SMTP
    |--------------------------------------------------------------------------
    |
    | Whether to fallback to SMTP if the selected provider fails.
    |
    */

    'fallback_to_smtp' => env('EMAILER_FALLBACK_TO_SMTP', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue name for processing email campaigns.
    |
    */

    'queue_name' => env('EMAILER_QUEUE', 'emailer'),

    /*
    |--------------------------------------------------------------------------
    | Email Delays Configuration
    |--------------------------------------------------------------------------
    |
    | Configure delays between emails to avoid being flagged as spam.
    |
    */

    'delays' => [
        'base_minutes' => env('EMAILER_DELAY_BASE_MINUTES', 5),
        'random_seconds' => env('EMAILER_DELAY_RANDOM_SECONDS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Email Settings
    |--------------------------------------------------------------------------
    |
    | Default from address and name when team settings are not available.
    |
    */

    'from_address' => env('EMAILER_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@example.com')),
    'from_name' => env('EMAILER_FROM_NAME', env('MAIL_FROM_NAME', 'Emailer')),
    'reply_to_address' => env('EMAILER_REPLY_TO_ADDRESS'),
    'reply_to_name' => env('EMAILER_REPLY_TO_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the models used by the consuming application.
    | These should point to your app's models that the emailer will interact with.
    |
    */

    'models' => [
        'team' => env('EMAILER_TEAM_MODEL', 'App\Models\Team'),
        'contact' => env('EMAILER_CONTACT_MODEL', 'App\Models\Contact'),
        'category' => env('EMAILER_CATEGORY_MODEL', 'App\Models\Category'),
        'template' => env('EMAILER_TEMPLATE_MODEL', 'App\Models\Template'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Team Model Configuration
    |--------------------------------------------------------------------------
    |
    | Legacy compatibility settings for model references.
    |
    */

    'team_model' => env('EMAILER_TEAM_MODEL', 'App\Models\Team'),
    'contact_model' => env('EMAILER_CONTACT_MODEL', 'App\Models\Contact'),
    'category_model' => env('EMAILER_CATEGORY_MODEL', 'App\Models\Category'),
    'template_model' => env('EMAILER_TEMPLATE_MODEL', 'App\Models\Template'),

    /*
    |--------------------------------------------------------------------------
    | Advertising Footer
    |--------------------------------------------------------------------------
    |
    | Footer to add to emails when using system SMTP (not team SMTP).
    |
    */

    'advertising_footer' => env('EMAILER_ADVERTISING_FOOTER', ''),

    /*
    |--------------------------------------------------------------------------
    | Default CSS
    |--------------------------------------------------------------------------
    |
    | Default CSS to inline in email templates.
    |
    */

    'default_css' => env('EMAILER_DEFAULT_CSS', ''),

    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for email tracking (opens, clicks).
    |
    */

    'tracking' => [
        'enabled' => env('EMAILER_TRACKING_ENABLED', true),
        'open_tracking' => env('EMAILER_OPEN_TRACKING', true),
        'click_tracking' => env('EMAILER_CLICK_TRACKING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for different email service providers.
    |
    */

    'providers' => [
        'mailgun' => [
            'enabled' => env('EMAILER_MAILGUN_ENABLED', env('MAILGUN_SECRET') ? true : false),
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        ],

        'sendgrid' => [
            'enabled' => env('EMAILER_SENDGRID_ENABLED', env('SENDGRID_API_KEY') ? true : false),
            'api_key' => env('SENDGRID_API_KEY'),
        ],

        'mailbaby' => [
            'enabled' => env('EMAILER_MAILBABY_ENABLED', env('MAILBABY_API_KEY') ? true : false),
            'api_key' => env('MAILBABY_API_KEY'),
            'api_url' => env('MAILBABY_API_URL', 'https://api.mailbaby.net'),
        ],

        'smtp' => [
            'enabled' => true, // SMTP is always available as fallback
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Table prefix for emailer tables.
    |
    */

    'table_prefix' => env('EMAILER_TABLE_PREFIX', 'emailer_'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for package routes (tracking, webhooks).
    |
    */

    'routes' => [
        'prefix' => env('EMAILER_ROUTE_PREFIX', 'emailer'),
        'middleware' => ['web'], // Add authentication middleware as needed
        'tracking_middleware' => [], // Specific middleware for tracking routes
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for optimizing performance.
    |
    */

    'performance' => [
        'chunk_size' => env('EMAILER_CHUNK_SIZE', 100), // Process deliveries in chunks
        'max_retries' => env('EMAILER_MAX_RETRIES', 3),
        'timeout' => env('EMAILER_TIMEOUT', 120), // Job timeout in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific package features.
    |
    */

    'features' => [
        'analytics' => env('EMAILER_ANALYTICS', true),
        'webhooks' => env('EMAILER_WEBHOOKS', true),
        'api_endpoints' => env('EMAILER_API_ENDPOINTS', false),
    ],

];
