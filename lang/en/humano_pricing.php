<?php

return [
    'page_title' => 'Pricing — Humano',
    'hero_title' => 'Plans and pricing',
    'hero_subtitle' => 'Pick a plan and subscribe securely with Stripe. Switch between monthly and annual billing before you check out.',
    'billing_monthly' => 'Monthly',
    'billing_annual' => 'Annual',
    'annual_discount_badge' => '30% less',
    'save_hint' => 'Annual plans are billed once per year.',
    'per_month_suffix' => '/month',
    'per_year_suffix' => '/year',
    'billed_annually' => 'Billed once per year.',
    'billed_monthly' => 'Billed every month.',
    'prices_plus_vat' => '+ VAT',
    'subscribe' => 'Subscribe',
    'coupon_title' => 'Have a referral code?',
    'coupon_body' => 'Use code :code at checkout when the promotion field appears and get 50% off!!!',
    'coupon_copy' => 'Copy code',
    'coupon_copied' => 'Copied',
    'staging_note' => 'Staging prices — production links can be swapped via environment variables.',
    'most_popular' => 'Most popular',

    'plans' => [
        'assistant' => [
            'name' => 'Humano.app Assistant',
            'description' => 'Let AI help you automate routine work like WhatsApp scheduling, clients, and tasks while keeping your brand voice.',
            'features' => [
                'WhatsApp-first assistant aligned with your tone of voice',
                'Calendar, clients, and day-to-day tasks in one flow',
                'Automation for repetitive workflows',
                'Digital channels to stay close to your customers',
            ],
        ],
        'business' => [
            'name' => 'Humano.app Business',
            'description' => 'Run your digital operations in one AI-assisted platform without losing the human touch that sets you apart.',
            'features' => [
                'Everything in Assistant, plus a unified digital cockpit',
                'AI that supports decisions without replacing your style',
                'Room to grow with your team and processes',
                'Operational visibility across the business',
            ],
        ],
        'foundation' => [
            'name' => 'Humano.app Foundation',
            'description' => 'The full stack to automate and grow your business beyond what you thought possible—without hiring more staff.',
            'features' => [
                'End-to-end automation tailored to how you work',
                'Scale revenue and capacity without linear headcount',
                'Priority guidance for complex rollouts',
                'A foundation built to compound over time',
            ],
        ],
    ],

    'checkout_complete_success' => 'Welcome! Your workspace is ready.',
    'checkout_complete_invalid_session' => 'We could not confirm this payment. Please open the link from your Stripe receipt or contact support.',
    'checkout_complete_not_paid' => 'This checkout is not completed or paid yet.',
    'checkout_complete_unsupported_mode' => 'This checkout type is not supported for automatic signup.',
    'checkout_complete_no_email' => 'Stripe did not provide an email for this payment, so we cannot create your account.',
    'checkout_complete_no_customer' => 'Stripe did not return a customer for this session.',
    'checkout_complete_no_team' => 'We could not attach this payment to a workspace. Please contact support.',
    'checkout_complete_customer_mismatch' => 'This payment belongs to a different Stripe customer than your current workspace. Sign in with the account that matches the payer email, or contact support.',
    'checkout_complete_register_first' => 'Create your account first, then complete checkout from the billing step.',

    'checkout_billing_gate_pending' => 'Your payment was received, but we could not unlock the app billing check yet. Please copy this message and contact support, or try again in a few minutes.',
];
