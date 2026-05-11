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
    | signup_completion:
    |   payment_link — After Stripe Payment Link checkout, redirect buyers to
    |     route('pricing.checkout.complete') with ?session_id={CHECKOUT_SESSION_ID}
    |     (and optional &category=mailer). A user is created or matched by email,
    |     then logged in. Configure the same URL in the Stripe Payment Link
    |     "After payment" redirect field.
    |   register_first — Visitors are told to create an account at /register
    |     before paying (legacy flow).
    |
    */

    'signup_completion' => strtolower((string) env('HUMANO_PRICING_SIGNUP_COMPLETION', 'payment_link')),

    'post_checkout_stripe_category' => strtolower((string) env('HUMANO_PRICING_POST_CHECKOUT_CATEGORY', 'mailer')),

    'coupon_code' => env('HUMANO_PRICING_COUPON_CODE', 'SOYAMIGO'),

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
