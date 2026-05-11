<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public pricing (Stripe Payment Links)
    |--------------------------------------------------------------------------
    |
    | Staging defaults match Humano Stripe test mode. Override with .env on
    | production when you are ready to go live.
    |
    | signup_completion (default: payment_link)
    |   payment_link — Default circuit: after Stripe Payment Link checkout, redirect
    |     buyers to route('pricing.checkout.complete') with
    |     ?session_id={CHECKOUT_SESSION_ID} (and optional &category=assistant|business). User
    |     and team are ensured, then the user is logged in. Set the same URL in the
    |     Stripe Payment Link "After payment" redirect field.
    |   register_first — Opt-in legacy: send visitors to /register before paying
    |     (set HUMANO_PRICING_SIGNUP_COMPLETION=register_first only if you need this).
    | Any other or empty env value resolves to payment_link.
    |
    | Stripe for this flow: Payment Links for Humano plans always use the platform account
    | (Cashier / STRIPE_* in .env). Team “own Stripe” settings apply to other products only.
    |
    */

    'signup_completion' => strtolower(trim((string) env('HUMANO_PRICING_SIGNUP_COMPLETION', 'payment_link'))) === 'register_first'
        ? 'register_first'
        : 'payment_link',

    /*
    | Default plan slug when checkout return URL omits &category= (assistant or business).
    */
    'post_checkout_plan_slug' => match (strtolower(trim((string) env('HUMANO_PRICING_POST_CHECKOUT_PLAN_SLUG', 'assistant'))))
    {
        'business' => 'business',
        default => 'assistant',
    },

    /*
    | Referral / friend promotion code label (e.g. for copy in UI or translations).
    | Not appended to Payment Link URLs — users enter it in Stripe checkout if they have it.
    */
    'coupon_code' => env('HUMANO_PRICING_COUPON_CODE', 'SOYAMIGO'),

    /*
    |--------------------------------------------------------------------------
    | Team modules after checkout (by plan id: assistant, business, foundation)
    |--------------------------------------------------------------------------
    |
    | Matched via stripe_product_id on the subscription vs plans below.
    | Each plan lists every module key to enable (business repeats assistant + extras).
    | foundation checkout still uses the business list (see TeamModulesByPricingPlanSyncer).
    | Keys must match modules.key (see ModuleSeeder). settings + subscriptions keep
    | team settings and Stripe billing usable after paid signup.
    |
    */

    'plan_team_modules' => [
        'assistant' => [
            'settings',
            'subscriptions',
            'dashboard',
            'calendar',
            'clients',
            'contacts',
            'tasks',
            'prospecting',
            'prompts',
            'campaigns',
            'mailer',
            'landings',
            'chat',
        ],
        'business' => [
            'settings',
            'subscriptions',
            'dashboard',
            'calendar',
            'clients',
            'contacts',
            'tasks',
            'prospecting',
            'prompts',
            'campaigns',
            'mailer',
            'landings',
            'chat',
            'funnel',
            'invoices',
            'payments',
            'financial',
        ],
    ],

    'plans' => [
        [
            'id' => 'assistant',
            'checkout_url' => env(
                'HUMANO_PRICING_ASSISTANT_CHECKOUT_URL',
                'https://buy.stripe.com/3cIeVd98VabI07cgPb43S03',
            ),
            'stripe_product_id' => env('HUMANO_PRICING_ASSISTANT_STRIPE_PRODUCT_ID', 'prod_UUoDnxftlyItz0'),
            'stripe_price_monthly_id' => env('HUMANO_PRICING_ASSISTANT_PRICE_MONTHLY_ID', 'price_1TVoawGelYN536DrEH4gIAsR'),
            'stripe_price_yearly_id' => env('HUMANO_PRICING_ASSISTANT_PRICE_YEARLY_ID', 'price_1TVod6GelYN536DrtCsqOG6d'),
            'monthly_amount' => '99',
            'yearly_amount' => '990',
            'popular' => false,
        ],
        [
            'id' => 'business',
            // Stripe Price IDs: monthly €299, yearly €2990 (Humano.app Business)
            'checkout_url' => env(
                'HUMANO_PRICING_BUSINESS_CHECKOUT_URL',
                'https://buy.stripe.com/6oU14nfxjabIbPUbuR43S04',
            ),
            'stripe_product_id' => env('HUMANO_PRICING_BUSINESS_STRIPE_PRODUCT_ID', 'prod_UUoHz602tHBY8b'),
            'stripe_price_monthly_id' => env('HUMANO_PRICING_BUSINESS_PRICE_MONTHLY_ID', 'price_1TVoebGelYN536DrLAOm6k90'),
            'stripe_price_yearly_id' => env('HUMANO_PRICING_BUSINESS_PRICE_YEARLY_ID', 'price_1TVof6GelYN536DrAaThyVzr'),
            'monthly_amount' => '299',
            'yearly_amount' => '2990',
            'popular' => true,
        ],
        [
            'id' => 'foundation',
            'checkout_url' => env(
                'HUMANO_PRICING_FOUNDATION_CHECKOUT_URL',
                'https://buy.stripe.com/4gM4gz3OB0B82fkcyV43S05',
            ),
            'stripe_product_id' => env('HUMANO_PRICING_FOUNDATION_STRIPE_PRODUCT_ID', 'prod_UUoIeGCxj2MfcL'),
            'stripe_price_monthly_id' => env('HUMANO_PRICING_FOUNDATION_PRICE_MONTHLY_ID', 'price_1TVofaGelYN536DrGEL9txGS'),
            'stripe_price_yearly_id' => env('HUMANO_PRICING_FOUNDATION_PRICE_YEARLY_ID', 'price_1TVog3GelYN536DryyMGQ0rE'),
            'monthly_amount' => '999',
            'yearly_amount' => '9990',
            'popular' => false,
        ],
    ],

];
