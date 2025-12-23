<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Providers
    |--------------------------------------------------------------------------
    |
    | Configure the available email providers for the mailer system
    |
    */
    'providers' => [
        'smtp' => [
            'name' => 'SMTP',
            'enabled' => true,
        ],
        'api' => [
            'name' => 'Email API',
            'enabled' => !empty(env('MAIL_API_KEY')),
            'key' => env('MAIL_API_KEY'),
            'domain' => env('MAIL_API_DOMAIN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for email campaigns
    |
    */
    'campaigns' => [
        'default_min_hours_between_emails' => 48,
        'max_retries' => 3,
        'batch_size' => 100,
        'rate_limit_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    */
    'processing' => [
        'deliveries_per_send_run' => env('EMAIL_DELIVERIES_PER_SEND_RUN', 100),
        'deliveries_per_campaign_run' => env('EMAIL_DELIVERIES_PER_CAMPAIGN_RUN', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delay Configuration
    |--------------------------------------------------------------------------
    */
    'delays' => [
        'base_minutes' => env('EMAIL_DELAY_BASE_MINUTES', 1),
        'random_seconds' => env('EMAIL_DELAY_RANDOM_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    */
    'models' => [
        'contact' => env('HUMANO_MAILER_CONTACT_MODEL', \App\Models\Contact::class),
        'team' => env('HUMANO_MAILER_TEAM_MODEL', \App\Models\Team::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailable Configuration
    |--------------------------------------------------------------------------
    */
    'mailables' => [
        'message_delivery_mail' => env('HUMANO_MAILER_MESSAGE_DELIVERY_MAIL', \App\Mail\MessageDeliveryMail::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    */
    'fallback_to_smtp' => env('EMAIL_FALLBACK_TO_SMTP', true),

    /*
    |--------------------------------------------------------------------------
    | Tracking Settings
    |--------------------------------------------------------------------------
    |
    | Configure email tracking features
    |
    */
    'tracking' => [
        'open_tracking' => true,
        'click_tracking' => true,
        'unsubscribe_tracking' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Domains
    |--------------------------------------------------------------------------
    |
    | Email domains to exclude from campaigns (test/demo addresses)
    |
    */
    'excluded_domains' => [
        '@example.org',
        '@example.net',
        '@example.com',
        '@demo.com',
        '@test.com',
        '@localhost',
        '@testing.com',
        '@dummy.com',
        '@fake.com',
    ],
];
