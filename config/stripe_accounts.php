<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe credentials per product category
    |--------------------------------------------------------------------------
    |
    | Each category (mentoring, mailer, prospecting, hosting) can use a
    | different Stripe account. If secret/key are null, the default
    | config('cashier.secret') and config('cashier.key') are used.
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
