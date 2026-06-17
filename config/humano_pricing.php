<?php

return [
    /*
     * |--------------------------------------------------------------------------
     * | Public pricing (Stripe Payment Links)
     * |--------------------------------------------------------------------------
     * |
     * | Staging defaults match Humano Stripe test mode. Override with .env on
     * | production when you are ready to go live.
     * |
     * | signup_completion (default: payment_link)
     * |   payment_link — Default circuit: after Stripe Payment Link checkout, redirect
     * |     buyers to route('pricing.checkout.complete') with
     * |     ?session_id={CHECKOUT_SESSION_ID} (and optional &category=assistant|business|mentor). User
     * |     and team are ensured, then the user is logged in. Set the same URL in the
     * |     Stripe Payment Link "After payment" redirect field.
     * |   register_first — Opt-in legacy: send visitors to /register before paying
     * |     (set HUMANO_PRICING_SIGNUP_COMPLETION=register_first only if you need this).
     * | Any other or empty env value resolves to payment_link.
     * |
     * | Stripe for this flow: Payment Links for Humano plans always use the platform account
     * | (Cashier / STRIPE_* in .env). Team “own Stripe” settings apply to other products only.
     * |
     */
    'signup_completion' => strtolower(trim((string) env('HUMANO_PRICING_SIGNUP_COMPLETION', 'payment_link'))) === 'register_first'
        ? 'register_first'
        : 'payment_link',

    /*
     * | Default plan slug when checkout return URL omits &category= (assistant, business, or mentor).
     */
    'post_checkout_plan_slug' => match (strtolower(trim((string) env('HUMANO_PRICING_POST_CHECKOUT_PLAN_SLUG', 'assistant'))))
    {
        'hunter' => 'hunter',
        'business' => 'business',
        'mentor' => 'mentor',
        default => 'assistant',
    },

    /*
     * | Plan slug for the seeded Demo team: {@see TeamDemoSeeder} calls {@see TeamModulesByPricingPlanSyncer}
     * | with this value (assistant, business, or mentor). Mentor = business modules plus enterprise extras below.
     * | Override with HUMANO_PRICING_DEMO_TEAM_PLAN_SLUG.
     */
    'demo_team_plan_slug' => match (strtolower(trim((string) env('HUMANO_PRICING_DEMO_TEAM_PLAN_SLUG', 'assistant'))))
    {
        'hunter' => 'hunter',
        'business' => 'business',
        'mentor' => 'mentor',
        default => 'assistant',
    },

    /*
     * | Plan slug applied to the personal team created at /register (self-signup).
     * | Uses the same module bundle as {@see plan_team_modules} for that plan (default: hunter).
     * | Override with HUMANO_PRICING_REGISTRATION_TEAM_PLAN_SLUG.
     */
    'registration_team_plan_slug' => match (strtolower(trim((string) env('HUMANO_PRICING_REGISTRATION_TEAM_PLAN_SLUG', 'hunter'))))
    {
        'assistant' => 'assistant',
        'hunter' => 'hunter',
        'business' => 'business',
        'mentor' => 'mentor',
        default => 'hunter',
    },

    /*
     * | Referral / friend promotion code label (e.g. for copy in UI or translations).
     * | Not appended to Payment Link URLs — users enter it in Stripe checkout if they have it.
     */
    'coupon_code' => env('HUMANO_PRICING_COUPON_CODE', 'SOYAMIGO'),

    /*
     * | Promo code shown after slash landing lead capture (/slash). Falls back to coupon_code.
     */
    'slash_lead_coupon_code' => env('SLASH_LANDING_COUPON_CODE', 'HOYMISMO'),

    /*
     * | Payment Link: Checkout custom field keys (lowercase) whose value is the referrer team's Stripe
     * | customer id (cus_…) or legacy numeric referrer enterprise id. Keys must match Stripe's field.
     * | If the custom field is empty, pass client_reference_id=cus_… on the Payment Link URL.
     * | HUMANO_PRICING_PAYMENT_LINK_AFFILIATE_CUSTOM_FIELD_KEYS=referente,affiliate
     */
    'payment_link_affiliate_custom_field_keys' => array_values(array_filter(array_map(
        static fn (string $k): string => strtolower(trim($k)),
        explode(',', (string) env('HUMANO_PRICING_PAYMENT_LINK_AFFILIATE_CUSTOM_FIELD_KEYS', 'referente,affiliate')),
    ))),

    /*
     * | Affiliate commission % on Humano platform billing (team-to-team referrals).
     * | Applied when a referred team pays a Stripe invoice (see teams.referred_by).
     */
    'affiliate_commission_percent' => (float) env('HUMANO_AFFILIATE_COMMISSION_PERCENT', 30),

    /*
     * | Team that stores platform-wide affiliate settings (commission %). Defaults to CMS_TEAM_ID.
     */
    'platform_team_id' => (int) env('CMS_TEAM_ID', 0),

    /*
     * | Cookie + session fallback when Stripe checkout omits client_reference_id.
     * | Set on /affiliate/capture and invitation click tracking before redirect.
     */
    'affiliate_referral_cookie_name' => env('HUMANO_AFFILIATE_REFERRAL_COOKIE', 'humano_affiliate_ref'),
    'affiliate_referral_cookie_days' => (int) env('HUMANO_AFFILIATE_REFERRAL_COOKIE_DAYS', 90),

    /*
     * |--------------------------------------------------------------------------
     * | Team modules after checkout (by plan id: assistant, business, mentor)
     * |--------------------------------------------------------------------------
     * |
     * | Matched via stripe_product_id on the subscription vs plans below.
     * | Each plan lists every module key to enable (business repeats assistant + extras).
     * | mentor is business plus org, CRM, API, files, support, extended billing, and commerce keys.
     * | Demo team modules follow demo_team_plan_slug above (default: assistant).
     * | Keys must match modules.key (see ModuleSeeder). Include settings so team
     * | settings stay usable after paid signup.
     * |
     */
    'plan_team_modules' => [
        'assistant' => [
            'today',
            'settings',
            'calendar',
            'contacts',
            'tasks',
            'prompts',
            'chat',
        ],
        'hunter' => [
            'today',
            'settings',
            'calendar',
            'contacts',
            'tasks',
            'prospecting',
            'prompts',
            'mailer',
            'landings',
            'chat',
        ],
        'business' => [
            'settings',
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
        'mentor' => [
            'settings',
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
            'users',
            'projects',
            'opportunities',
            'templates',
            'integrations',
            'team_files',
            'tickets',
            'departments',
            'collaborators',
            'enterprises',
            'subscriptions',
            'performance_insights',
            'accounting',
            'incomes',
            'expenses',
            'stores',
            'products',
            'orders',
        ],
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Plans (Stripe Payment Links + Price IDs)
     * |--------------------------------------------------------------------------
     * |
     * | Default checkout URLs and Stripe catalog IDs for Humano.app (override with
     * | HUMANO_PRICING_* in .env). Display names and marketing copy live under lang (humano_pricing.php).
     * |
     * | Humano.app Assistant — Payment Link …/3cIeVd98VabI07cgPb43S03, product prod_UUoDnxftlyItz0,
     * |   monthly price_1TVoawGelYN536DrEH4gIAsR (99€), yearly price_1TVod6GelYN536DrtCsqOG6d (990€).
     * | Humano.app Business — …/6oU14nfxjabIbPUbuR43S04, prod_UUoHz602tHBY8b,
     * |   monthly price_1TVoebGelYN536DrLAOm6k90 (299€), yearly price_1TVof6GelYN536DrAaThyVzr (2990€).
     * | Humano.app Mentor — …/4gM4gz3OB0B82fkcyV43S05, prod_UUoIeGCxj2MfcL,
     * |   monthly price_1TVofaGelYN536DrGEL9txGS (999€), yearly price_1TVog3GelYN536DryyMGQ0rE (9990€).
     * |
     * | checkout_available (per plan): when false, the public pricing card hides amounts and shows
     * | "Coming soon" instead of the Stripe subscribe button. Override with HUMANO_PRICING_*_CHECKOUT_AVAILABLE.
     * |
     */
    'plans' => [
        [
            'id' => 'assistant',
            'checkout_url' => env(
                'HUMANO_PRICING_ASSISTANT_CHECKOUT_URL',
                'https://buy.stripe.com/5kQ4gzacZ3Nk9HM0Qd43S07',
            ),
            'checkout_url_yearly' => env(
                'HUMANO_PRICING_ASSISTANT_CHECKOUT_URL_YEARLY',
                'https://buy.stripe.com/aFa5kDgBn5Vs07c56t43S09',
            ),
            'stripe_product_id' => env('HUMANO_PRICING_ASSISTANT_STRIPE_PRODUCT_ID', 'prod_UJkenQzkHZM1Sr'),
            'stripe_price_monthly_id' => env('HUMANO_PRICING_ASSISTANT_PRICE_MONTHLY_ID', 'price_1ThAmkGelYN536DrP364au8Z'),
            'stripe_price_yearly_id' => env('HUMANO_PRICING_ASSISTANT_PRICE_YEARLY_ID', 'price_1TUro1GelYN536DriuckpG7j'),
            'monthly_amount' => '49',
            'yearly_amount' => '490',
            'popular' => false,
            'checkout_available' => filter_var(
                (string) env('HUMANO_PRICING_ASSISTANT_CHECKOUT_AVAILABLE', 'true'),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],
        [
            'id' => 'hunter',
            'checkout_url' => env(
                'HUMANO_PRICING_HUNTER_CHECKOUT_URL',
                'https://buy.stripe.com/6oU14ngBn2Jg7zE0Qd43S08',
            ),
            'checkout_url_yearly' => env(
                'HUMANO_PRICING_HUNTER_CHECKOUT_URL_YEARLY',
                'https://buy.stripe.com/fZu7sLfxjfw2g6afL743S0a',
            ),
            'stripe_product_id' => env('HUMANO_PRICING_HUNTER_STRIPE_PRODUCT_ID', 'prod_UgXq13WgRU1IiP'),
            'stripe_price_monthly_id' => env('HUMANO_PRICING_HUNTER_PRICE_MONTHLY_ID', 'price_1ThAkUGelYN536DrhZmSAwVr'),
            'stripe_price_yearly_id' => env('HUMANO_PRICING_HUNTER_PRICE_YEARLY_ID', 'price_1ThAlmGelYN536Dr7yEl4WaW'),
            'monthly_amount' => env('HUMANO_PRICING_HUNTER_MONTHLY_AMOUNT', '99'),
            'yearly_amount' => env('HUMANO_PRICING_HUNTER_YEARLY_AMOUNT', '990'),
            'popular' => false,
            'checkout_available' => filter_var(
                (string) env('HUMANO_PRICING_HUNTER_CHECKOUT_AVAILABLE', 'true'),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],
        [
            'id' => 'business',
            'checkout_url' => env(
                'HUMANO_PRICING_BUSINESS_CHECKOUT_URL',
                'https://buy.stripe.com/6oU9AT3OB5VsbPUeH343S06',
            ),
            'checkout_url_yearly' => env(
                'HUMANO_PRICING_BUSINESS_CHECKOUT_URL_YEARLY',
                'https://buy.stripe.com/cNidR998VbfM4ns9mJ43S0b',
            ),
            'stripe_product_id' => env('HUMANO_PRICING_BUSINESS_STRIPE_PRODUCT_ID', 'prod_UUoHz602tHBY8b'),
            'stripe_price_monthly_id' => env('HUMANO_PRICING_BUSINESS_PRICE_MONTHLY_ID', 'price_1TVoebGelYN536DrLAOm6k90'),
            'stripe_price_yearly_id' => env('HUMANO_PRICING_BUSINESS_PRICE_YEARLY_ID', 'price_1TVof6GelYN536DrAaThyVzr'),
            'monthly_amount' => '299',
            'yearly_amount' => '2990',
            'popular' => true,
            'checkout_available' => filter_var(
                (string) env('HUMANO_PRICING_BUSINESS_CHECKOUT_AVAILABLE', 'true'),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],
        [
            'id' => 'mentor',
            'checkout_url' => env(
                'HUMANO_PRICING_MENTOR_CHECKOUT_URL',
                'https://buy.stripe.com/4gM4gz3OB0B82fkcyV43S05',
            ),
            'checkout_url_yearly' => env(
                'HUMANO_PRICING_MENTOR_CHECKOUT_URL_YEARLY',
                '',
            ),
            'stripe_product_id' => env('HUMANO_PRICING_MENTOR_STRIPE_PRODUCT_ID', 'prod_UUoIeGCxj2MfcL'),
            'stripe_price_monthly_id' => env('HUMANO_PRICING_MENTOR_PRICE_MONTHLY_ID', 'price_1TVofaGelYN536DrGEL9txGS'),
            'stripe_price_yearly_id' => env('HUMANO_PRICING_MENTOR_PRICE_YEARLY_ID', 'price_1TVog3GelYN536DryyMGQ0rE'),
            'monthly_amount' => '999',
            'yearly_amount' => '9990',
            'popular' => false,
            'checkout_available' => filter_var(
                (string) env('HUMANO_PRICING_MENTOR_CHECKOUT_AVAILABLE', 'false'),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],
        [
            'id' => 'innovation',
            'checkout_url' => '',
            'external_url' => env('HUMANO_PRICING_INNOVATION_EXTERNAL_URL', 'https://fanyion.com'),
            'stripe_product_id' => '',
            'stripe_price_monthly_id' => '',
            'stripe_price_yearly_id' => '',
            'monthly_amount' => '',
            'yearly_amount' => '',
            'popular' => false,
            'checkout_available' => false,
        ],
    ],
];
