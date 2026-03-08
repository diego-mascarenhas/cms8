<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credit cost per position (seniority)
    |--------------------------------------------------------------------------
    | Each prospect position consumes a different number of credits when
    | importing. Keys must match search form values (owner, founder, etc.).
    */
    'credits_per_position' => [
        'owner' => 3,
        'founder' => 3,
        'c_suite' => 2,
        'vp' => 2,
        'head' => 2,
        'director' => 1,
        'manager' => 1,
        'senior' => 1,
        'entry' => 1,
        'intern' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default credit cost when position is unknown or not mapped
    |--------------------------------------------------------------------------
    */
    'default_credits' => 1,

    /*
    |--------------------------------------------------------------------------
    | Stripe Price IDs for monthly prospect plans (recurring)
    |--------------------------------------------------------------------------
    */
    'stripe_basic_price_id' => env('STRIPE_PROSPECTS_BASIC_PRICE_ID'),
    'stripe_growth_price_id' => env('STRIPE_PROSPECTS_GROWTH_PRICE_ID'),

    /*
    |--------------------------------------------------------------------------
    | One-time credit packs: Stripe Price ID => number of credits
    |--------------------------------------------------------------------------
    */
    'credit_packs' => [
        // env('PROSPECT_EXPORT_STRIPE_PRICE_ID') => 100, // example: add when product exists
    ],

];
