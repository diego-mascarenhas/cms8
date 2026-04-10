<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Humano Core Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains core configuration options for the Humano system.
    |
    */

    'name' => 'Humano Core',
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'default_route' => 'dashboard.analytics',
        'show_analytics' => true,
        'widgets' => [
            'activity_log',
            'team_stats',
            'quick_actions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */
    'users' => [
        'allow_registration' => false,
        'require_email_verification' => true,
        'default_role' => 'user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Team Configuration
    |--------------------------------------------------------------------------
    */
    'teams' => [
        'allow_team_creation' => true,
        'max_teams_per_user' => 5,
        'default_settings' => [
            'timezone' => 'UTC',
            'currency' => 'USD',
            'language' => 'en',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module System
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'auto_discover' => true,
        'enabled_modules' => [
            'crm',
            'billing',
            'communications',
            'hosting',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'theme' => 'vuexy',
        'sidebar_collapsed' => false,
        'show_breadcrumbs' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp demo line → store team (Humano showcase only)
    |--------------------------------------------------------------------------
    |
    | Only the demo LINE team's inbound webhook is rewritten. Any other team's number
    | (client after QR, etc.) always uses that team's own id — safe to change the store
    | id here between prospect demos without breaking existing clients.
    |
    | If whatsapp_demo_store_team_id is empty/unset, every line uses the webhook team.
    |
    | When set AND inbound is for whatsapp_demo_line_team_id (default 1), that line's
    | assistant uses the store team id for tools, catalog, cart, prompts, memory.
    |
    */
    'whatsapp_demo_line_team_id' => (int) (env('WHATSAPP_DEMO_LINE_TEAM_ID') ?: 1),
    'whatsapp_demo_store_team_id' => env('WHATSAPP_DEMO_STORE_TEAM_ID') !== null && env('WHATSAPP_DEMO_STORE_TEAM_ID') !== ''
        ? (int) env('WHATSAPP_DEMO_STORE_TEAM_ID')
        : null,

];
