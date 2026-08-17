<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy per-category Stripe credentials
    |--------------------------------------------------------------------------
    |
    | All products now use the default Cashier account (STRIPE_KEY,
    | STRIPE_SECRET, STRIPE_WEBHOOK_SECRET). These entries are kept so
    | existing .env keys do not break config load; StripeAccountResolver
    | ignores them.
    |
    */

    'mentoring' => [
        'secret' => env('STRIPE_MENTORING_SECRET'),
        'key' => env('STRIPE_MENTORING_KEY'),
        'webhook_secret' => env('STRIPE_MENTORING_WEBHOOK_SECRET'),
    ],

    'mailer' => [
        'secret' => env('STRIPE_MAILER_SECRET'),
        'key' => env('STRIPE_MAILER_KEY'),
        'webhook_secret' => env('STRIPE_MAILER_WEBHOOK_SECRET'),
    ],

    'prospecting' => [
        'secret' => env('STRIPE_PROSPECTING_SECRET'),
        'key' => env('STRIPE_PROSPECTING_KEY'),
        'webhook_secret' => env('STRIPE_PROSPECTING_WEBHOOK_SECRET'),
    ],

    'hosting' => [
        'secret' => env('STRIPE_HOSTING_SECRET'),
        'key' => env('STRIPE_HOSTING_KEY'),
        'webhook_secret' => env('STRIPE_HOSTING_WEBHOOK_SECRET'),
    ],

    'support' => [
        'secret' => env('STRIPE_HOSTING_SECRET'),
        'key' => env('STRIPE_HOSTING_KEY'),
        'webhook_secret' => env('STRIPE_HOSTING_WEBHOOK_SECRET'),
    ],

];
