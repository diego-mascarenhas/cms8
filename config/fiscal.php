<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound Fiscal Export
    |--------------------------------------------------------------------------
    |
    | Humano is the local source of truth for invoices. Payments arrive from
    | providers (Stripe, PayPal, Mercado Pago, ...) and are turned into core
    | invoices. From there, each invoice can be exported to the legal/fiscal
    | platform that matches the issuing team's country (Cuéntica for Spain,
    | ARCA for Argentina, ...).
    |
    */

    'enabled' => env('FISCAL_EXPORT_ENABLED', true),

    /*
    | Platform used when a team has no explicit "fiscal_platform" setting and
    | no resolvable fiscal country. Set to null to skip export by default.
    */
    'default_platform' => env('FISCAL_DEFAULT_PLATFORM', 'cuentica'),

    /*
    | Local invoice statuses that are eligible for fiscal export.
    | 2 => "Impresa / cobrada" (paid).
    */
    'export_on_status' => [2],

    /*
    | Local invoice statuses that, once already exported, must trigger a
    | rectification (credit note) on the fiscal platform.
    | 3 => Anulada, 4 => Nota de Crédito.
    */
    'rectify_on_status' => [3, 4],

    /*
    | Resolve the fiscal platform for a team by its fiscal country code.
    | When a team has no explicit configuration, this map is the fallback.
    */
    'default_platform_by_country' => [
        'ES' => 'cuentica',
        'AR' => 'arca',
    ],

    /*
    | Per-platform configuration. Credentials may also be overridden per team
    | through team settings (e.g. setting key "cuentica_api_token").
    */
    'platforms' => [

        'cuentica' => [
            'enabled' => env('CUENTICA_ENABLED', true),
            'countries' => ['ES'],
            'base_url' => env('CUENTICA_BASE_URL', 'https://api.cuentica.com'),
            'timeout' => (int) env('CUENTICA_TIMEOUT', 30),

            // Credentials are configured per team in team settings
            // ("cuentica_api_token"). There is no global token: every team
            // (including Humano's own) issues invoices into its own account.

            // Invoice defaults applied when the local data does not specify them.
            'invoice_serie' => env('CUENTICA_INVOICE_SERIE'),
            'default_tax_percent' => (float) env('CUENTICA_DEFAULT_TAX_PERCENT', 21),
            'default_sell_type' => env('CUENTICA_DEFAULT_SELL_TYPE', 'service'),
            'default_tax_regime' => env('CUENTICA_DEFAULT_TAX_REGIME', '01'),
            'default_tax_subjection_code' => env('CUENTICA_DEFAULT_TAX_SUBJECTION_CODE', 'S1'),
            'default_payment_method' => env('CUENTICA_DEFAULT_PAYMENT_METHOD', 'card'),
            'default_business_type' => env('CUENTICA_DEFAULT_BUSINESS_TYPE', 'company'),
            'default_country_code' => env('CUENTICA_DEFAULT_COUNTRY_CODE', 'ES'),
        ],

        'arca' => [
            'enabled' => env('ARCA_ENABLED', false),
            'countries' => ['AR'],
            // Credentials and comprobante settings are added in a later phase.
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting / retries
    |--------------------------------------------------------------------------
    |
    | Cuéntica allows 600 requests / 5 min and 7200 / day. The export job uses
    | these values to back off when it receives HTTP 429.
    |
    */
    'retry' => [
        'max_attempts' => (int) env('FISCAL_EXPORT_MAX_ATTEMPTS', 5),
        'backoff_seconds' => [60, 120, 300, 600],
    ],
];
