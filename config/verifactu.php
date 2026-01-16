<?php

return [
    'enabled' => env('VERIFACTU_ENABLED', true),
    'default_currency' => env('VERIFACTU_CURRENCY', 'EUR'),
    'issuer' => [
        'name' => env('VERIFACTU_ISSUER_NAME', ''),
        'vat' => env('VERIFACTU_ISSUER_VAT', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | AEAT Connection Settings
    |--------------------------------------------------------------------------
    |
    | Certificate path and password for SOAP authentication with AEAT.
    | The certificate must be in PKCS12 format (.p12 or .pfx).
    |
    */
    'certificate' => [
        'path' => env('VERIFACTU_CERT_PATH', ''),
        'password' => env('VERIFACTU_CERT_PASSWORD', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Set to true for production (www1.aeat.es) or false for testing (prewww1.aeat.es)
    |
    */
    'production' => env('VERIFACTU_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Load Package Migrations
    |--------------------------------------------------------------------------
    |
    | Set to true if you want to use the package's Invoice, Breakdown, and
    | Recipient models. Set to false if you have your own invoice system
    | and will implement the VeriFactu contracts on your existing models.
    |
    */
    'load_migrations' => env('VERIFACTU_LOAD_MIGRATIONS', false),
];
