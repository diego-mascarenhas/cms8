<?php

return [
    /*
     * |--------------------------------------------------------------------------
     * | Default Team Modules Configuration
     * |--------------------------------------------------------------------------
     * |
     * | This configuration defines which modules should be enabled by default
     * | when a new team is created or when syncing team modules.
     * |
     * | true = enabled by default
     * | false = disabled by default
     * |
     */
    'defaults' => [
        // Core modules
        'dashboard' => true,
        'users' => false,
        'settings' => true,
        'contacts' => true,
        'clients' => true,
        'list60' => true,
        'prospecting' => true,
        'services' => false,
        'projects' => false,
        'tasks' => true,
        'calendar' => true,
        'notifications' => false,
        'templates' => false,
        // Additional modules (billing)
        'invoices' => true,
        'payments' => false,
        'incomes' => false,
        'expenses' => false,
        'financial' => true,
        'accounting' => false,
        // Additional modules (ecommerce)
        'products' => true,
        'orders' => true,
        'stores' => true,
        // Additional modules (infrastructure)
        'servers' => false,
        'hosting' => false,
        // Additional modules (general)
        'notes' => false,
        'collaborators' => false,
        'communications' => false,
        'enterprises' => false,
        'events' => false,
        'today' => false,
        'times' => true,
        'attendances' => false,
        'documentation' => false,
        'departments' => false,
        // Additional modules (campaigns)
        'mailer' => true,
        // Additional modules (automation)
        'funnel' => false,
        'integrations' => false,
        // Additional modules (content)
        'multimedia' => true,
        'content-sections' => false,
        'contents' => true,
        'website' => true,
        'academy' => true,
        'landings' => false,
        // Additional modules (support)
        'tickets' => false,
        'mailbox' => false,
        'chat' => false,
        // Additional modules (learning)
        'languages' => false,
        'language-variants' => false,
        'fares' => false,
        'softwares' => false,
        'certifications' => false,
        'stylebooks' => false,
    ],
];
