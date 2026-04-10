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

];
